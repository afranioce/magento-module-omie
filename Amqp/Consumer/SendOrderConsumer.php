<?php

declare(strict_types=1);

namespace Omie\Integration\Amqp\Consumer;

use Magento\Sales\Api\Data\OrderInterface;
use Omie\Integration\Service\Sync\OrderServiceInterface;

final class SendOrderConsumer
{
    private OrderServiceInterface $orderService;

    public function __construct(OrderServiceInterface $orderService)
    {
        $this->orderService = $orderService;
    }

    public function process(OrderInterface $order): void
    {
        $this->orderService->send($order);
    }
}
