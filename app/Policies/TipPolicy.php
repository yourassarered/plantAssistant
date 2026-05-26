<?php

namespace App\Policies;

use App\Models\Tip;
use App\Models\User;

class TipPolicy
{
    public function view(User $user, Tip $tip): bool
    {
        $plant = $tip->plant;

        return $tip->author_id === $user->id || $plant->user_id === $user->id || $user->isAdmin();
    }

    public function create(User $user, int $plantOwnerId, bool $isPublicPlant): bool
    {
        if (! $isPublicPlant) {
            return false;
        }

        return $user->id !== $plantOwnerId;
    }

    public function updateStatus(User $user, Tip $tip): bool
    {
        return $tip->plant->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, Tip $tip): bool
    {
        return $tip->author_id === $user->id || $tip->plant->user_id === $user->id || $user->isAdmin();
    }
}
