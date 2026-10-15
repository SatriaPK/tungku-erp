<?php

namespace App\Policies;

use App\Models\RecordingSession;
use App\Models\User;

class RecordingSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isClient() || $user->isManager() || $user->isOperator();
    }

    public function view(User $user, RecordingSession $recordingSession): bool
    {
        if ($user->isManager()) {
            return true;
        }

        if ($user->isClient()) {
            return $recordingSession->client_id === $user->id;
        }

        return $user->isOperator()
            && $recordingSession->stage?->operator_id === $user->id;
    }

    public function cancel(User $user, RecordingSession $recordingSession): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $user->isClient()
            && $recordingSession->client_id === $user->id;
    }
}
