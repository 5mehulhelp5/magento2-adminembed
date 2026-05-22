<?php

declare(strict_types=1);

namespace StorePortal\AdminEmbed\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use StorePortal\AdminEmbed\Helper\Token;

class Index extends Action
{
    const ADMIN_RESOURCE = 'StorePortal_AdminEmbed::manage';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly Token $tokenHelper
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        // Auto-connect this store to the portal on every admin page load.
        $this->tokenHelper->autoConnect();

        // Validate license via portal API (dev/staging hosts always pass).
        $licenseValid  = $this->tokenHelper->isLicensed();
        $licenseResult = $this->tokenHelper->validateLicense();

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Store Portal'));

        // Pass license data to the layout block so the template can show
        // a notice when the key is missing or invalid.
        $block = $resultPage->getLayout()->getBlock('storeportal.embed');
        if ($block) {
            $block->setData('license_valid',   $licenseValid);
            $block->setData('license_plan',    $licenseResult['plan_name'] ?? ($licenseResult['plan'] ?? ''));
            $block->setData('license_message', $licenseResult['message'] ?? '');
            $block->setData('portal_settings_url',
                $this->_url->getUrl('adminhtml/system_config/edit', ['section' => 'storeportal'])
            );
        }

        return $resultPage;
    }
}
