<?php

declare(strict_types=1);

namespace Omie\Integration\Model\Adminhtml\Source;

use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Data\OptionSourceInterface;
use Omie\Sdk\Entity\General\BankAccount\BankAccount as SdkBankAccount;
use Omie\Sdk\Service\General\BankAccountServiceInterface;

final class BankAccount implements OptionSourceInterface
{
    private BankAccountServiceInterface $bankAccountService;

    public function __construct(BankAccountServiceInterface $bankAccountService)
    {
        $this->bankAccountService = $bankAccountService;
    }

    public function toOptionArray()
    {
        try {
            $result = $this->bankAccountService->getList();
        } catch (GuzzleException $e) {
            return [];
        }

        return array_map(fn (SdkBankAccount $bankAccount) => [
            'value' => $bankAccount->getId(),
            'label' => $bankAccount->getName(),
        ], $result->getResults());
    }
}
