<?php

declare(strict_types=1);

namespace Omie\Integration\Service\Sync;

use DateTime;
use GuzzleHttp\Exception\ServerException;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Store\Model\StoreManagerInterface;
use Omie\Integration\Helper\Data;
use Omie\Integration\Helper\GeneralCodes;
use Omie\Integration\Model\Omie;
use Omie\Integration\Service\ProductAttributeServiceInterface;
use Omie\Sdk\Entity\General\Characteristic\Characteristic;
use Omie\Sdk\Entity\General\Product\Family as SdkFamily;
use Omie\Sdk\Entity\General\Product\Product as SdkProduct;
use Omie\Sdk\Service\General\ProductServiceInterface as SdkProductServiceInterface;
use Psr\Log\LoggerInterface;

final class ProductService implements ProductServiceInterface
{
    private SdkProductServiceInterface $productService;

    private ProductCollectionFactory $productCollectionFactory;

    private CategoryCollectionFactory $categoryCollectionFactory;

    private StockItemRepository $stockItemRepository;

    private ProductAttributeServiceInterface $productAttributeService;

    private StoreManagerInterface $storeManager;

    private LoggerInterface $logger;

    private Data $helperData;

    public function __construct(
        SdkProductServiceInterface $productService,
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        StockItemRepository $stockItemRepository,
        StoreManagerInterface $storeManager,
        ProductAttributeServiceInterface $productAttributeService,
        Data $helperData,
        LoggerInterface $logger
    ) {
        $this->productService = $productService;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->stockItemRepository = $stockItemRepository;
        $this->productAttributeService = $productAttributeService;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->helperData = $helperData;
    }

    public function massImport(): void
    {
        $page = 1;
        $filter = [];

        if (!((bool) $this->helperData->getGeneralConfig(GeneralCodes::FORCE_IMPORT))) {
            $lateSyncFromLast = $this->getDateSyncFromLastProduct();

            if ($lateSyncFromLast) {
                $lateSyncFromLast->modify('+1 second');

                $filter['filtrar_por_data_de'] = $lateSyncFromLast->format('d/m/Y');
                $filter['filtrar_por_hora_de'] = $lateSyncFromLast->format('H:i:s');
            }
        }

        do {
            try {
                $result = $this->productService->getList($page, 50, $filter);
            } catch (ServerException $serverException) {
                break;
            }

            array_map(function (SdkProduct $sdkProduct) {
                /** @var Product $product */
                $product = $this->productCollectionFactory->create()
                    ->addFieldToFilter(Omie::OMIE_ID, ['eq' => $sdkProduct->getId()])
                    ->getFirstItem();

                $data = $this->convertProduct($sdkProduct);
                $data['attribute_set_id'] = $product->getDefaultAttributeSetId();
                $product->addData($data);

                try {
                    $product->save();
                    $this->updateStock($product, $sdkProduct->getStock());

                    $this->logger->info(
                        sprintf(
                            'Omie import product %d successful with data: %s',
                            $sdkProduct->getId(),
                            json_encode($data)
                        )
                    );
                } catch (\Exception $e) {
                    $this->logger->critical(
                        sprintf(
                            'Omie import product %d, error: %s',
                            $sdkProduct->getId(),
                            $e->getMessage()
                        )
                    );
                }
            }, $result->getResults());

            $page = $result->getPage() + 1;
        } while ($result->getPage() < $result->getTotalPages());
    }

    private function convertProduct(SdkProduct $sdkProduct): array
    {
        $category = $this->getOrCreateCategory($sdkProduct->getFamily());

        $store = $this->storeManager->getDefaultStoreView();

        /** @var ?Characteristic $isFeatured */
        $isFeatured = $sdkProduct->getCharacteristics()
            ->find(fn (Characteristic $characteristic) => strtolower($characteristic->getName()) === 'destaque'
                && strtolower($characteristic->getValue()) === 'sim');

        return [
            'omie_id' => $sdkProduct->getId(),
            'type_id' => Type::TYPE_SIMPLE,
            'category_ids' => [$store->getRootCategoryId(), $category->getId()],
            'store_ids' => [$store->getId()],
            'website_ids' => [$store->getWebsiteId()],
            'visibility' => Visibility::VISIBILITY_BOTH,
            'omie_sync_at' => $sdkProduct->getUpdatedAt()->format(DateTime::ATOM),
            'sku' => $sdkProduct->getSku(),
            'name' => $sdkProduct->getName(),
            // TODO desabilita temporariamente a importação de descrição, no omie é limitado a texto a formatação
//            'description' => html_entity_decode(nl2br($sdkProduct->getDescription())),
            'price' => $sdkProduct->getPrice(),
            'weight' => $sdkProduct->getWeight(),
            'status' => $sdkProduct->isActive() ? Status::STATUS_ENABLED : Status::STATUS_DISABLED,
            'is_featured' => $isFeatured ? '1' : '0',
            'color' => $this->getColorOptionId($sdkProduct),
            'brand' => $sdkProduct->getBrand(),
            'url_key' => $sdkProduct->getName() . '-' . $sdkProduct->getSku()
        ];
    }

    private function updateStock(Product $item, int $quantity): void
    {
        $stockItem = $this->stockItemRepository->get($item->getId());

        $stockItem->setQty($quantity);
        $stockItem->setIsInStock(true);

        $this->stockItemRepository->save($stockItem);
    }

    private function getDateSyncFromLastProduct(): ?DateTime
    {
        $lastProductSync = $this->productCollectionFactory->create()
            ->addAttributeToSort(Omie::OMIE_SYNC_AT)
            ->getLastItem();

        if (!$lastProductSync) {
            return null;
        }

        $omieSyncAt = $lastProductSync->getData(Omie::OMIE_SYNC_AT);

        if (!$omieSyncAt) {
            return null;
        }

        return new DateTime($omieSyncAt);
    }

    private function getOrCreateCategory(SdkFamily $family): Category
    {

        /** @var Category $category */
        $category = $this->categoryCollectionFactory->create()
            ->addFieldToFilter(Omie::OMIE_ID, ['eq' => $family->getId()])
            ->getFirstItem();

        if ($category->isObjectNew()) {
            $name = empty($family->getName()) ? 'Sem Categoria' : $family->getName();
            $store = $this->storeManager->getDefaultStoreView();

            /** @var Category $rootCategory */
            $rootCategory = $this->categoryCollectionFactory->create()
                ->addIdFilter([$store->getRootCategoryId()])
                ->getFirstItem();

            $category->setOmieId($family->getId());
            $category->setOmieSyncAt((new DateTime())->format(DateTime::ATOM));
            $category->setStoreId($store->getId());
            $category->setPath($rootCategory->getPath());
            $category->setIsAnchor(true);
            $category->setIsActive(true);
            $category->setName($name);
            $category->setParentId($rootCategory->getId());
            $category->setUrlKey($this->helperData->slugify($family->getName()));

            $category->save();

            $this->logger->info(
                sprintf(
                    'Omie import family %d successful with category data: %s',
                    $family->getId(),
                    json_encode($category->getData())
                )
            );
        }

        return $category;
    }

    private function getColorOptionId(SdkProduct $sdkProduct): ?int
    {
        /** @var ?Characteristic $color */
        $color = $sdkProduct->getCharacteristics()
            ->find(fn (Characteristic $characteristic) => strtolower($characteristic->getName()) === 'cor');

        if (!$color) {
            return null;
        }

        return $this->productAttributeService->getOrCreateOptionId('color', $color->getValue());
    }
}
