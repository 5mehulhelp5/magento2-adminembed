<?php
declare(strict_types=1);
namespace StorePortal\AdminEmbed\Observer;

use StorePortal\AdminEmbed\Model\UpdateChecker;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\App\ResourceConnection;

class AddUpdateNotification implements ObserverInterface
{
    public function __construct(
        private readonly UpdateChecker $updateChecker,
        private readonly NotifierInterface $notifier,
        private readonly ResourceConnection $resource
    ) {}

    public function execute(Observer $observer): void
    {
        try {
            $update = $this->updateChecker->getAvailableUpdate();
            if ($update === null) return;
            $title = (string)__('Store Portal %1 is available', $update['latest']);
            $conn  = $this->resource->getConnection();
            $table = $this->resource->getTableName('adminnotification_inbox');
            if ((int)$conn->fetchOne("SELECT COUNT(*) FROM {$table} WHERE title = ? AND is_remove = 0", [$title]) > 0) return;
            $desc = !empty($update['notes']) ? $update['notes']
                : (string)__('Update available: %1 to %2. Run: %3', $update['installed'], $update['latest'], $this->updateChecker->getUpdateCommand());
            $this->notifier->addNotice($title, $desc);
        } catch (\Throwable $e) {}
    }
}
