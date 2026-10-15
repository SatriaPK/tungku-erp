<?php

namespace App\Exceptions;

use Exception;

class InvalidWebhookSignatureException extends Exception
{
    public function __construct(string $gateway)
    {
        parent::__construct("Invalid webhook signature from {$gateway}");
    }
}
