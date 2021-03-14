<?php

declare(strict_types=1);

namespace Omie\Integration\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Payment;
use Omie\Integration\Model\BilletPayment;

class BeforeOrderPaymentSaveObserver implements ObserverInterface
{
    public function execute(Observer $observer)
    {
        /** @var Payment $payment */
        $payment = $observer->getEvent()->getPayment();

        if ($payment->getMethod() === BilletPayment::CODE
            && empty($payment->getAdditionalInformation('instructions'))) {
            $payment->setAdditionalInformation(
                'instructions',
                $payment->getMethodInstance()->getConfigData(
                    'instructions',
                    $payment->getOrder()->getStoreId()
                )
            );
        }
    }
}
