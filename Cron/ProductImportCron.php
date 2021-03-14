<?php

declare(strict_types=1);

namespace Omie\Integration\Cron;

use Omie\Integration\Service\Sync\ProductServiceInterface;
use Psr\Log\LoggerInterface;

final class ProductImportCron implements CronInterface
{
    private ProductServiceInterface $productService;

    private LoggerInterface $logger;

    public function __construct(
        ProductServiceInterface $productService,
        LoggerInterface $logger
    ) {
        $this->productService = $productService;
        $this->logger = $logger;
    }

    public function process(): void
    {
        $this->logger->info('Import massive product start');

        $this->productService->massImport();

        $this->logger->info('Import massive product end');
    }
}
