<?php

namespace App\Console\Commands;

use App\Actions\Payment\ExpirePayment;
use App\Enum\PaymentStatus;
use App\Models\Payment;
use Illuminate\Console\Command;

class ExpireUnpaidPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:expire {--dry-run : Only show what would be expired} {--limit=100 : Maximum number of records to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire unpaid payments that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle(ExpirePayment $action): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info('Checking for expired payments'.($dryRun ? ' (DRY RUN)' : ''));

        $payments = Payment::query()
            ->whereIn('status', [PaymentStatus::Pending->value])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->limit($limit)
            ->get();

        if ($payments->isEmpty()) {
            $this->info('No expired payments found.');

            return self::SUCCESS;
        }

        $this->withProgressBar($payments, function (Payment $payment) use ($action, $dryRun) {
            if ($dryRun) {
                if ($this->getOutput()->isVerbose()) {
                    $this->line("\nWould expire payment: {$payment->order_id} (Expired at: {$payment->expires_at})");
                }

                return;
            }

            try {
                $action->execute($payment);
                if ($this->getOutput()->isVerbose()) {
                    $this->info("\nExpired payment: {$payment->order_id}");
                }
            } catch (\Exception $e) {
                if ($this->getOutput()->isVerbose()) {
                    $this->error("\nFailed to expire payment {$payment->order_id}: {$e->getMessage()}");
                }
            }
        });

        $this->newLine();
        $this->info("Processed {$payments->count()} expired payments.");

        return self::SUCCESS;
    }
}
