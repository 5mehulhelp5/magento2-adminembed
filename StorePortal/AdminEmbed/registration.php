<?php
/**
 * StorePortal_AdminEmbed — embeds the Store Portal dashboard inside Magento Admin.
 *
 * Installation via Composer:
 *   composer require storeportal/magento2-adminembed
 *   php bin/magento module:enable StorePortal_AdminEmbed
 *   php bin/magento setup:upgrade
 *   php bin/magento cache:flush
 *
 * Configuration:
 *   Edit Helper/Token.php and set PORTAL_URL and API_KEY to match your .env values.
 */

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'StorePortal_AdminEmbed',
    __DIR__
);
