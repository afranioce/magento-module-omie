<?php

namespace Omie\Integration\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Address;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Omie\Integration\Amqp\Topics;
use Psr\Log\LoggerInterface;

class AfterCustomerAddressSaveObserver implements ObserverInterface
{
    private CustomerRepositoryInterface $customerRepository;

    private PublisherInterface $publisher;

    private LoggerInterface $logger;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        PublisherInterface $publisher,
        LoggerInterface $logger
    ) {
        $this->publisher = $publisher;
        $this->logger = $logger;
        $this->customerRepository = $customerRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var $customerAddress Address */
        $customerAddress = $observer->getCustomerAddress();

        $customer = $this->customerRepository->getById($customerAddress->getCustomerId());

        $this->logger->info(sprintf('Customer %d changed address', $customer->getId()));

        $this->publisher->publish(Topics::CUSTOMER_CHANGED, $customer);

        $this->logger->info(
            sprintf(
                'Customer %d changed address published to topic "%s"',
                $customer->getId(),
                Topics::CUSTOMER_CHANGED
            )
        );
    }
}
