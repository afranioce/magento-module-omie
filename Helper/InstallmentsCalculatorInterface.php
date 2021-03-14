<?php

declare(strict_types=1);

namespace Omie\Integration\Helper;

use Gabrielqs\Installments\Model\Calculator;
use Magento\Framework\DataObject;

interface InstallmentsCalculatorInterface
{
    public function getInstallmentsCalculator(): Calculator;

    /**
     * @return \Magento\Framework\DataObject[]
     */
    public function getInstallments(float $amount);

    public function getInstallmentConfig(): DataObject;

    public function setInstallmentDataBeforeAuthorization(int $installmentQuantity): void;
}
