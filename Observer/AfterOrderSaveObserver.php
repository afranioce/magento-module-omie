<?php

declare(strict_types=1);

namespace Omie\Integration\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Omie\Integration\Amqp\Topics;
use Psr\Log\LoggerInterface;

final class AfterOrderSaveObserver implements ObserverInterface
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
        /** @var OrderInterface $order */
        $order = $observer->getData('order');

        $this->logger->info(sprintf('Order %d saved', $order->getIncrementId()));

        $this->publisher->publish(Topics::ORDER_REGISTERED, $order);

        $this->logger->info(sprintf('Order %d published to topic "%s"', $order->getIncrementId(), Topics::ORDER_REGISTERED));
    }
}
