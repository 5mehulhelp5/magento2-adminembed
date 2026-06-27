<?php

declare(strict_types=1);

namespace StorePortal\AdminEmbed\Controller\Adminhtml\License;

use StorePortal\AdminEmbed\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'StorePortal_AdminEmbed::config';

    public function __construct(
        Context $context,
        private readonly LicenseValidator $licenseValidator,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if ($this->licenseValidator->isValid()) {
            $this->messageManager->addSuccessMessage(
                (string) __('Your Store Portal Embed license is active.')
            );
            return $this->resultRedirectFactory->create()->setPath('storeportal_embed/index/index');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend((string) __('Store Portal — License Required'));
        return $resultPage;
    }
}