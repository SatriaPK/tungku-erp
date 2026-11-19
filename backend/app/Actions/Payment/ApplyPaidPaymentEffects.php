<?php

namespace App\Actions\Payment;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Actions\Booking\CouponPaymentLifecycle;
use App\Actions\Notification\NotifyBusinessEvent;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Enum\SessionStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApplyPaidPaymentEffects
{
    public function handle(Payment $payment, ?AuditActor $actor = null): void
    {
        DB::transaction(function () use ($payment, $actor): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== PaymentStatus::Paid) {
                throw new InvalidArgumentException('Payment must be paid before applying settlement effects.');
            }
            app(CouponPaymentLifecycle::class)->redeem($payment);
            $audit = app(RecordBusinessChange::class);
            $actor ??= AuditActor::system(self::class);
            $project = $payment->project()->lockForUpdate()->first();
            $invoice = $payment->invoice()->lockForUpdate()->first();
            if ($invoice !== null) {
                $before = $audit->snapshot($invoice);
                $invoice->markAsPaid();
                $audit->handle($invoice, 'invoice.paid', $before, $actor);
            }
            if ($project !== null && $payment->type === PaymentType::Po
                && $project->status === ProjectStatus::PendingPayment && $payment->amount >= $project->po_amount) {
                foreach ($project->recordingSessions()->where('client_id', $project->client_id)
                    ->whereNull('payment_id')->where('status', SessionStatus::Held)
                    ->where('held_until', '>', now())->lockForUpdate()->get() as $heldSession) {
                    $before = $audit->snapshot($heldSession);
                    $heldSession->update(['payment_id' => $payment->id]);
                    $audit->handle($heldSession, 'booking.payment_linked', $before, $actor);
                }
            }
            foreach ($payment->recordingSessions()->lockForUpdate()->get() as $session) {
                if ($session->status === SessionStatus::Held) {
                    $before = $audit->snapshot($session);
                    $session->update(['status' => SessionStatus::Confirmed, 'held_until' => null]);
                    $audit->handle($session, 'booking.confirmed', $before, $actor);
                }
            }
            if ($project !== null && $payment->type === PaymentType::Po
                && $project->status === ProjectStatus::PendingPayment && $payment->amount >= $project->po_amount) {
                $before = $audit->snapshot($project);
                $project->update(['status' => ProjectStatus::AwaitingAssignment]);
                $audit->handle($project, 'project.awaiting_assignment', $before, $actor);
                app(NotifyBusinessEvent::class)->project($project, 'project_awaiting_assignment');
            }
        });
    }
}
