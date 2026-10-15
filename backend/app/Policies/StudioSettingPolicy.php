<?php

namespace App\Policies;

use App\Enum\Role;
use App\Models\User;

class StudioSettingPolicy
{
    /**
     * Determine if the user can view studio settings.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === Role::Manager;
    }

    /**
     * Determine if the user can update studio settings.
     */
    public function update(User $user): bool
    {
        return $user->role === Role::Manager;
    }
}
