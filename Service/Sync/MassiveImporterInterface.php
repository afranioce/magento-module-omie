<?php

declare(strict_types=1);

namespace Omie\Integration\Service\Sync;

interface MassiveImporterInterface
{
    public function massImport(): void;
}
