<?php
declare(strict_types=1);

namespace StorePortal\AdminEmbed\Controller\Adminhtml\License;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Success extends Action
{
    public const ADMIN_RESOURCE = 'StorePortal_AdminEmbed::config';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $page = $this->resultPageFactory->create();
        $page->getConfig()->getTitle()->prepend('License Activated — Store Portal');
        return $page;
    }
}