<?php

declare(strict_types=1);

namespace StorePortal\AdminEmbed\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * HMAC-SHA256 license validator for StorePortal_AdminEmbed.
 * Follows eTechFlow LICENSING_PROTOCOL.md exactly.
 * No internet call required — math proves the key was minted for this domain.
 */
class LicenseValidator
{
    // ── Per-module constants — unique to THIS module ──────────────────────────
    // MODULE_ID must match the slug used by tools/generate-license.php and
    // the webstore license generator. Do NOT change after shipping.

    const MODULE_ID = 'store-portal-magento';

    const SECRET_FRAGMENTS = [
        'Sp3M9xK2qL7v',
        'wR4nB8tY1jH6c',
        'mX5eA2uF9dP3z',
        'qK7sN1gV4wJ8b',
    ];

    const XML_PATH_LICENSE_KEY            = 'storeportal/license/license_key';
    const XML_PATH_PRODUCTION_ENVIRONMENT = 'storeportal/license/production_environment';

    // ── Bundle constants — IDENTICAL in every StorePortal module ─────────────
    // A bundle key activates ALL StorePortal modules (All-Channels + Enterprise plans).
    // BUNDLE_SECRET_FRAGMENTS must NEVER differ between modules — if they drift,
    // the bundle key silently breaks for that module.

    const BUNDLE_ID = 'store-portal-bundle';

    const BUNDLE_SECRET_FRAGMENTS = [
        'Bp1X7mQ3kR9n',
        'wA4cE8hL2gT5v',
        'sJ6uY0dF3zP7m',
        'nK9bW5iC1oR4x',
    ];

    const XML_PATH_BUNDLE_LICENSE_KEY = 'storeportal_bundle/license/license_key';

    // ── Dev / staging host patterns — auto-bypass (no key needed) ────────────
    // Match LICENSING_PROTOCOL.md §9. Add new patterns here when new dev
    // environments are introduced company-wide.

    private const DEV_PATTERNS = [
        '/^localhost$/i',
        '/^127\.\d+\.\d+\.\d+$/',
        '/^192\.168\./',
        '/^10\./',
        '/^172\.(1[6-9]|2\d|3[01])\./',
        '/\.test$/i',
        '/\.local$/i',
        '/\.localhost$/i',
        '/\.dev$/i',
        '/\.example$/i',
        '/\.invalid$/i',
        '/^staging\./i',
        '/^stage\./i',
        '/^dev\./i',
        '/^qa\./i',
        '/^uat\./i',
        '/^test\./i',
        '/^preview\./i',
        '/^sandbox\./i',
        '/-staging\./i',
        '/-dev\./i',
        '/\.ngrok\.io$/i',
        '/\.ngrok-free\.app$/i',
        '/\.loca\.lt$/i',
        '/\.magento\.cloud$/i',
        '/\.magentocloud\.com$/i',
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Returns true if this store has a valid per-module or bundle license.
     * Dev / staging hosts always return true (LICENSING_PROTOCOL.md §9).
     * Soft-expiry model: subscription lapse never breaks the module.
     */
    public function isValid(): bool
    {
        $host = $this->getCurrentHost();

        if ($this->isDevelopmentHost($host)) {
            return true;
        }

        // Production environment toggle: default=true on null/empty, "0"=false, "1"=true
        $isProd = $this->scopeConfig->getValue(
            self::XML_PATH_PRODUCTION_ENVIRONMENT,
            ScopeInterface::SCOPE_STORE
        );
        if ($isProd !== null && $isProd !== '' && $isProd === '0') {
            return true;
        }

        $canonical = $this->canonicalize($host);

        // Check per-module key first
        $moduleKey = (string) ($this->scopeConfig->getValue(
            self::XML_PATH_LICENSE_KEY,
            ScopeInterface::SCOPE_STORE
        ) ?? '');
        if ($moduleKey !== '' && hash_equals($this->computeKey($canonical), $moduleKey)) {
            return true;
        }

        // Fall back to bundle key (All-Channels / Enterprise customers use this)
        $bundleKey = (string) ($this->scopeConfig->getValue(
            self::XML_PATH_BUNDLE_LICENSE_KEY,
            ScopeInterface::SCOPE_STORE
        ) ?? '');
        if ($bundleKey !== '' && hash_equals($this->computeBundleKey($canonical), $bundleKey)) {
            return true;
        }

        return false;
    }

    /**
     * Compute the per-module HMAC key.
     * HMAC-SHA256(canonicalHost + ":" + MODULE_ID, assembled_secret)
     * Must produce identical output to the portal's license_engine.py
     * generate_license_key() when the same master secret is assembled.
     */
    public function computeKey(string $canonicalHost): string
    {
        $secret = implode('', self::SECRET_FRAGMENTS);
        return hash_hmac('sha256', $canonicalHost . ':' . self::MODULE_ID, $secret);
    }

    /**
     * Compute the bundle HMAC key.
     * Used to validate All-Channels and Enterprise plan keys.
     */
    public function computeBundleKey(string $canonicalHost): string
    {
        $secret = implode('', self::BUNDLE_SECRET_FRAGMENTS);
        return hash_hmac('sha256', $canonicalHost . ':' . self::BUNDLE_ID, $secret);
    }

    /**
     * Strip www., remove port number, lowercase.
     * www.store.com → store.com (one key works for both).
     */
    public function canonicalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = (string) preg_replace('/^www\./u', '', $host);
        $host = (string) preg_replace('/:\d+$/', '', $host);
        return $host;
    }

    /**
     * Return the current store's hostname from the HTTP request.
     */
    public function getCurrentHost(): string
    {
        return (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /**
     * Return true if this host is a dev / staging environment.
     * These bypass licensing — merchants get free dev/staging use.
     */
    private function isDevelopmentHost(string $host): bool
    {
        $canonical = $this->canonicalize($host);
        foreach (self::DEV_PATTERNS as $pattern) {
            if (preg_match($pattern, $canonical)) {
                return true;
            }
        }
        return false;
    }
}
