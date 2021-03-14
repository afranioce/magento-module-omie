<?php

declare(strict_types=1);

namespace Omie\Integration\Service\Sync;

use DateTime;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AddressFactory;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\State\InputMismatchException;
use Magento\Store\Model\StoreManagerInterface;
use Omie\Integration\Helper\ClientCodes;
use Omie\Integration\Helper\Data;
use Omie\Integration\Helper\GeneralCodes;
use Omie\Integration\Model\Omie;
use Omie\Sdk\Entity\General\Client\Address as SdkAddress;
use Omie\Sdk\Entity\General\Client\Client;
use Omie\Sdk\Entity\General\Client\Phone;
use Omie\Sdk\Entity\General\Client\Tag;
use Omie\Sdk\Service\General\ClientServiceInterface;
use Psr\Log\LoggerInterface;

final class CustomerService implements CustomerServiceInterface
{
    private ClientServiceInterface $clientService;

    private CollectionFactory $collectionFactory;

    private CustomerFactory $customerFactory;

    private StoreManagerInterface $storeManager;

    private AddressFactory $addressFactory;

    private RegionFactory $regionFactory;

    private Data $helperData;
    private LoggerInterface $logger;

    public function __construct(
        ClientServiceInterface $clientService,
        CollectionFactory $collectionFactory,
        CustomerFactory $customerFactory,
        StoreManagerInterface $storeManager,
        AddressFactory $addressFactory,
        RegionFactory $regionFactory,
        Data $helperData,
        LoggerInterface $logger
    ) {
        $this->clientService = $clientService;
        $this->collectionFactory = $collectionFactory;
        $this->customerFactory = $customerFactory;
        $this->storeManager = $storeManager;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;
        $this->helperData = $helperData;
        $this->logger = $logger;
    }

    /**
     * @throws LocalizedException
     */
    public function massImport(): void
    {
        $page = 1;
        $filter = [];

        if (!((bool)$this->helperData->getGeneralConfig(GeneralCodes::FORCE_IMPORT))) {
            $lateSyncFromLast = $this->getDateSyncFromLastCustomer();

            if ($lateSyncFromLast) {
                $lateSyncFromLast->modify('+1 second');

                $filter['filtrar_por_data_de'] = $lateSyncFromLast->format('d/m/Y');
                $filter['filtrar_por_hora_de'] = $lateSyncFromLast->format('H:i:s');
            }
        }

        do {
            try {
                $result = $this->clientService->getList($page, 50, $filter);
            } catch (ServerException $serverException) {
                break;
            }

            array_map(function (Client $client) {
                /** @var Customer $item */
                $item = $this->collectionFactory->create()
                    ->addAttributeToFilter(Omie::OMIE_ID, ['eq' => $client->getId()])
                    ->getFirstItem();

                $data = $this->transformClientForCustomerArray($client);
                $item->addData($data);

                try {
                    $item->save();
                    $this->saveAddress($item, $client);
                } catch (Exception $e) {
                    $this->logger->critical(
                        sprintf(
                            'Omie import client %d, error: %s',
                            $client->getId(),
                            $e->getMessage()
                        )
                    );
                }
            }, $result->getResults());

            $page = $result->getPage() + 1;
        } while ($result->getPage() < $result->getTotalPages());
    }

    /**
     * @throws GuzzleException
     * @throws InputException
     * @throws LocalizedException
     * @throws InputMismatchException
     */
    public function send(CustomerInterface $customer): void
    {
        $this->logger->info(sprintf('Omie send customer: %d', $customer->getId()));

        $client = $this->transformCustomerForClient($customer);

        $client->getId()
            ? $this->clientService->update($client)
            : $this->clientService->create($client);

        $this->logger->info(
            sprintf(
                'Omie sent customer: %d, OmieId: %d',
                $customer->getId(),
                $client->getId()
            )
        );

        $customerModel = $this->customerFactory->create()->load($customer->getId());

        $customerModel->setOmieId($client->getId());
        $customerModel->setOmieSyncAt((new DateTime())->format(DATE_ATOM));

        $customerModel->save();

        $this->logger->info(
            sprintf(
                'Omie updated customer: %d, OmieId: %d',
                $customer->getId(),
                $client->getId()
            )
        );
    }

    private function transformClientForCustomerArray(Client $client): array
    {
        $store = $this->storeManager->getDefaultStoreView();

        return [
            'omie_id' => $client->getId(),
            'omie_sync_at' => $client->getUpdatedAt()->format(DATE_ATOM),
            'cpf' => $client->isPerson() ? $client->getCnpjCpfMask() : null,
            'cnpj' => !$client->isPerson() ? $client->getCnpjCpfMask() : null,
            'socialname' => $client->getSocialname(),
            'store_id' => $store->getId(),
            'website_id' => $store->getWebsiteId(),
            'email' => $client->getEmail(),
            'firstname' => $client->getFirstname(),
            'lastname' => $client->getLastname(),
            'gender' => 0,
        ];
    }

    private function transformCustomerForClient(CustomerInterface $customer): Client
    {
        $addresses = $customer->getAddresses();

        $clientAddresses = [
            0 => null,
            1 => null,
        ];

        if ($addresses) {
            foreach ($addresses as $index => $address) {
                $region = $address->getRegion();
                $street = $address->getStreet();

                $clientAddresses[$index] = new SdkAddress(
                    isset($street[0]) ? $street[0] : '',
                    isset($street[1]) ? $street[1] : '',
                    $address->getPostcode() ? $address->getPostcode() : '',
                    SdkAddress::COUNTRY_BRAZIL_ID,
                    isset($street[3]) ? $street[3] : '',
                    isset($street[2]) ? $street[2] : '',
                    $region && $region->getRegionCode() ? $region->getRegionCode() : '',
                    $address->getCity() ? $address->getCity() : ''
                );
            }
        }

        $fullName = array_filter([$customer->getFirstname(), $customer->getMiddlename(), $customer->getLastname()]);

        $omieIdAttribute = $customer->getCustomAttribute(Omie::OMIE_ID);
        $cpf = $customer->getCustomAttribute('cpf');
        $cnpj = $customer->getCustomAttribute('cnpj');
        $socialname = $customer->getCustomAttribute('socialname');
        $tagConsumer = $this->helperData->getClientConfig(ClientCodes::TAG_CONSUMER);

        return new Client(
            $omieIdAttribute ? (int)$omieIdAttribute->getValue() : null,
            $this->helperData->getGeneralConfig(GeneralCodes::PREFIX_INTEGRATION) . $customer->getId(),
            implode(' ', $fullName),
            $socialname ? $socialname->getValue() : implode(' ', $fullName),
            $customer->getEmail(),
            true,
            [
                new Tag($tagConsumer)
            ],
            $cpf ? $cpf->getValue() : ($cnpj ? $cnpj->getValue() : ''),
            (bool)$cpf,
            $clientAddresses[0],
            $clientAddresses[1],
            [],
            new DateTimeImmutable(),
            '',
            new DateTimeImmutable(),
            ''
        );
    }

    private function getDateSyncFromLastCustomer(): ?DateTime
    {
        $lastCustomerSync = $this->collectionFactory->create()
            ->addAttributeToSort(Omie::OMIE_SYNC_AT)
            ->getLastItem();

        if (!$lastCustomerSync) {
            return null;
        }

        $omieSyncAt = $lastCustomerSync->getData(Omie::OMIE_SYNC_AT);

        if (!$omieSyncAt) {
            return null;
        }

        return new DateTime($omieSyncAt);
    }

    private function saveAddress(Customer $customer, Client $client): void
    {
        /** @var Phone $phone */
        $phone = $client->getPhones()[0] ?? Phone::empty();

        $clientAddress = $client->getAddress();
        $clientAddressShipping = $client->getAddressShipping();

        $region = $this->regionFactory->create()
            ->loadByCode($clientAddress->getRegionCode(), 'BR');

        $customerAddress = $customer->getDefaultBillingAddress();

        if (!$customerAddress) {
            $customerAddress = $this->addressFactory->create()->setCustomer($customer);
        }

        $customerAddress
            ->setFirstname($client->getFirstname())
            ->setLastname($client->getLastname())
            ->setCountryId('BR')
            ->setPostcode($clientAddress->getZipcode())
            ->setCity($clientAddress->getCity())
            ->setRegionId($region->getId())
            ->setTelephone($phone->format())
            ->setCompany($client->getSocialname())
            ->setStreet([
                $clientAddress->getStreet(),
                $clientAddress->getNumber(),
                $clientAddress->getDistrict(),
                $clientAddress->getComplement(),
            ])
            ->setIsDefaultBilling('1')
            ->setIsDefaultShipping(!$clientAddressShipping ? '1' : '0')
            ->setSaveInAddressBook('1');

        try {
            $customerAddress->save();
        } catch (Exception $e) {
            $this->logger->critical(
                sprintf(
                    'Omie set client %d, address error: %s',
                    $client->getId(),
                    $e->getMessage()
                )
            );
        }

        if (!$clientAddressShipping) {
            return;
        }

        $customerAddressShipping = $customer->getDefaultShippingAddress();

        if (!$customerAddressShipping) {
            $customerAddressShipping = $this->addressFactory->create()->setCustomer($customer);
        }

        $region = $this->regionFactory->create()
            ->loadByCode($clientAddressShipping->getRegionCode(), 'BR');

        $customerAddressShipping
            ->setFirstname($client->getFirstname())
            ->setLastname($client->getLastname())
            ->setCountryId('BR')
            ->setPostcode($clientAddressShipping->getZipcode())
            ->setCity($clientAddressShipping->getCity())
            ->setRegionId($region->getId())
            ->setTelephone($phone->format())
            ->setCompany($client->getSocialname())
            ->setStreet([
                $clientAddressShipping->getStreet(),
                $clientAddressShipping->getNumber(),
                $clientAddressShipping->getDistrict(),
                $clientAddressShipping->getComplement(),
            ])
            ->setIsDefaultBilling('0')
            ->setIsDefaultShipping('1')
            ->setSaveInAddressBook('1');

        try {
            $customerAddressShipping->save();
        } catch (Exception $e) {
            $this->logger->critical(
                sprintf(
                    'Omie set client %d, address shipping error: %s',
                    $client->getId(),
                    $e->getMessage()
                )
            );
        }
    }
}
