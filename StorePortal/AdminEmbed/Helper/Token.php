<?php

declare(strict_types=1);

namespace StorePortal\AdminEmbed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Integration\Model\Oauth\TokenFactory;
use Magento\Store\Model\ScopeInterface;

class Token extends AbstractHelper
{
    const XML_PATH_PORTAL_URL   = 'storeportal/general/portal_url';
    const XML_PATH_API_KEY      = 'storeportal/general/api_key';
    const XML_PATH_LICENSE_KEY  = 'storeportal/license/license_key';

    const DEFAULT_PORTAL_URL = 'http://localhost:5000';
    const DEFAULT_API_KEY    = 'StorePortalWpKey2026Mazhar';

    public function __construct(
        Context $context,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $backendUrl,
        private readonly Curl $curl,
        private readonly AdminSession $adminSession,
        private readonly TokenFactory $tokenFactory
    ) {
        parent::__construct($context);
    }

    public function getPortalUrl(): string
    {
        $url = $this->scopeConfig->getValue(self::XML_PATH_PORTAL_URL);
        return rtrim($url ?: self::DEFAULT_PORTAL_URL, '/');
    }

    /**
     * Portal URL for server-side PHP curl calls.
     * Inside Docker, 'localhost' resolves to the container itself — swap it for
     * 'host.docker.internal' so curl can reach Flask running on the host machine.
     */
    private function getInternalPortalUrl(): string
    {
        return str_replace('://localhost', '://host.docker.internal', $this->getPortalUrl());
    }

    public function getApiKey(): string
    {
        $key = $this->scopeConfig->getValue(self::XML_PATH_API_KEY);
        return $key ?: self::DEFAULT_API_KEY;
    }

    public function getLicenseKey(): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::XML_PATH_LICENSE_KEY,
            ScopeInterface::SCOPE_STORE
        ) ?? '');
    }

    /**
     * Validate access by checking domain + license key against the portal's subscription system.
     * Result is cached per request.
     */
    public function validateLicense(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $portalUrl  = $this->getInternalPortalUrl();
        $licenseKey = $this->getLicenseKey();

        // Extract the domain from the store base URL
        $storeUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $parsed   = parse_url($storeUrl);
        $host     = $parsed['host'] ?? 'localhost';
        $port     = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $domain   = $host . $port;

        $params = ['domain' => $domain, 'platform' => 'magento'];
        if ($licenseKey !== '') {
            $params['license_key'] = $licenseKey;
        }

        $url = $portalUrl . '/license/validate?' . http_build_query($params);

        try {
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $this->curl->setOption(CURLOPT_TIMEOUT, 5);
            $this->curl->get($url);

            $body   = $this->curl->getBody();
            $result = json_decode($body, true);
            $cache  = is_array($result) ? $result : ['valid' => false, 'message' => 'Invalid response from portal.'];
        } catch (\Exception $e) {
            // Portal unreachable — fail open (don't break admin) but mark invalid
            $cache = ['valid' => false, 'message' => 'Could not reach portal: ' . $e->getMessage()];
        }

        return $cache;
    }

    /**
     * Returns true if the current store domain has an active subscription
     * and the configured license key is valid.
     */
    public function isLicensed(): bool
    {
        return (bool) ($this->validateLicense()['valid'] ?? false);
    }

    /**
     * Generate a signed auto-login token for portal iframe embedding.
     * Token: base64(store_url|timestamp|HMAC-SHA256(store_url|timestamp, api_key))
     */
    public function generateToken(): string
    {
        $storeUrl  = $this->storeManager->getStore()->getBaseUrl();
        $timestamp = time();
        $payload   = $storeUrl . '|' . $timestamp;
        $sig       = hash_hmac('sha256', $payload, $this->getApiKey());
        return base64_encode($payload . '|' . $sig);
    }

    /**
     * Build the full iframe URL with auto-login token and return URL.
     */
    public function getIframeUrl(string $path = '/dashboard'): string
    {
        $portalUrl = $this->getPortalUrl();
        $token     = $this->generateToken();
        $returnUrl = $this->backendUrl->getUrl('storeportal_embed/index/index');
        return $portalUrl . $path
            . '?wp_token=' . urlencode($token)
            . '&wp_return=' . urlencode(rtrim($returnUrl, '/'));
    }

    /**
     * Auto-connect this Magento store to the portal using the currently
     * logged-in admin session — no username/password required.
     */
    public function autoConnect(): bool
    {
        $adminUser = $this->adminSession->getUser();
        if (!$adminUser || !$adminUser->getId()) {
            return false;
        }

        $storeUrl  = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $parsed    = parse_url($storeUrl);
        $host      = $parsed['host'] ?? '';
        $port      = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $domain    = $host . $port;
        $storeName = $domain ? 'Magento — ' . $domain : $this->storeManager->getStore()->getName();
        $portalUrl = $this->getInternalPortalUrl();
        $apiKey    = $this->getApiKey();

        try {
            $tokenModel  = $this->tokenFactory->create();
            $tokenModel->createAdminToken($adminUser->getId());
            $accessToken = $tokenModel->getToken();
        } catch (\Exception $e) {
            return false;
        }

        if (empty($accessToken)) {
            return false;
        }

        $timestamp  = (string) time();
        $payload    = $storeUrl . '|' . $timestamp;
        $sig        = hash_hmac('sha256', $payload, $apiKey);

        $postFields = http_build_query([
            'store_url'    => $storeUrl,
            'store_name'   => $storeName,
            'access_token' => $accessToken,
            'timestamp'    => $timestamp,
            'sig'          => $sig,
        ]);

        try {
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $this->curl->setOption(CURLOPT_TIMEOUT, 10);
            $this->curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
            $this->curl->post($portalUrl . '/magento/auto-connect', $postFields);
            return $this->curl->getStatus() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
