<?php
namespace StorePortal\Connector\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class ProductDelete implements ObserverInterface
{
    /** Same portal URL as ProductSave — keep in sync */
    const PORTAL_URL = 'http://localhost:5000';

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        try {
            $product = $observer->getEvent()->getProduct();
            if (!$product || !$product->getSku()) {
                return;
            }

            $storeUrl = $this->storeManager->getStore()->getBaseUrl();
            $payload = json_encode([
                'sku'       => $product->getSku(),
                'store_url' => rtrim($storeUrl, '/'),
                'event'     => 'delete',
            ]);

            $this->sendWebhook(self::PORTAL_URL . '/webhooks/magento/product-delete', $payload);
        } catch (\Exception $e) {
            $this->logger->warning('StorePortal ProductDelete observer error: ' . $e->getMessage());
        }
    }

    private function sendWebhook(string $url, string $payload): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
