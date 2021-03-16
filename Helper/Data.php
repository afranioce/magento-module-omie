<?php

declare(strict_types=1);

namespace Omie\Integration\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Omie\Sdk\Communication\AccessTokenInterface;
use Omie\Sdk\Communication\UrlInterface;

class Data extends AbstractHelper implements AccessTokenInterface, UrlInterface
{
    private const XML_PATH_SECTION_OMIE = 'omie';
    private const XML_PATH_SECTION_PAYMENT = 'payment';

    private const XML_PATH_GROUP_GENERAL = 'general';
    private const XML_PATH_GROUP_CLIENT = 'client';

    private const XML_PATH_GROUP_OMIE_BILLET = 'omie_billet';

//    private const XML_PATHS = [
//        self::XML_PATH_SECTION_OMIE => [
//            self::XML_PATH_GROUP_GENERAL,
//            self::XML_PATH_GROUP_CLIENT,
//        ],
//        self::XML_PATH_SECTION_PAYMENT => [
//            self::XML_PATH_GROUP_OMIE_BILLET,
//        ]
//    ];

    /**
     * @param int|string|null $storeId
     */
    public function getConfigValue(string $field, $storeId = null)
    {
        return $this->scopeConfig->getValue($field, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getPaymentBilletInstructions(): string
    {
        return $this->scopeConfig->getValue('payment/omie_billet/instructions', ScopeInterface::SCOPE_STORE, null);
    }

    /**
     * @param int|string|null $storeId
     */
    public function getGeneralConfig(string $code, $storeId = null)
    {
        return $this->getConfig(self::XML_PATH_GROUP_GENERAL, $code, $storeId);
    }

    /**
     * @param int|string|null $storeId
     */
    public function getClientConfig(string $code, $storeId = null)
    {
        return $this->getConfig(self::XML_PATH_GROUP_CLIENT, $code, $storeId);
    }

    /**
     * @param int|string|null $storeId
     */
    public function getPaymentConfig(string $code, $storeId = null)
    {
        return $this->getConfigValue(
            sprintf(
                '%s/%s/%s',
                self::XML_PATH_SECTION_PAYMENT,
                self::XML_PATH_GROUP_OMIE_BILLET,
                $code
            ),
            $storeId
        );
    }

    /**
     * @param int|string|null $storeId
     */
    private function getConfig(string $group, string $code, $storeId = null)
    {
        return $this->getConfigValue(
            sprintf(
                '%s/%s/%s',
                self::XML_PATH_SECTION_OMIE,
                $group,
                $code
            ),
            $storeId
        );
    }

    /**
     * TODO Usar slugify de url do magento ou criar um helper
     */
    public function slugify(string $str, string $delimiter = '-'): string
    {
        setlocale(LC_ALL, 'en_US.UTF8');

        $slugify = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $slugify = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $slugify);
        $slugify = strtolower(trim($slugify, '-'));
        $slugify = preg_replace("/[\/_|+ -]+/", $delimiter, $slugify);

        return $slugify;
    }

    public function getAppKey(): string
    {
        return $this->scopeConfig->getValue('payment/omie_billet/app_key', ScopeInterface::SCOPE_STORE, null);
    }

    public function getAppSecret(): string
    {
        return $this->scopeConfig->getValue('payment/omie_billet/app_secret', ScopeInterface::SCOPE_STORE, null);
    }

    public function getBaseUri(): string
    {
        return $this->scopeConfig->getValue('payment/omie_billet/base_url', ScopeInterface::SCOPE_STORE, null);
    }
}
