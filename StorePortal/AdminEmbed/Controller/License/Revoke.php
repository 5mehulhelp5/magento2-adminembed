<?php

declare(strict_types=1);



namespace StorePortal\AdminEmbed\Controller\License;



use Magento\Framework\App\Action\HttpPostActionInterface;

use Magento\Framework\App\CacheInterface;

use Magento\Framework\App\Cache\TypeListInterface;

use Magento\Framework\App\Config\ScopeConfigInterface;

use Magento\Framework\App\Config\Storage\WriterInterface;

use Magento\Framework\App\CsrfAwareActionInterface;

use Magento\Framework\App\Request\InvalidRequestException;

use Magento\Framework\App\RequestInterface;

use Magento\Framework\Controller\Result\JsonFactory;

use Magento\Framework\Controller\ResultInterface;



class Revoke implements HttpPostActionInterface, CsrfAwareActionInterface

{

    public function __construct(

        private readonly RequestInterface $request,

        private readonly ScopeConfigInterface $scopeConfig,

        private readonly WriterInterface $configWriter,

        private readonly TypeListInterface $cacheTypeList,

        private readonly CacheInterface $cache,

        private readonly JsonFactory $jsonFactory

    ) {

    }



    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException

    {

        return null;

    }



    public function validateForCsrf(RequestInterface $request): ?bool

    {

        return true;

    }



    public function execute(): ResultInterface

    {

        $result = $this->jsonFactory->create();



        $body        = json_decode((string) $this->request->getContent(), true) ?? [];

        $providedKey = trim((string) ($body['license_key'] ?? ''));



        if ($providedKey === '' || !preg_match('/^SP-[A-F0-9\-]+$/i', $providedKey)) {

            return $result->setHttpResponseCode(400)->setData(['error' => 'Invalid key format']);

        }



        $storedKey = trim((string) $this->scopeConfig->getValue('storeportal/license/issued_key'));

        if ($storedKey === '' || !hash_equals(strtoupper($storedKey), strtoupper($providedKey))) {

            return $result->setHttpResponseCode(403)->setData(['error' => 'Key mismatch']);

        }



        // Clear all locally issued license config values

        foreach ([

            'storeportal/license/license_key',

            'storeportal/license/issued_key',

            'storeportal/license/issued_domain',

            'storeportal/license/issued_plan',

            'storeportal/license/stripe_session',

            'storeportal/license/stripe_subscription',

            'storeportal/license/stripe_customer',

        ] as $path) {

            $this->configWriter->save($path, '');

        }



        // Set revoked flag — overrides even dev mode bypass in LicenseValidator::isValid()

        $this->configWriter->save('storeportal_embed/license/revoked', '1');

        $this->configWriter->save('storeportal/license/issued_at', '0');


        // Remove 48-hour grace cache so isLocallyIssuedKey() returns false immediately

        $this->cache->remove('etf_alm_local_grace_' . md5($storedKey));



        // Remove cached portal validation result so isValid() re-checks immediately

        $this->cache->remove('etf_alm_lic_' . md5($storedKey));



        $this->cacheTypeList->cleanType('config');



        return $result->setData(['success' => true]);

    }

}