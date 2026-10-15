<?php

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    protected static ?PaymentGatewayInterface $fakeInstance = null;

    /**
     * Create a payment gateway instance based on the provided name.
     *
     * @throws InvalidArgumentException
     */
    public static function make(?string $gateway = null): PaymentGatewayInterface
    {
        // Return fake instance if set
        if (self::$fakeInstance !== null) {
            return self::$fakeInstance;
        }

        $gateway = $gateway ?? config('payment.default_gateway');

        return match ($gateway) {
            'midtrans' => new MidtransGateway,
            'xendit' => new XenditGateway,
            'manual', 'cash' => new ManualGateway,
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gateway}"),
        };
    }

    /**
     * Get available payment gateways.
     *
     * @return array<string>
     */
    public static function available(): array
    {
        return ['midtrans', 'xendit', 'manual', 'cash'];
    }

    /**
     * Check if a gateway is supported.
     */
    public static function supports(string $gateway): bool
    {
        return in_array($gateway, self::available());
    }

    /**
     * Set a fake gateway instance for testing.
     */
    public static function fake(?PaymentGatewayInterface $gateway = null): void
    {
        self::$fakeInstance = $gateway ?? new FakePaymentGateway;
    }

    /**
     * Clear the fake gateway instance.
     */
    public static function clearFake(): void
    {
        self::$fakeInstance = null;
    }
}
