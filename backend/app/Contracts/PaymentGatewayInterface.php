<?php

namespace App\Contracts;

use App\Exceptions\InvalidWebhookSignatureException;
use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create a payment charge/invoice with the gateway.
     *
     * @return array{
     *     charge_id: string,
     *     payment_url: ?string,
     *     snap_token: ?string,
     *     expires_at: Carbon
     * }
     *
     * @throws PaymentGatewayException
     */
    public function createCharge(Payment $payment): array;

    /**
     * Get the current status of a charge from the gateway.
     *
     * @return string One of: pending, paid, failed, expired
     *
     * @throws PaymentGatewayException
     */
    public function getChargeStatus(string $chargeId): string;

    /**
     * Process a refund for a paid charge.
     *
     * @return array{
     *     refund_id: string,
     *     status: string,
     *     refunded_at: Carbon
     * }
     *
     * @throws PaymentGatewayException
     */
    public function processRefund(Payment $payment, int $amount, string $reason): array;

    /**
     * Verify that a webhook request came from the payment gateway.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Parse webhook payload into a standardized format.
     *
     * @return array{
     *     order_id: string,
     *     transaction_id: string,
     *     status: string,
     *     amount: int,
     *     payment_method: ?string,
     *     paid_at: ?Carbon
     * }
     */
    public function parseWebhookPayload(Request $request): array;

    /**
     * Get the gateway name/identifier.
     */
    public function getName(): string;
}
