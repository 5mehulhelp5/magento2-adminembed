<?php
declare(strict_types=1);
namespace StorePortal\AdminEmbed\Block\Adminhtml;

use StorePortal\AdminEmbed\Model\UpdateChecker;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class UpdateNotice extends Template
{
    public function __construct(Context $context, private readonly UpdateChecker $updateChecker, array $data = [])
    { parent::__construct($context, $data); }

    public function getUpdateInfo(): ?array { return $this->updateChecker->getAvailableUpdate(); }
    public function getUpdateCommand(): string { return $this->updateChecker->getUpdateCommand(); }
}
