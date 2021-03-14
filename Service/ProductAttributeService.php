<?php

declare(strict_types=1);

namespace Omie\Integration\Service;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Api\AttributeOptionManagementInterface;
use Magento\Eav\Api\Data\AttributeOptionInterfaceFactory;
use Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory;
use Magento\Eav\Model\Entity\Attribute\OptionLabel;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Model\Entity\Attribute\Source\TableFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\StateException;
use Psr\Log\LoggerInterface;
use Throwable;

class ProductAttributeService implements ProductAttributeServiceInterface
{
    protected ProductAttributeRepositoryInterface $attributeRepository;

    protected array $attributeValues;

    protected TableFactory $tableFactory;

    protected AttributeOptionManagementInterface $attributeOptionManagement;

    protected AttributeOptionLabelInterfaceFactory $optionLabelFactory;

    protected AttributeOptionInterfaceFactory $optionFactory;

    private LoggerInterface $logger;

    public function __construct(
        ProductAttributeRepositoryInterface $attributeRepository,
        TableFactory $tableFactory,
        AttributeOptionManagementInterface $attributeOptionManagement,
        AttributeOptionLabelInterfaceFactory $optionLabelFactory,
        AttributeOptionInterfaceFactory $optionFactory,
        LoggerInterface $logger
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->tableFactory = $tableFactory;
        $this->attributeOptionManagement = $attributeOptionManagement;
        $this->optionLabelFactory = $optionLabelFactory;
        $this->optionFactory = $optionFactory;
        $this->logger = $logger;
    }

    public function getAttribute(string $attributeCode): ProductAttributeInterface
    {
        return $this->attributeRepository->get($attributeCode);
    }

    public function getOptionId(string $attributeCode, string $label, bool $refresh = false): ?int
    {
        /** @var Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        if ($refresh === true || !isset($this->attributeValues[$attribute->getAttributeId()])) {
            $this->attributeValues[$attribute->getAttributeId()] = [];

            /** @var Table $sourceModel */
            $sourceModel = $this->tableFactory->create();
            $sourceModel->setAttribute($attribute);

            foreach ($sourceModel->getAllOptions() as $option) {
                $this->attributeValues[$attribute->getAttributeId()][$option['label']] = (int) $option['value'];
            }
        }

        if (isset($this->attributeValues[$attribute->getAttributeId()][$label])) {
            return $this->attributeValues[$attribute->getAttributeId()][$label];
        }

        return null;
    }

    /**
     * @throws InputException
     * @throws StateException
     */
    public function createOptionId(string $attributeCode, string $label): void
    {
        $this->logger->info(
            sprintf(
                'Save product attribute code "%s" and label "%s"',
                $label,
                $attributeCode
            )
        );

        /** @var OptionLabel $optionLabel */
        $optionLabel = $this->optionLabelFactory->create();
        $optionLabel->setStoreId(0);
        $optionLabel->setLabel($label);

        $option = $this->optionFactory->create();
        $option->setLabel($label);
        $option->setStoreLabels([$optionLabel]);
        $option->setSortOrder(0);
        $option->setIsDefault(false);

        $attribute = $this->getAttribute($attributeCode);

        try {
            $this->attributeOptionManagement->add(
                Product::ENTITY,
                $attribute->getAttributeId(),
                $option
            );
        } catch (Throwable $throwable) {
            $this->logger->error(
                sprintf(
                    'Save product attribute code "%s" and label "%s" error: %s',
                    $label,
                    $attributeCode,
                    $throwable->getMessage()
                )
            );
        }
    }

    public function getOrCreateOptionId(string $attributeCode, string $label): ?int
    {
        $colorOptionId = $this->getOptionId($attributeCode, $label);

        if ($colorOptionId !== null) {
            return $colorOptionId;
        }

        $this->createOptionId($attributeCode, $label);

        return $this->getOptionId($attributeCode, $label, true);
    }
}
