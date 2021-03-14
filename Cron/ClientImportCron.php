<?php

declare(strict_types=1);

namespace Omie\Integration\Cron;

use Omie\Integration\Service\Sync\CustomerServiceInterface;
use Psr\Log\LoggerInterface;

class ClientImportCron implements CronInterface
{
    private CustomerServiceInterface $customerSyncService;

    private LoggerInterface $logger;

    public function __construct(
        CustomerServiceInterface $customerSyncService,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->customerSyncService = $customerSyncService;
    }

    public function process(): void
    {
        $this->logger->info('Import massive customer start');

        $this->customerSyncService->massImport();

        $this->logger->info('Import massive customer end');
    }
}
