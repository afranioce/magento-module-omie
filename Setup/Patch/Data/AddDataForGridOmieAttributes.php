<?php

declare(strict_types=1);

namespace Omie\Integration\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Config;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Omie\Integration\Model\Omie;

final class AddDataForGridOmieAttributes implements DataPatchInterface, PatchVersionInterface
{
    private array $entities = [
        Customer::ENTITY,
        Product::ENTITY,
    ];

    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function apply()
    {
        foreach ($this->entities as $entity) {
            $customerOmieIdAttribute = $this->config->getAttribute($entity, Omie::OMIE_ID);
            $customerOmieIdAttribute->addData([
                'sort_order' => 10,
                'is_used_in_grid' => 1,
                'is_visible_in_grid' => 0,
                'is_filterable_in_grid' => 1,
                'is_searchable_in_grid' => 1,
            ]);
            $customerOmieIdAttribute->save();

            $customerOmieSyncAtAttribute = $this->config->getAttribute($entity, Omie::OMIE_SYNC_AT);
            $customerOmieSyncAtAttribute->addData([
                'sort_order' => 10,
                'is_used_in_grid' => 1,
                'is_visible_in_grid' => 0,
                'is_filterable_in_grid' => 1,
                'is_searchable_in_grid' => 1,
            ]);
            $customerOmieSyncAtAttribute->save();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [
            CreateOmieAttributes::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    public static function getVersion()
    {
        return '1.0.1';
    }
}
