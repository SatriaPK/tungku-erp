<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Actions\Payment\InitiateFinalPayment;
use App\Actions\Payment\InitiatePayment;
use App\Enum\PaymentProvider;
use App\Enum\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentController extends Controller
{
    /**
     * Initiate payment for an invoice.
     */
    public function initiatePayment(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $validated = $request->validate([
            'gateway' => 'required|string|in:midtrans,xendit',
        ]);

        try {
            $payment = app(InitiatePayment::class)->execute($invoice, $validated['gateway']);
            $payment->load(['project', 'invoice']);

            return (new PaymentResource($payment))
                ->response()
                ->setStatusCode(201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Cannot initiate payment.',
                'errors' => [
                    'invoice' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    public function initiateFinalPayment(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);
        abort_unless($request->user()->isClient() && $payment->project?->client_id === $request->user()->id, 403);

        $validated = $request->validate([
            'provider' => 'required|string|in:midtrans,xendit,cash',
        ]);
        abort_unless($payment->type === PaymentType::Final, 404);

        try {
            $payment = app(InitiateFinalPayment::class)->execute($payment, PaymentProvider::from($validated['provider']));
            $payment->load(['project', 'invoice']);

            return (new PaymentResource($payment))->response();
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => 'Cannot initiate final payment.', 'errors' => ['payment' => [$exception->getMessage()]]], 422);
        }
    }

    /**
     * Show payment status.
     */
    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        $payment->load(['project', 'invoice']);

        return (new PaymentResource($payment))->response();
    }
}
