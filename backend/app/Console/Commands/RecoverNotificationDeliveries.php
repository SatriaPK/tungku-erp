<?php

namespace App\Console\Commands;

use App\Actions\Notification\RecordNotification;
use App\Models\NotificationDelivery;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('notifications:recover {--retry= : Retry one failed delivery ID with its original identity}')]
#[Description('Recover up to 100 due notification deliveries, including expired worker leases')]
class RecoverNotificationDeliveries extends Command
{
    public function handle(RecordNotification $recorder): int
    {
        $retry = $this->option('retry');
        if ($retry !== null) {
            if (! ctype_digit((string) $retry)) {
                $this->error('The retry option must be a delivery ID.');

                return self::INVALID;
            }
            $updated = NotificationDelivery::query()->whereKey((int) $retry)->where('status', 'failed')
                ->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now(),
                    'lease_token' => null, 'lease_until' => null]);
            if ($updated !== 1) {
                $this->error('Only a failed delivery can be retried.');

                return self::FAILURE;
            }
            $recorder->enqueue((int) $retry);

            return self::SUCCESS;
        }

        $ids = NotificationDelivery::query()->where(function (Builder $query): void {
            $query->where(fn (Builder $pending) => $pending->where('status', 'pending')->where('available_at', '<=', now()))
                ->orWhere(fn (Builder $leased) => $leased->where('status', 'processing')->where('lease_until', '<=', now()));
        })->orderBy('id')->limit(100)->pluck('id');

        foreach ($ids as $id) {
            $recorder->enqueue($id);
        }
        $this->info('Notification deliveries scheduled: '.$ids->count());

        return self::SUCCESS;
    }
}
