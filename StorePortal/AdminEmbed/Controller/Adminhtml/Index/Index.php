<?php
namespace StorePortal\AdminEmbed\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use StorePortal\AdminEmbed\Helper\Token;

class Index extends Action
{
    const ADMIN_RESOURCE = 'StorePortal_AdminEmbed::manage';

    protected $resultPageFactory;
    protected $tokenHelper;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        Token $tokenHelper
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->tokenHelper       = $tokenHelper;
    }

    public function execute()
    {
        // Auto-connect this Magento store to the portal on every admin page load.
        // The portal endpoint is idempotent (upserts the store record) and the call
        // is skipped automatically when mg_username/mg_password are not configured.
        $this->tokenHelper->autoConnect();

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Store Portal'));
        return $resultPage;
    }
}
