<?php
namespace StorePortal\AdminEmbed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Integration\Model\Oauth\TokenFactory;

class Token extends AbstractHelper
{
    const XML_PATH_PORTAL_URL = 'storeportal/general/portal_url';
    const XML_PATH_API_KEY    = 'storeportal/general/api_key';

    const DEFAULT_PORTAL_URL = 'https://module.etechflow.com';
    const DEFAULT_API_KEY    = 'StorePortalWpKey2026Mazhar';

    protected $storeManager;
    protected $backendUrl;
    protected $curl;
    protected $adminSession;
    protected $tokenFactory;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        UrlInterface $backendUrl,
        Curl $curl,
        AdminSession $adminSession,
        TokenFactory $tokenFactory
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->backendUrl   = $backendUrl;
        $this->curl         = $curl;
        $this->adminSession = $adminSession;
        $this->tokenFactory = $tokenFactory;
    }

    public function getPortalUrl(): string
    {
        $url = $this->scopeConfig->getValue(self::XML_PATH_PORTAL_URL);
        return rtrim($url ?: self::DEFAULT_PORTAL_URL, '/');
    }

    public function getApiKey(): string
    {
        $key = $this->scopeConfig->getValue(self::XML_PATH_API_KEY);
        return $key ?: self::DEFAULT_API_KEY;
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
     * logged-in admin's session — no username/password required.
     *
     * Generates a Magento REST API token for the active admin user, signs the
     * request with HMAC-SHA256, and POSTs to the portal's /magento/auto-connect
     * endpoint. The portal stores the token and marks the store as connected.
     */
    public function autoConnect(): bool
    {
        $adminUser = $this->adminSession->getUser();
        if (!$adminUser || !$adminUser->getId()) {
            return false;
        }

        $storeUrl  = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $storeName = $this->storeManager->getStore()->getName();
        $portalUrl = $this->getPortalUrl();
        $apiKey    = $this->getApiKey();

        // Generate a Magento REST API token for the logged-in admin (no password needed)
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

        $timestamp  = (string)time();
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
