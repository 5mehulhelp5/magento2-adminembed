<?php
namespace StorePortal\AdminEmbed\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use StorePortal\AdminEmbed\Helper\Token;
use StorePortal\AdminEmbed\Model\LicenseValidator;

class Index extends Action
{
    const ADMIN_RESOURCE = 'StorePortal_AdminEmbed::manage';

    protected $resultPageFactory;
    protected $tokenHelper;
    protected $licenseValidator;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Token $tokenHelper,
        LicenseValidator $licenseValidator
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->tokenHelper       = $tokenHelper;
        $this->licenseValidator  = $licenseValidator;
    }

    public function execute()
    {
        // License gate — unlicensed stores get the plan/checkout page.
        if (!$this->licenseValidator->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('storeportal_embed/license/index');
        }

        // Auto-connect this Magento store to the portal on every admin page load.
        // The portal endpoint is idempotent (upserts the store record) and the call
        // is skipped automatically when mg_username/mg_password are not configured.
        $this->tokenHelper->autoConnect();

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Store Portal'));
        return $resultPage;
    }
}
