<?php

declare(strict_types=1);

namespace Omie\Integration\Observer;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Omie\Integration\Amqp\Topics;
use Psr\Log\LoggerInterface;

final class AfterCustomerSaveObserver implements ObserverInterface
{
    private PublisherInterface $publisher;

    private LoggerInterface $logger;

    public function __construct(
        PublisherInterface $publisher,
        LoggerInterface $logger
    ) {
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        /** @var CustomerInterface $customer */
        $customer = $observer->getData('customer_data_object');
        /** @var ?CustomerInterface $beforeSaveCustomer */
        $beforeSaveCustomer = $observer->getData('orig_customer_data_object');
        $isNewCustomer = $beforeSaveCustomer === null;
        $topic = $isNewCustomer ? Topics::CUSTOMER_REGISTERED : Topics::CUSTOMER_CHANGED;

        $this->logger->info(sprintf('Customer %d saved', $customer->getId()));

        $this->publisher->publish($topic, $customer);

        $this->logger->info(sprintf('Customer %d published to topic "%s"', $customer->getId(), $topic));
    }
}
