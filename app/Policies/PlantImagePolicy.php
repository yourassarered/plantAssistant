<?php

namespace App\Policies;

use App\Models\PlantImage;
use App\Models\User;

class PlantImagePolicy
{
    public function view(User $user, PlantImage $image): bool
    {
        $plant = $image->plant;

        return $plant->is_public || $plant->user_id === $user->id || $user->isAdmin();
    }

    public function createForPlant(User $user, int $plantOwnerId): bool
    {
        return $plantOwnerId === $user->id || $user->isAdmin();
    }

    public function update(User $user, PlantImage $image): bool
    {
        return $image->plant->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, PlantImage $image): bool
    {
        return $image->plant->user_id === $user->id || $user->isAdmin();
    }
}
