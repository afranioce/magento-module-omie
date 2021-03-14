<?php

declare(strict_types=1);

namespace Omie\Integration\Service\Sync;

use DateInterval;
use DateTimeImmutable;
use GuzzleHttp\Exception\ClientException;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Omie\Integration\Helper\Data;
use Omie\Integration\Helper\GeneralCodes;
use Omie\Integration\Model\Omie;
use Omie\Payment\Boleto\Model\Method\Boleto;
use Omie\Sdk\Entity\Product\Order\Installment;
use Omie\Sdk\Entity\Product\Order\Item;
use Omie\Sdk\Entity\Product\Order\Order as SdkOrder;
use Omie\Sdk\Entity\Product\Order\OrderSteps;
use Omie\Sdk\Service\Product\OrderServiceInterface as SdkOrderServiceInterface;
use Psr\Log\LoggerInterface;

final class OrderService implements OrderServiceInterface
{
    private SdkOrderServiceInterface $orderService;

    private OrderRepositoryInterface $orderRepository;

    private CustomerRepositoryInterface $customerRepository;

    private ProductRepositoryInterface $productRepository;

    private Data $helperData;

    private LoggerInterface $logger;

    public function __construct(
        SdkOrderServiceInterface $orderService,
        OrderRepositoryInterface $orderRepository,
        CustomerRepositoryInterface $customerRepository,
        ProductRepositoryInterface $productRepository,
        Data $helperData,
        LoggerInterface $logger
    ) {
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->helperData = $helperData;
        $this->logger = $logger;
    }

    public function send(OrderInterface $order): void
    {
        $this->logger->info(sprintf('Omie order %s send', $order->getIncrementId()));

        try {
            $sdkOrder = $this->transformOrderForSdkOrder($order);
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Omie order %s transform error: %s',
                    $order->getIncrementId(),
                    $exception->getMessage()
                )
            );

            throw $exception;
        }

        try {
            $this->orderService->create($sdkOrder);
        } catch (ClientException $clientException) {
            $this->logger->error(
                sprintf(
                    'Omie order %s sent OmieId: %d, OrderId: %s error: %s',
                    $order->getIncrementId(),
                    $sdkOrder->getId(),
                    $sdkOrder->getOrderId(),
                    $clientException->getMessage()
                )
            );

            throw $clientException;
        }

        $this->logger->info(
            sprintf(
                'Omie order %s sent successfull OmieId: %d, OrderId: %s',
                $order->getIncrementId(),
                $sdkOrder->getId(),
                $sdkOrder->getOrderId()
            )
        );

        $order->setExtOrderId($sdkOrder->getOrderId());
        $order->setExtCustomerId($sdkOrder->getClientId());

        $this->orderRepository->save($order);

        $this->logger->info(
            sprintf(
                'Omie order %s updated, the omie orderId is %d, extOrderId and extCustomerId has changed',
                $order->getIncrementId(),
                $sdkOrder->getId()
            )
        );
    }

    private function transformOrderForSdkOrder(OrderInterface $order): SdkOrder
    {
        $payment = $order->getPayment();
        $customer = $this->customerRepository->getById($order->getCustomerId());

        $installments = class_exists(Boleto::class) && $payment->getMethod() == Boleto::CODE
            ? $this->getInstallments($payment)
            : [];

        return new SdkOrder(
            null,
            $this->helperData->getGeneralConfig(GeneralCodes::PREFIX_INTEGRATION) . $order->getIncrementId(),
            (int) $customer->getCustomAttribute(Omie::OMIE_ID)->getValue(),
            $this->helperData->getConfigValue('sales/omie_order/category'),
            (int) $this->helperData->getConfigValue('sales/omie_order/bank_account'),
            new DateTimeImmutable('+1 month'),
            $installments,
            OrderSteps::FIRST,
            $this->getItems($order)
        );
    }

    private function getInstallments(OrderPaymentInterface $payment): array
    {
        $installments = [];

        $dateInterval = new DateInterval('P1M');

        $quantity = (int) $payment->getAdditionalInformation()[0];
        $amount = (float) $payment->getAdditionalInformation()[1];
        $expirationDays = (float) $payment->getAdditionalInformation()[3];
        $percent = 100 * $amount / $payment->getAmountOrdered();

        $installmentDate = new DateTimeImmutable(sprintf('+%d days', $expirationDays));

        for ($i = 1; $i <= $quantity; $i++) {
            $installmentDate = $installmentDate->add($dateInterval);

            $installments[] = new Installment(
                $i,
                $installmentDate,
                $percent,
                $amount
            );
        }

        return $installments;
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getItems(OrderInterface $order): array
    {
        $items = [];

        foreach ($order->getItems() as $orderItem) {
            $product = $this->productRepository->getById($orderItem->getProductId());

            $items[] = new Item(
                $this->helperData->getGeneralConfig(GeneralCodes::PREFIX_INTEGRATION) . $orderItem->getQuoteItemId(),
                (int) $product->getCustomAttribute(Omie::OMIE_ID)->getValue(),
                (int) $orderItem->getQtyOrdered(),
                $orderItem->getPrice(),
                $orderItem->getRowTotal(),
                $orderItem->getWeight(),
                $orderItem->getDiscountAmount(),
            );
        }

        return $items;
    }
}
