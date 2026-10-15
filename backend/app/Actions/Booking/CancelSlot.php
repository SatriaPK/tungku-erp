<?php

namespace App\Actions\Booking;

use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class CancelSlot
{
    /**
     * Cancel a recording session.
     *
     * Note: The $reason parameter is accepted for future use but not currently
     * stored in the database. This keeps the API stable for when we add
     * a cancellation_reason column later.
     *
     *
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public function handle(int $recordingSessionId, ?string $reason = null): RecordingSession
    {
        $session = RecordingSession::find($recordingSessionId);

        if ($session === null) {
            throw new ModelNotFoundException("Recording session with ID {$recordingSessionId} not found.");
        }

        if ($session->status === SessionStatus::Cancelled) {
            throw new InvalidArgumentException(
                'Cannot cancel session. The session is already cancelled.'
            );
        }

        if ($session->status === SessionStatus::Completed) {
            throw new InvalidArgumentException(
                'Cannot cancel session. Completed sessions cannot be cancelled.'
            );
        }

        if (! in_array($session->status, [SessionStatus::Held, SessionStatus::Confirmed])) {
            throw new InvalidArgumentException(
                "Cannot cancel session. Current status is '{$session->status->value}'."
            );
        }

        $session->status = SessionStatus::Cancelled;
        $session->held_until = null;
        $session->save();

        return $session;
    }
}
