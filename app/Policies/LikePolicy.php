<?php

namespace App\Policies;

use App\Models\Plant;
use App\Models\User;

class LikePolicy
{
    public function toggle(User $user, Plant $plant): bool
    {
        return $plant->is_public && $plant->user_id !== $user->id;
    }

    public function viewPlantLikes(User $user, Plant $plant): bool
    {
        return $plant->is_public || $plant->user_id === $user->id || $user->isAdmin();
    }

    public function viewMyLikes(User $user): bool
    {
        return true;
    }

    public function viewLikeState(User $user, Plant $plant): bool
    {
        return $plant->is_public || $plant->user_id === $user->id || $user->isAdmin();
    }
}
