<?php
namespace StorePortal\AdminEmbed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Model\UrlInterface;

class Token extends AbstractHelper
{
    const XML_PATH_PORTAL_URL = 'storeportal/general/portal_url';
    const XML_PATH_API_KEY    = 'storeportal/general/api_key';

    // Fallback defaults — used when no value is saved in admin
    const DEFAULT_PORTAL_URL = 'http://localhost:5000';
    const DEFAULT_API_KEY    = 'StorePortalWpKey2026Mazhar';

    protected $storeManager;
    protected $backendUrl;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        UrlInterface $backendUrl
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->backendUrl   = $backendUrl;
        // $this->scopeConfig is already available via AbstractHelper/Context
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
     * Generate a signed auto-login token.
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
}
