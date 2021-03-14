<?php

declare(strict_types=1);

namespace Omie\Integration\Model\Adminhtml\Source;

use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Data\OptionSourceInterface;
use Omie\Sdk\Entity\General\Category\Category as SdkCategory;
use Omie\Sdk\Service\General\CategoryServiceInterface;

final class Category implements OptionSourceInterface
{
    private CategoryServiceInterface $categoryService;

    public function __construct(CategoryServiceInterface $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function toOptionArray()
    {
        try {
            $result = $this->categoryService->getList();
        } catch (GuzzleException $e) {
            return [];
        }

        return array_map(fn (SdkCategory $category) => [
            'value' => $category->getId(),
            'label' => $category->getName(),
        ], $result->getResults());
    }
}
