<?php

namespace App\Policies;

use App\Models\User;

class FollowPolicy
{
    public function follow(User $user, User $target): bool
    {
        return $user->id !== $target->id;
    }

    public function unfollow(User $user, User $target): bool
    {
        return $user->id !== $target->id;
    }

    public function viewRelations(User $user, User $target): bool
    {
        return true;
    }
}
