<?php

namespace App\Http\Controllers\Api\V1\Manager;

use App\Actions\Invoice\EnsureFinalPaymentInvoice;
use App\Actions\Payment\CancelPayment;
use App\Actions\Payment\ConfirmManualPayment;
use App\Actions\Payment\RefundPayment;
use App\Enum\PaymentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Manager\PaymentListResource;
use App\Http\Resources\Manager\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class PaymentController extends Controller
{
    /**
     * List all payments with filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,paid,failed,expired,cancelled,refunded',
            'provider' => 'nullable|string|in:midtrans,xendit,manual,cash',
            'client_id' => 'nullable|integer|exists:users,id',
            'project_id' => 'nullable|integer|exists:projects,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'sort_by' => 'nullable|string|in:created_at,amount,paid_at',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Payment::with(['project.client']);

        // Apply filters
        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['provider'])) {
            $query->where('provider', $validated['provider']);
        }

        if (isset($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }

        if (isset($validated['client_id'])) {
            $query->whereHas('project', function ($q) use ($validated) {
                $q->where('client_id', $validated['client_id']);
            });
        }

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }

        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'created_at';
        $sortOrder = $validated['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = $validated['per_page'] ?? 15;
        $payments = $query->paginate($perPage);

        return PaymentListResource::collection($payments);
    }

    /**
     * Show payment detail.
     */
    public function show(Payment $payment): PaymentResource
    {
        $payment->load(['project.client', 'invoice.items']);

        return new PaymentResource($payment);
    }

    /**
     * Confirm manual payment.
     */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()->isManager(), 403);
        $validated = $request->validate([
            'note' => 'required|string|max:500',
            'paid_at' => 'nullable|date|before_or_equal:now',
        ]);

        if ($payment->type === PaymentType::Final) {
            try {
                app(EnsureFinalPaymentInvoice::class)->execute($payment);
            } catch (InvalidArgumentException $exception) {
                return response()->json(['message' => 'Cannot prepare final invoice.', 'errors' => ['payment' => [$exception->getMessage()]]], 422);
            }
        }

        try {
            app(ConfirmManualPayment::class)->execute(
                $payment,
                $validated['note'],
                isset($validated['paid_at']) ? new \DateTime($validated['paid_at']) : null
            );

            $payment->refresh();
            $payment->load(['project.client', 'invoice']);

            return (new PaymentResource($payment))
                ->response()
                ->setStatusCode(200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Cannot confirm payment.',
                'errors' => [
                    'payment' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Refund payment.
     */
    public function refund(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'reason' => 'required|string|max:500',
        ]);

        try {
            app(RefundPayment::class)->execute(
                $payment,
                $validated['amount'],
                $validated['reason']
            );

            $payment->refresh();
            $payment->load(['project.client', 'invoice']);

            return (new PaymentResource($payment))
                ->response()
                ->setStatusCode(200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Cannot refund payment.',
                'errors' => [
                    'payment' => [$e->getMessage()],
                ],
            ], 422);
        }
    }

    /**
     * Cancel payment.
     */
    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            app(CancelPayment::class)->execute($payment, $validated['reason']);

            $payment->refresh();
            $payment->load(['project.client', 'invoice']);

            return (new PaymentResource($payment))
                ->response()
                ->setStatusCode(200);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => 'Cannot cancel payment.',
                'errors' => [
                    'payment' => [$e->getMessage()],
                ],
            ], 422);
        }
    }
}
