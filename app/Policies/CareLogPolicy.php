<?php

namespace App\Policies;

use App\Models\CareLog;
use App\Models\User;

class CareLogPolicy
{
    public function view(User $user, CareLog $log): bool
    {
        return $log->plant->user_id === $user->id || $user->isAdmin();
    }

    public function create(User $user, int $plantOwnerId): bool
    {
        return $plantOwnerId === $user->id || $user->isAdmin();
    }

    public function delete(User $user, CareLog $log): bool
    {
        return $log->plant->user_id === $user->id || $user->isAdmin();
    }
}
