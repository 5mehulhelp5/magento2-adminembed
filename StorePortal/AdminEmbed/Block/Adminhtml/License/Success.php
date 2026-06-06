<?php
declare(strict_types=1);

namespace StorePortal\AdminEmbed\Block\Adminhtml\License;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Success extends Template
{
    public function __construct(
        Context $context,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getIssuedKey(): string    { return trim((string) $this->scopeConfig->getValue('storeportal/license/issued_key')); }
    public function getIssuedPlan(): string   { return trim((string) $this->scopeConfig->getValue('storeportal/license/issued_plan')); }
    public function getIssuedDomain(): string { return trim((string) $this->scopeConfig->getValue('storeportal/license/issued_domain')); }

    public function getPlanLabel(): string
    {
        $map = ['solo'=>'Solo','growth'=>'Growth','business'=>'Business','all-channels'=>'All-Channels','enterprise'=>'Enterprise'];
        return $map[strtolower($this->getIssuedPlan())] ?? ucfirst($this->getIssuedPlan());
    }

    public function getConfigUrl(): string { return (string) $this->getUrl('adminhtml/system_config/edit', ['section'=>'storeportal']); }
    public function getGateUrl(): string   { return (string) $this->getUrl('storeportal_embed/license/index'); }
}