<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewFinalDelivery(User $user, Project $project): bool
    {
        return $user->isClient()
            && $project->client_id === $user->id
            && $project->status->value === 'completed';
    }
}
