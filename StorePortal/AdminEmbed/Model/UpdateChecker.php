<?php
declare(strict_types=1);
namespace StorePortal\AdminEmbed\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\CurlFactory;

class UpdateChecker
{
    private const PACKAGE     = 'storeportal/magento2-adminembed';
    private const LATEST_URL  = 'https://license-service.etechflow.com/composer/latest/storeportal/magento2-adminembed.json';
    private const CACHE_KEY   = 'storeportal_adminembed_update_check';
    private const CACHE_TTL   = 21600;
    private const MODULE_NAME = 'StorePortal_AdminEmbed';

    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly CacheInterface $cache,
        private readonly ResourceConnection $resource
    ) {}

    public function getAvailableUpdate(): ?array
    {
        try {
            $latest = $this->fetchLatest();
            if (empty($latest['version'])) return null;
            $installed = $this->installedVersion();
            if ($installed === '' || version_compare($installed, $latest['version'], '>=')) return null;
            return ['installed' => $installed, 'latest' => $latest['version'],
                    'notes' => $latest['notes'], 'package' => self::PACKAGE];
        } catch (\Throwable $e) { return null; }
    }

    public function getUpdateCommand(): string { return 'composer update ' . self::PACKAGE; }

    private function fetchLatest(): array
    {
        $raw = $this->cache->load(self::CACHE_KEY);
        if (!$raw) {
            $raw = '{}';
            try {
                $curl = $this->curlFactory->create(); $curl->setTimeout(5);
                $curl->get(self::LATEST_URL);
                if ((int)$curl->getStatus() === 200) $raw = (string)$curl->getBody();
            } catch (\Throwable $e) { $raw = '{}'; }
            $this->cache->save($raw, self::CACHE_KEY, [], self::CACHE_TTL);
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['latest_version'])) return ['version' => '', 'notes' => ''];
        return ['version' => (string)$data['latest_version'], 'notes' => (string)($data['release_notes'] ?? '')];
    }

    private function installedVersion(): string
    {
        // This package keeps its composer.json at the PACKAGE root (the module code
        // lives in a StorePortal/<Module> subdir), so walk up from the module dir to
        // the composer.json whose name matches this package. Falls back to Composer
        // InstalledVersions, then setup_module schema_version.
        try {
            $registrar = new \Magento\Framework\Component\ComponentRegistrar();
            $path = $registrar->getPath(\Magento\Framework\Component\ComponentRegistrar::MODULE, self::MODULE_NAME);
            if ($path) {
                foreach ([$path.'/composer.json', dirname($path).'/composer.json', dirname(dirname($path)).'/composer.json'] as $cf) {
                    if (is_file($cf)) {
                        $data = json_decode((string)file_get_contents($cf), true);
                        if (is_array($data) && ($data['name'] ?? '') === self::PACKAGE && !empty($data['version'])) {
                            return ltrim((string)$data['version'], 'v');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}
        try {
            if (class_exists('\Composer\InstalledVersions') && \Composer\InstalledVersions::isInstalled(self::PACKAGE)) {
                $v = \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE);
                if (is_string($v) && $v !== '') return ltrim($v, 'v');
            }
        } catch (\Throwable $e) {}
        try {
            $v = $this->resource->getConnection()->fetchOne(
                'SELECT schema_version FROM ' . $this->resource->getTableName('setup_module') . ' WHERE module = ?',
                [self::MODULE_NAME]);
            return $v ? (string)$v : '';
        } catch (\Throwable $e) { return ''; }
    }
}
