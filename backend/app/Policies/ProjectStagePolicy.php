<?php

namespace App\Policies;

use App\Models\ProjectStage;
use App\Models\User;

class ProjectStagePolicy
{
    public function requestRecordingExtension(User $user, ProjectStage $projectStage): bool
    {
        return $user->isOperator()
            && $user->is_active
            && $projectStage->operator_id === $user->id;
    }
}
