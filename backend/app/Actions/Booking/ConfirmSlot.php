<?php

namespace App\Actions\Booking;

use App\Enum\SessionStatus;
use App\Models\RecordingSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class ConfirmSlot
{
    /**
     * Confirm a held recording session slot after payment.
     *
     *
     * @throws ModelNotFoundException
     * @throws InvalidArgumentException
     */
    public function handle(int $recordingSessionId): RecordingSession
    {
        $session = RecordingSession::find($recordingSessionId);

        if ($session === null) {
            throw new ModelNotFoundException("Recording session with ID {$recordingSessionId} not found.");
        }

        if ($session->status !== SessionStatus::Held) {
            throw new InvalidArgumentException(
                "Cannot confirm session. Current status is '{$session->status->value}', expected 'held'."
            );
        }

        if ($session->held_until === null || $session->held_until->isPast()) {
            throw new InvalidArgumentException(
                'Cannot confirm session. The hold has expired.'
            );
        }

        $session->status = SessionStatus::Confirmed;
        $session->held_until = null;
        $session->save();

        return $session;
    }
}
