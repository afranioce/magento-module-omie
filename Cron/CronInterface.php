<?php

declare(strict_types=1);

namespace Omie\Integration\Cron;

interface CronInterface
{
    public function process(): void;
}
