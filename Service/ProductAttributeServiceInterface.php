<?php

namespace Omie\Integration\Service;

use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\StateException;

interface ProductAttributeServiceInterface
{
    public function getAttribute(string $attributeCode): ProductAttributeInterface;

    public function getOptionId(string $attributeCode, string $label): ?int;

    /**
     * @throws InputException
     * @throws StateException
     */
    public function createOptionId(string $attributeCode, string $label): void;

    public function getOrCreateOptionId(string $attributeCode, string $label): ?int;
}
