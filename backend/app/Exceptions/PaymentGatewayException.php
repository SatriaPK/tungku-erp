<?php

namespace App\Exceptions;

use Exception;

class PaymentGatewayException extends Exception
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function createChargeFailed(string $gateway, string $reason): self
    {
        return new self("Failed to create charge via {$gateway}: {$reason}");
    }

    public static function refundFailed(string $gateway, string $reason): self
    {
        return new self("Failed to process refund via {$gateway}: {$reason}");
    }

    public static function statusCheckFailed(string $gateway, string $chargeId): self
    {
        return new self("Failed to check status for charge {$chargeId} via {$gateway}");
    }
}
