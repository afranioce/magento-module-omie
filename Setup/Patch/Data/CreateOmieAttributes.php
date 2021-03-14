<?php

declare(strict_types=1);

namespace Omie\Integration\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Config;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Setup\Patch\PatchVersionInterface;
use Omie\Integration\Model\Omie;

final class CreateOmieAttributes implements DataPatchInterface, PatchRevertableInterface, PatchVersionInterface
{
    private array $entities = [
        Customer::ENTITY,
        Product::ENTITY,
    ];

    private array $entityUsedInForms = [
        Customer::ENTITY => [
            'adminhtml_customer',
            'checkout_register',
            'customer_account_create',
            'customer_account_edit',
            'adminhtml_checkout'
        ],
        Product::ENTITY => []
    ];

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

        foreach ($this->entities as $entity) {
            $eavSetup->addAttribute($entity, Omie::OMIE_ID, [
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

            $eavSetup->addAttribute($entity, Omie::OMIE_SYNC_AT, [
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

            $customerEntity = $this->config->getEntityType($entity);
            $attributeSetId = $customerEntity->getDefaultAttributeSetId();
            $attributeSet = $this->attributeSetFactory->create();
            $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

            $customerOmieIdAttribute = $this->config->getAttribute($entity, Omie::OMIE_ID);
            $customerOmieIdAttribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => $this->entityUsedInForms[$entity],
            ]);
            $customerOmieIdAttribute->save();

            $customerOmieSyncAtAttribute = $this->config->getAttribute($entity, Omie::OMIE_SYNC_AT);
            $customerOmieSyncAtAttribute->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => $this->entityUsedInForms[$entity],
            ]);
            $customerOmieSyncAtAttribute->save();
        }
    }

    public function revert()
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach ($this->entities as $entity) {
            $eavSetup->removeAttribute($entity, Omie::OMIE_ID);
            $eavSetup->removeAttribute($entity, Omie::OMIE_SYNC_AT);
        }
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
        return '1.0.0';
    }
}
