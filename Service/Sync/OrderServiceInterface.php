<?php

declare(strict_types=1);

namespace Omie\Integration\Service\Sync;

use Magento\Sales\Api\Data\OrderInterface;

interface OrderServiceInterface
{
    public function send(OrderInterface $order): void;
}
