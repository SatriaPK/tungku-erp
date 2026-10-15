<?php

namespace App\Exceptions;

use RuntimeException;

class CRMEmailDeliveryException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $deliveryIsDefinitive, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
