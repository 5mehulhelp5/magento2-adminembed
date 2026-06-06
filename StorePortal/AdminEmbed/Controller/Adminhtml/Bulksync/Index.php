<?php
namespace StorePortal\AdminEmbed\Controller\Adminhtml\Bulksync;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use StorePortal\AdminEmbed\Model\LicenseValidator;

class Index extends Action
{
    const ADMIN_RESOURCE = 'StorePortal_AdminEmbed::manage';

    protected $resultPageFactory;
    protected $licenseValidator;

    public function __construct(Context $context, PageFactory $resultPageFactory, LicenseValidator $licenseValidator)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->licenseValidator  = $licenseValidator;
    }

    public function execute()
    {
        if (!$this->licenseValidator->isValid()) {
            return $this->resultRedirectFactory->create()->setPath('storeportal_embed/license/index');
        }
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Store Portal – Bulk Sync'));
        return $resultPage;
    }
}
