<?php
namespace StorePortal\AdminEmbed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Model\UrlInterface;

class Token extends AbstractHelper
{
    /**
     * Must match WP_PLUGIN_API_KEY in your Flask .env file.
     * Change this if you updated WP_PLUGIN_API_KEY.
     */
    const API_KEY = 'StorePortalWpKey2026Mazhar';

    /**
     * Your Store Portal URL (same as APP_URL in Flask .env).
     * Change this when your ngrok URL changes.
     */
    const PORTAL_URL = 'https://nonanarchically-rambunctious-lashay.ngrok-free.dev';

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
    }

    public function getPortalUrl(): string
    {
        return rtrim(self::PORTAL_URL, '/');
    }

    /**
     * Generate a signed auto-login token.
     * Format matches the WordPress plugin token so Flask can verify both.
     * Token: base64(store_url|timestamp|HMAC-SHA256(store_url|timestamp, API_KEY))
     */
    public function generateToken(): string
    {
        $storeUrl  = $this->storeManager->getStore()->getBaseUrl();
        $timestamp = time();
        $payload   = $storeUrl . '|' . $timestamp;
        $sig       = hash_hmac('sha256', $payload, self::API_KEY);
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
