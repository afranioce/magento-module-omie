<?php

declare(strict_types=1);

namespace Omie\Integration\Service\Sync;

use Magento\Customer\Api\Data\CustomerInterface;

interface CustomerServiceInterface extends MassiveImporterInterface
{
    public function send(CustomerInterface $customer): void;
}
