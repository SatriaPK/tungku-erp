<?php

namespace App\Console\Commands;

use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use Illuminate\Console\Command;
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
            $session->update(['status' => SessionStatus::Expired->value]);
            $session->held_until = null;
            $session->save();

            Log::info('Released expired recording session hold.', [
                'recording_session_id' => $session->id,
            ]);
        }

        $this->info("Released {$expiredHolds->count()} expired hold(s).");

        return self::SUCCESS;
    }
}
