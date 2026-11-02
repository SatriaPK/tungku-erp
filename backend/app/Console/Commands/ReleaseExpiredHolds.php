<?php

namespace App\Console\Commands;

use App\Actions\Audit\AuditActor;
use App\Actions\Audit\RecordBusinessChange;
use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseExpiredHolds extends Command
{
    protected $signature = 'bookings:release-expired-holds';

    protected $description = 'Release recording session holds that have expired';

    public function handle(): int
    {
        $expiredHolds = RecordingSession::query()
            ->where('status', SessionStatus::Held->value)
            ->whereNotNull('held_until')
            ->where('held_until', '<=', now())
            ->get();

        foreach ($expiredHolds as $session) {
            DB::transaction(function () use ($session): void {
                $session = RecordingSession::query()->lockForUpdate()->findOrFail($session->id);
                if ($session->status !== SessionStatus::Held) {
                    return;
                }
                $audit = app(RecordBusinessChange::class);
                $before = $audit->snapshot($session);
                $session->update(['status' => SessionStatus::Expired, 'held_until' => null]);
                $audit->handle($session, 'booking.expired', $before, AuditActor::system('bookings:release-expired-holds'));
            });

            Log::info('Released expired recording session hold.', [
                'recording_session_id' => $session->id,
            ]);
        }

        $this->info("Released {$expiredHolds->count()} expired hold(s).");

        return self::SUCCESS;
    }
}
