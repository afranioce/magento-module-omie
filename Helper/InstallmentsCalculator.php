<?php

declare(strict_types=1);

namespace Omie\Integration\Helper;

use Gabrielqs\Installments\Model\Calculator;
use Gabrielqs\Installments\Model\QuoteManager;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;

class InstallmentsCalculator extends AbstractHelper implements InstallmentsCalculatorInterface
{
    protected Calculator $calculator;

    protected bool $isInitialized = false;

    protected Data $dataHelper;

    protected QuoteManager $quoteManager;

    private CheckoutSession $checkoutSession;

    private CustomerRepositoryInterface $customerRepository;

    public function __construct(
        Context $context,
        Data $dataHelper,
        Calculator $calculator,
        QuoteManager $quoteManager,
        CheckoutSession $checkoutSession,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->quoteManager = $quoteManager;
        $this->calculator = $calculator;
        $this->dataHelper = $dataHelper;
        $this->checkoutSession = $checkoutSession;
        $this->customerRepository = $customerRepository;

        parent::__construct($context);
    }

    public function getInstallmentsCalculator(): Calculator
    {
        if (!$this->isInitialized) {
            $this->calculator->setInterestRate($this->getInterestRate());
            $this->calculator->setMinimumInstallmentAmount($this->getMinimumInstallmentAmount());
            $this->calculator->setMaximumInstallmentQuantity($this->getMaximumInstallmentQuantity());
            $this->isInitialized = true;
        }

        return $this->calculator;
    }

    public function getInstallments(float $amount): array
    {
        return $this->getInstallmentsCalculator()->getInstallments($amount);
    }

    public function getInstallmentConfig(): DataObject
    {
        return $this->getInstallmentsCalculator()->getInstallmentConfig();
    }

    public function setInstallmentDataBeforeAuthorization(int $installmentQuantity): void
    {
        $this->quoteManager->setCalculator($this->getInstallmentsCalculator());
        $this->quoteManager->setInstallmentDataBeforeAuthorization($installmentQuantity);
    }

    private function getInterestRate(): float
    {
        return (1 + ((float) $this->dataHelper->getPaymentConfig('interest_rate') / 100));
    }

    private function getMinimumInstallmentAmount(): float
    {
        return (float) $this->dataHelper->getPaymentConfig('minimum_installment_value');
    }

    private function getMaximumInstallmentQuantity(): int
    {
        $customerId = $this->checkoutSession->getQuote()->getCustomerId();

        if ($customerId) {
            $customer = $this->customerRepository->getById($customerId);
            $cpf = $customer->getCustomAttribute('cpf');

            if ($cpf) {
                return (int) $this->dataHelper->getPaymentConfig('maximum_installment_quantity_physical_person');
            }
        }

        return (int) $this->dataHelper->getPaymentConfig('maximum_installment_quantity');
    }
}
