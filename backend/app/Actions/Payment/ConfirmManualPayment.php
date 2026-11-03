<?php

namespace App\Actions\Payment;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Actions\Notification\RecordNotification;
use App\Actions\Project\SettleFinalPayment;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Models\Payment;
use App\Notifications\PaymentConfirmedNotification;
use DateTime;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmManualPayment
{
    /**
     * Manually confirm a cash/manual payment.
     *
     * @throws InvalidArgumentException
     */
    public function execute(Payment $payment, string $note, ?DateTime $paidAt = null): Payment
    {
        // Validate payment can be confirmed
        if (! in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::PendingManualConfirmation], true)) {
            throw new InvalidArgumentException(
                "Cannot confirm payment with status: {$payment->status->value}"
            );
        }

        // Only manual/cash payments can be manually confirmed
        if ($payment->provider !== PaymentProvider::Cash) {
            throw new InvalidArgumentException(
                "Cannot manually confirm {$payment->provider->value} payment. Use webhook for online payments."
            );
        }

        return DB::transaction(function () use ($payment, $note, $paidAt) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->project_id !== null) {
                $payment->project()->lockForUpdate()->first();
                $payment->load('project');
            }
            if ($payment->status !== PaymentStatus::Pending && $payment->status !== PaymentStatus::PendingManualConfirmation) {
                throw new InvalidArgumentException("Cannot confirm payment with status: {$payment->status->value}");
            }

            if ($payment->type === PaymentType::Final) {
                $project = $payment->project;
                if ($project === null
                    || $project->status !== ProjectStatus::AwaitingFinalPayment
                    || $project->final_payment_amount !== $payment->amount) {
                    throw new InvalidArgumentException('Final payment does not match the project outstanding balance.');
                }
            }

            $audit = app(RecordBusinessChange::class);
            $before = $audit->snapshot($payment);
            $payment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $paidAt === null
                    ? now()
                    : (clone $paidAt)->setTimezone(new DateTimeZone(config('app.timezone'))),
                'notes' => $note,
            ]);
            $actor = AuditActor::system(self::class);
            $audit->handle($payment, 'payment.paid', $before, $actor, 'payment:'.$payment->id.':paid');
            app(ApplyPaidPaymentEffects::class)->handle($payment, $actor);

            if ($payment->type === PaymentType::Final) {
                app(SettleFinalPayment::class)->execute($payment);
            }

            $client = $payment->invoice?->client;
            if ($client === null && $payment->project_id !== null) {
                $client = $payment->project->client;
            }
            if ($client !== null) {
                app(RecordNotification::class)->handle($client, new PaymentConfirmedNotification($payment->fresh()));
            }

            return $payment->fresh();
        });
    }
}
