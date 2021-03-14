<?php

declare(strict_types=1);

namespace Omie\Integration\Amqp\Consumer;

use Magento\Customer\Api\Data\CustomerInterface;
use Omie\Integration\Service\Sync\CustomerServiceInterface;

final class SendClientConsumer
{
    private CustomerServiceInterface $customerSyncService;

    public function __construct(CustomerServiceInterface $customerSyncService)
    {
        $this->customerSyncService = $customerSyncService;
    }

    public function process(CustomerInterface $customer): void
    {
        $this->customerSyncService->send($customer);
    }
}
