<?php

namespace App\Policies;

use App\Models\TrackStageProgress;
use App\Models\User;

class TrackStageProgressPolicy
{
    public function submit(User $user, TrackStageProgress $progress): bool
    {
        return $user->isOperator()
            && $progress->stage()->where('operator_id', $user->id)->exists();
    }

    public function review(User $user, TrackStageProgress $progress): bool
    {
        return $user->isManager();
    }

    public function viewHistory(User $user, TrackStageProgress $progress): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $user->isOperator()
            && $progress->stage()->where('operator_id', $user->id)->exists();
    }
}
