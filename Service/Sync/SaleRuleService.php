<?php

declare(strict_types=1);

namespace Omie\Integration\Service\Sync;

use Magento\Customer\Model\GroupFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory;
use Magento\SalesRule\Model\Rule\Condition\Address;
use Magento\SalesRule\Model\Rule\Condition\Combine;
use Magento\SalesRule\Model\Rule\Condition\Product;
use Omie\Integration\Model\Omie;
use Omie\Sdk\Entity\Product\PriceTable;
use Omie\Sdk\Service\Product\PriceTableServiceInterface;

final class SaleRuleService implements SaleRuleServiceInterface
{
    private const COUNTRY_BRAZIL_ID = 'BR';

    private PriceTableServiceInterface $priceTableService;

    private CollectionFactory $collectionFactory;

    private RegionFactory $regionFactory;

    private GroupFactory $groupFactory;

    public function __construct(
        PriceTableServiceInterface $priceTableService,
        CollectionFactory $collectionFactory,
        RegionFactory $regionFactory,
        GroupFactory $groupFactory
    ) {
        $this->priceTableService = $priceTableService;
        $this->collectionFactory = $collectionFactory;
        $this->regionFactory = $regionFactory;
        $this->groupFactory = $groupFactory;
    }

    public function massImport(): void
    {
        $page = 1;
        do {
            $result = $this->priceTableService->getList($page);

            array_map(function (PriceTable $priceTable) {
                $item = $this->collectionFactory->create()
                    ->addFieldToFilter(Omie::OMIE_ID, ['eq' => $priceTable->getId()])
                    ->getFirstItem();

                $data = $this->convertPriceTable($priceTable);
                $item->addData($data)->save();
            }, $result->getResults());

            $page = $result->getPage() + 1;
        } while ($result->getTotalPages() < $result->getPage());
    }

    private function convertPriceTable(PriceTable $priceTable): array
    {
        $data = [
            'omie_id' => $priceTable->getId(),
            'name' => $priceTable->getName(),
            'is_active' => $priceTable->isActive(),
            'website_ids' => '1',
            'from_date' => '',
            'to_date' => '',
            'customer_group_ids' => '',
            'actions_serialized' => json_encode([
                'type' => Product\Combine::class,
                'attribute' => null,
                'operator' => null,
                'value' => '1',
                'is_value_processed' => null,
                'aggregator' => 'all',
                'conditions' => [
                    [
                        'type' => Product::class,
                        'attribute' => 'attribute_set_id',
                        'operator' => '==',
                        'value' => '4',
                        'is_value_processed' => false,
                    ],
                ],
            ]),
            'coupon_type' => '1',
            'coupon_code' => '',
            'store_labels' => [
                0 => 'TestRule',
                1 => 'TestRuleForDefaultStore',
            ],
        ];

        if ($priceTable->getCharacteristics()->isHasValidate()) {
            $data['from_date'] = $priceTable->getCharacteristics()->getDateStart()
                ? $priceTable->getCharacteristics()->getDateStart()->format(DATE_ATOM)
                : null;
            $data['to_date'] = $priceTable->getCharacteristics()->getDateEnd()
                ? $priceTable->getCharacteristics()->getDateEnd()->format(DATE_ATOM)
                : null;
        }

        $conditions = [];

        if (!$priceTable->getProducts()->isAllProducts()) {
            // TODO incluir as condições do produto
            $conditions[] = [
                'type' => Address::class,
                'attribute' => 'base_subtotal',
                'operator' => '>=',
                'value' => '100',
                'is_value_processed' => false,
            ];
        }

        if (!$priceTable->getClients()->isAllClients()) {
            $regionCode = $priceTable->getClients()->getRegionCode();
            $customerGroupName = $priceTable->getClients()->getClientGroupName();

            if (!empty($regionCode)) {
                $region = $this->regionFactory->create()->loadByCode($regionCode, self::COUNTRY_BRAZIL_ID);
                $conditions[] = [
                    'type' => Address::class,
                    'attribute' => 'region_id',
                    'operator' => '==',
                    'value' => $region->getId(),
                    'is_value_processed' => false,
                ];
            }

            if (!$customerGroupName) {
                // TODO necessário importar "Tags de Clientes e Fornecedores"?
                $customer = $this->groupFactory->create()->load($customerGroupName, 'customer_group_code');
                if (!empty($customer)) {
                    $data['customer_group_ids'] = (string) $customer->getId();
                }
            }
        }

        $data['conditions_serialized'] = json_encode([
            'type' => Combine::class,
            'attribute' => null,
            'operator' => null,
            'value' => '1',
            'is_value_processed' => null,
            'aggregator' => 'all',
            'conditions' => $conditions,
        ]);

        return $data;
    }
}
