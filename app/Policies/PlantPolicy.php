<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;

class PlantPolicy
{
    public function view(User $user, Plant $plant): bool
    {
        return $plant->is_public || $plant->user_id === $user->id || $user->isAdmin();
    }

    public function update(User $user, Plant $plant): bool
    {
        return $plant->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, Plant $plant): bool
    {
        return $plant->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
