<?php

declare(strict_types=1);

namespace Omie\Integration\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Omie\Integration\Model\Omie;

final class CreatProductAttributes implements DataPatchInterface, PatchVersionInterface, PatchRevertableInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;

    private EavSetupFactory $eavSetupFactory;

    private AttributeSetFactory $attributeSetFactory;

    private Config $config;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        EavSetupFactory $eavSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        Config $config
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->config = $config;
    }

    public function apply()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->addAttribute(Category::ENTITY, Omie::OMIE_ID, [
            'type' => 'varchar',
            'label' => 'Omie Id',
            'input' => 'text',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => false,
            'required' => false,
            'user_defined' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'unique' => false,
            'system' => false,
            'source' => '',
        ]);

        $eavSetup->addAttribute(Category::ENTITY, Omie::OMIE_SYNC_AT, [
            'type' => 'datetime',
            'label' => 'Omie last sync date',
            'input' => 'date',
            'frontend' => \Magento\Eav\Model\Entity\Attribute\Frontend\Datetime::class,
            'backend' => \Magento\Eav\Model\Entity\Attribute\Backend\Datetime::class,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => false,
            'required' => false,
            'user_defined' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'unique' => false,
            'system' => false,
            'source' => '',
            'input_filter' => 'date',
            'validate_rules' => '{"input_validation":"date"}',
        ]);

        $eavSetup->addAttribute(Product::ENTITY, 'is_featured', [
            'group' => 'General',
            'type' => 'int',
            'backend' => '',
            'frontend' => '',
            'label' => 'Is Featured',
            'input' => 'boolean',
            'class' => '',
            'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'default' => '1',
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'apply_to' => ''
        ]);

        $eavSetup->addAttribute(Product::ENTITY, 'brand', [
            'group' => 'General',
            'type' => 'varchar',
            'backend' => '',
            'frontend' => '',
            'label' => 'Brand',
            'input' => 'text',
            'class' => '',
            'source' => '',
            'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'visible' => true,
            'required' => false,
            'user_defined' => false,
            'searchable' => true,
            'filterable' => true,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'apply_to' => ''
        ]);

        $categoryAttributeSetId = $this->config->getEntityType(Category::ENTITY)->getDefaultAttributeSetId();
        $categoryAttributeGroupId = $this->attributeSetFactory->create()->getDefaultGroupId($categoryAttributeSetId);

        $categoryAttributesData = [
            'attribute_set_id' => $categoryAttributeSetId,
            'attribute_group_id' => $categoryAttributeGroupId,
            'is_used_in_grid' => 1,
            'is_visible_in_grid' => 0,
            'is_filterable_in_grid' => 1,
            'is_searchable_in_grid' => 1,
        ];

        $customerOmieIdAttribute = $this->config->getAttribute(Category::ENTITY, Omie::OMIE_ID);
        $customerOmieIdAttribute->addData($categoryAttributesData);
        $customerOmieIdAttribute->save();

        $customerOmieSyncAtAttribute = $this->config->getAttribute(Category::ENTITY, Omie::OMIE_SYNC_AT);
        $customerOmieSyncAtAttribute->addData($categoryAttributesData);
        $customerOmieSyncAtAttribute->save();

        $productAttributeSetId = $this->config->getEntityType(Product::ENTITY)->getDefaultAttributeSetId();
        $productAttributeGroupId = $this->attributeSetFactory->create()->getDefaultGroupId($productAttributeSetId);

        $productAttributesData = [
            'attribute_set_id' => $productAttributeSetId,
            'attribute_group_id' => $productAttributeGroupId,
            'is_used_in_grid' => 1,
            'is_visible_in_grid' => 0,
            'is_filterable_in_grid' => 1,
            'is_searchable_in_grid' => 1,
        ];

        $isFeaturedAttribute = $this->config->getAttribute(Product::ENTITY, 'is_featured');
        $isFeaturedAttribute->addData($productAttributesData);
        $isFeaturedAttribute->save();

        $brandAttribute = $this->config->getAttribute(Product::ENTITY, 'brand');
        $brandAttribute->addData($productAttributesData);
        $brandAttribute->save();
    }

    public function revert()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $eavSetup->removeAttribute(Category::ENTITY, Omie::OMIE_ID);
        $eavSetup->removeAttribute(Category::ENTITY, Omie::OMIE_SYNC_AT);
        $eavSetup->removeAttribute(Product::ENTITY, 'is_featured');
        $eavSetup->removeAttribute(Product::ENTITY, 'brand');
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
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
        return '1.0.3';
    }
}
