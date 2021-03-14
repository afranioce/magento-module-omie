<?php

declare(strict_types=1);

namespace Omie\Integration\Amqp;

interface Topics
{
    public const CUSTOMER_REGISTERED = 'omie.customer_registered.topic';
    public const CUSTOMER_CHANGED = 'omie.customer_changed.topic';

    public const ORDER_REGISTERED = 'omie.order_registered.topic';
}
