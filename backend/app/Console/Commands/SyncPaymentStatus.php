<?php

namespace App\Console\Commands;

use App\Actions\Payment\ConfirmPayment;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPaymentStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:sync {--dry-run : Only show what would be synced} {--gateway= : Filter by specific gateway} {--limit=100 : Maximum number of records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync ambiguous payment status with external gateway providers';

    /**
     * Execute the console command.
     */
    public function handle(ConfirmPayment $confirmAction): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $gatewayFilter = $this->option('gateway');

        $this->info('Syncing payment statuses'.($dryRun ? ' (DRY RUN)' : ''));

        $query = Payment::query()
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::PendingManualConfirmation->value])
            ->whereNotNull('provider_transaction_id')
            ->where('provider', '!=', 'cash'); // exclude manual cash handling since it has no gateway API

        if ($gatewayFilter) {
            $query->where('provider', $gatewayFilter);
        }

        $payments = $query->limit($limit)->get();

        if ($payments->isEmpty()) {
            $this->info('No pending payments to sync.');

            return self::SUCCESS;
        }

        $this->withProgressBar($payments, function (Payment $payment) use ($confirmAction, $dryRun) {
            try {
                $gateway = PaymentGatewayFactory::make($payment->provider->value);

                if ($dryRun) {
                    if ($this->getOutput()->isVerbose()) {
                        $this->line("\nWould check gateway API for payment: {$payment->order_id} via {$payment->provider->value}");
                    }

                    return;
                }

                $statusResult = $gateway->getChargeStatus($payment->provider_transaction_id);

                if ($statusResult === PaymentStatus::Paid->value && in_array($payment->status, [PaymentStatus::Pending, PaymentStatus::PendingManualConfirmation], true)) {
                    $confirmAction->execute(
                        $payment,
                        $payment->provider_transaction_id,
                        null,
                        ['synced_by' => 'console_command']
                    );

                    if ($this->getOutput()->isVerbose()) {
                        $this->info("\nSynced and marked payment {$payment->order_id} as Paid");
                    }
                } elseif (in_array($statusResult, [PaymentStatus::Expired->value, PaymentStatus::Failed->value], true)) {
                    $payment->update(['status' => $statusResult]);

                    if ($this->getOutput()->isVerbose()) {
                        $this->line("\nSynced and marked payment {$payment->order_id} as {$statusResult}");
                    }
                }
            } catch (\Exception $e) {
                if ($this->getOutput()->isVerbose()) {
                    $this->error("\nFailed to sync payment {$payment->order_id}: {$e->getMessage()}");
                }

                Log::warning('Failed sync payment command', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->newLine();
        $this->info("Synced {$payments->count()} payments.");

        return self::SUCCESS;
    }
}
