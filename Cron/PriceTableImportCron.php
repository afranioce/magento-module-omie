<?php

declare(strict_types=1);

namespace Omie\Integration\Cron;

use Omie\Integration\Service\Sync\SaleRuleServiceInterface;
use Psr\Log\LoggerInterface;

final class PriceTableImportCron implements CronInterface
{
    private SaleRuleServiceInterface $saleRuleSyncService;

    private LoggerInterface $logger;

    public function __construct(
        SaleRuleServiceInterface $saleRuleSyncService,
        LoggerInterface $logger
    ) {
        $this->saleRuleSyncService = $saleRuleSyncService;
        $this->logger = $logger;
    }

    public function process(): void
    {
        $this->logger->info('Import massive price tables start');

        $this->saleRuleSyncService->massImport();

        $this->logger->info('Import massive price tables end');
    }
}
