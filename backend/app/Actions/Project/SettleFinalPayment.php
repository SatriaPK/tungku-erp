<?php

namespace App\Actions\Project;

use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Enum\ProjectStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettleFinalPayment
{
    public function execute(Payment $payment): Project
    {
        return DB::transaction(function () use ($payment): Project {
            $payment = Payment::query()->lockForUpdate()->with('invoice')->findOrFail($payment->id);
            /** @var Project $project */
            $project = Project::query()->lockForUpdate()->findOrFail($payment->project_id);
            $projectStatus = ProjectStatus::from((string) $project->getRawOriginal('status'));

            if ($payment->type !== PaymentType::Final || $payment->status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages(['payment' => 'Final payment harus lunas sebelum project diselesaikan.']);
            }

            if ($project->status !== ProjectStatus::AwaitingFinalPayment
                || $payment->amount !== $project->final_payment_amount) {
                throw ValidationException::withMessages(['payment' => 'Nominal pembayaran tidak cocok dengan saldo final terkunci.']);
            }

            $paidAmount = (int) $project->payments()->where('status', PaymentStatus::Paid->value)->sum('amount');
            if ($paidAmount < $project->totalDue()) {
                throw ValidationException::withMessages(['payment' => 'Seluruh kewajiban project belum dibayar.']);
            }

            if ($payment->invoice_id !== null) {
                Invoice::query()->whereKey($payment->invoice_id)->update([
                    'status' => InvoiceStatus::Paid,
                    'paid_at' => $payment->paid_at ?? now(),
                ]);
            }

            if ($projectStatus === ProjectStatus::AwaitingFinalPayment) {
                $project->update(['status' => ProjectStatus::Completed, 'completed_at' => $payment->paid_at ?? now()]);
            }

            return $project->fresh();
        });
    }
}
