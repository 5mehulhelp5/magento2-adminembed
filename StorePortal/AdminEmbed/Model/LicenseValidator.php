<?php





declare(strict_types=1);





namespace StorePortal\AdminEmbed\Model;





use Magento\Framework\App\CacheInterface;


use Magento\Framework\App\Config\ScopeConfigInterface;


use Magento\Framework\HTTP\Client\Curl;


use Magento\Store\Model\ScopeInterface;


use Magento\Store\Model\StoreManagerInterface;





/**


 * Validates the license for StorePortal_AdminEmbed.


 *


 * Two config URLs:


 *   portal_url     — shown in admin browser links (can be http://127.0.0.1:5000)


 *   portal_api_url — called by the Magento SERVER; must be publicly reachable.


 *                    Falls back to portal_url if blank (works when portal is public).


 *


 * Validation priority:


 *   1. production_environment = No  → dev mode, always valid.


 *   2. production_environment = Yes → require valid key, no exceptions:


 *      a. SP-XXXX → live call to portal_api_url/license/validate (domain + IP check).


 *      b. Legacy HMAC → offline check.


 *      c. No key → invalid.


 */


class LicenseValidator


{


    public const XML_PATH_LICENSE_KEY            = 'storeportal/license/license_key';


    public const XML_PATH_PRODUCTION_ENVIRONMENT = 'storeportal/license/production_environment';


    public const XML_PATH_PORTAL_URL             = 'storeportal/license/portal_url';


    public const XML_PATH_PORTAL_API_URL         = 'storeportal/license/portal_api_url';





    public const XML_PATH_BUNDLE_LICENSE_KEY = 'etechflow_bundle/license/license_key';





    private const MODULE_ID      = 'admin-embed';


    private const BUNDLE_ID      = 'etechflow-bundle';





    private const SECRET_FRAGMENTS = [


        'eTF-AEMB-2026', 'q7M2-vK9p', 'R4cX-bN8t', 'L1aD-hW5j',


    ];





    private const BUNDLE_SECRET_FRAGMENTS = [


        'eTF-BUNDLE-2026', 'k2D9-mP4x', 'L8nR-vH2j', 'X7tY-zW5q',


    ];





    private const CACHE_TTL = 3600;


    private const CACHE_TAG = 'storeportal_embed_lic_license';





    public function __construct(


        private readonly ScopeConfigInterface $scopeConfig,


        private readonly StoreManagerInterface $storeManager,


        private readonly CacheInterface $cache,


        private readonly Curl $curl


    ) {


    }





    public function isValid(): bool


    {


        $host = $this->getCurrentHost();


        if ($host === '') {


            return false;


        }





        // Explicit portal revocation always wins — even in dev mode


        if ($this->isExplicitlyRevoked()) {


            return false;


        }





        // production_environment = No → dev mode, bypass


        if (!$this->isProductionEnvironment()) {


            return true;


        }





        // production_environment = Yes → full enforcement


        $configuredKey = $this->getConfiguredKey();





        if (str_starts_with($configuredKey, 'SP-')) {


            // Fast path: key purchased inside Magento — skip portal API call


            if ($this->isLocallyIssuedKey($configuredKey, $host)) {


                return true;


            }


            return $this->validateViaPortal($host, $configuredKey);


        }





        if ($configuredKey !== '' && hash_equals($this->computeKey($host), $configuredKey)) {


            return true;


        }





        $bundleKey = $this->getConfiguredBundleKey();


        if ($bundleKey !== '' && hash_equals($this->computeBundleKey($host), $bundleKey)) {


            return true;


        }





        return false;


    }





    /**


     * Call portal API to validate SP-XXXX key.


     * Flask checks domain match AND server IP against allowed_ips.


     * Cached for 1 hour.


     */


    private function validateViaPortal(string $host, string $licenseKey): bool


    {


        $cacheKey = 'etf_alm_lic_' . md5($host . ':' . $licenseKey);


        $cached   = $this->cache->load($cacheKey);


        if ($cached !== false) {


            return $cached === '1';


        }





        $apiBase = $this->getPortalApiBase();


        if ($apiBase === '') {


            return false;  // No API URL configured


        }





        $url = rtrim($apiBase, '/') . '/license/validate'


            . '?domain='      . urlencode($this->canonicalize($host))


            . '&license_key=' . urlencode($licenseKey)


            . '&platform=magento';





        $valid = false;


        try {


            $this->curl->setTimeout(5);


            $this->curl->addHeader('Accept', 'application/json');


            $this->curl->addHeader('User-Agent', 'ETechFlow-ALM/1.0');


            $this->curl->get($url);


            $status = $this->curl->getStatus();


            $body   = $this->curl->getBody();


            if ($status === 200 && $body) {


                $data  = json_decode($body, true);


                $valid = !empty($data['valid']);


            }


        } catch (\Exception) {


            $valid = false;


        }





        $this->cache->save(


            $valid ? '1' : '0',


            $cacheKey,


            [self::CACHE_TAG],


            self::CACHE_TTL


        );





        return $valid;


    }





    /** API URL for server-side calls: portal_api_url, falls back to portal_url. */


    private function getPortalApiBase(): string


    {


        $api = trim((string) $this->scopeConfig->getValue(self::XML_PATH_PORTAL_API_URL));


        if ($api !== '') {


            return $api;


        }


        // Fall back to portal_url only if it is not localhost/127.x (unreachable from server)


        $browser = trim((string) $this->scopeConfig->getValue(self::XML_PATH_PORTAL_URL));


        if ($browser !== '' && !str_contains($browser, '127.0.0.1') && !str_contains($browser, 'localhost')) {


            return $browser;


        }


        return '';


    }





    public function computeKey(string $host): string


    {


        $payload = $this->canonicalize($host) . ':' . self::MODULE_ID;


        $secret  = implode('', self::SECRET_FRAGMENTS);


        $raw     = hash_hmac('sha256', $payload, $secret, true);


        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');


    }





    public function computeBundleKey(string $host): string


    {


        $payload = $this->canonicalize($host) . ':' . self::BUNDLE_ID;


        $secret  = implode('', self::BUNDLE_SECRET_FRAGMENTS);


        $raw     = hash_hmac('sha256', $payload, $secret, true);


        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');


    }





    private function canonicalize(string $host): string


    {


        $host = strtolower(trim($host));


        if (str_starts_with($host, 'www.')) {


            $host = substr($host, 4);


        }


        return $host;


    }





    public function getConfiguredKey(): string


    {


        $value = $this->scopeConfig->getValue(self::XML_PATH_LICENSE_KEY, ScopeInterface::SCOPE_STORE);


        return trim((string) $value);


    }





    public function getConfiguredBundleKey(): string


    {


        $value = $this->scopeConfig->getValue(self::XML_PATH_BUNDLE_LICENSE_KEY, ScopeInterface::SCOPE_STORE);


        return trim((string) $value);


    }





    public function isProductionEnvironment(): bool


    {


        $value = $this->scopeConfig->getValue(self::XML_PATH_PRODUCTION_ENVIRONMENT, ScopeInterface::SCOPE_STORE);


        if ($value === null || $value === '') {


            return true;


        }


        return (bool) $value;


    }





    public function getCurrentHost(): string


    {


        try {


            $url  = $this->storeManager->getStore()->getBaseUrl();


            $host = parse_url($url, PHP_URL_HOST);


            return is_string($host) ? strtolower($host) : '';


        } catch (\Exception) {


            return '';


        }


    }





    public function isDevHost(?string $host = null): bool


    {


        $check = $host !== null ? strtolower(trim($host)) : $this->canonicalize($this->getCurrentHost());


        return $this->isDevelopmentHost($check);


    }





    private function isDevelopmentHost(string $host): bool


    {


        if ($host === 'localhost' || str_starts_with($host, '127.')) return true;


        if (str_starts_with($host, '10.') || str_starts_with($host, '192.168.')) return true;


        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[01])\./', $host)) return true;


        foreach (['.test', '.local', '.localhost', '.dev', '.example', '.invalid'] as $s) {


            if (str_ends_with($host, $s)) return true;


        }


        foreach (['staging.', 'stage.', 'dev.', 'qa.', 'uat.', 'test.', 'preview.', 'sandbox.'] as $p) {


            if (str_starts_with($host, $p)) return true;


        }


        if (preg_match('/-(staging|stage|dev|qa|uat|test|preview|sandbox)\./', $host)) return true;


        foreach (['.magento.cloud', '.magentocloud.com', '.ngrok.io', '.ngrok-free.app', '.loca.lt'] as $s) {


            if (str_ends_with($host, $s)) return true;


        }


        return false;


    }





    /**


     * Returns true ONLY within the 48-hour grace window after local purchase.


     * After that, falls through to portal validation so cancellations take effect.


     */


    private function isLocallyIssuedKey(string $key, string $host): bool
    {
        $issuedKey = trim((string) $this->scopeConfig->getValue('storeportal/license/issued_key'));
        if ($issuedKey === '' || !hash_equals($issuedKey, $key)) {
            return false;
        }
        $issuedDomain = trim((string) $this->scopeConfig->getValue('storeportal/license/issued_domain'));
        if ($issuedDomain === '' || $this->canonicalize($issuedDomain) !== $this->canonicalize($host)) {
            return false;
        }
        $sessionId = trim((string) $this->scopeConfig->getValue('storeportal/license/stripe_session'));
        if ($sessionId === '') {
            return false;
        }
        // issued_at is set ONCE by Callback.php at purchase time — never recreated on cache flush
        $issuedAt = (int) $this->scopeConfig->getValue('storeportal/license/issued_at');
        if ($issuedAt === 0) {
            return false;
        }
        return (time() - $issuedAt) < 172800; // 48-hour grace from purchase
    }




    private function isExplicitlyRevoked(): bool

    {

        return (string) $this->scopeConfig->getValue(

            'storeportal_embed/license/revoked',

            ScopeInterface::SCOPE_STORE

        ) === '1';

    }

}