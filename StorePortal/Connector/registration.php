<?php
/**
 * Store Portal — Magento 2 Webhook Connector
 * Fires HTTP POST to the portal on product save/delete events.
 *
 * Install:
 *   1. Copy StorePortal/ into <magento_root>/app/code/
 *   2. Run: php bin/magento module:enable StorePortal_Connector
 *   3. Run: php bin/magento setup:upgrade
 *   4. Run: php bin/magento cache:flush
 *   5. Set PORTAL_URL in Observer/ProductSave.php and Observer/ProductDelete.php
 */
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'StorePortal_Connector',
    __DIR__
);
