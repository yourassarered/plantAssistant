<?php

namespace App\Policies;

use App\Models\CareSetting;
use App\Models\User;

class CareSettingPolicy
{
    public function view(User $user, CareSetting $setting): bool
    {
        return $setting->plant->user_id === $user->id || $user->isAdmin();
    }

    public function create(User $user, int $plantOwnerId): bool
    {
        return $plantOwnerId === $user->id || $user->isAdmin();
    }

    public function update(User $user, CareSetting $setting): bool
    {
        return $setting->plant->user_id === $user->id || $user->isAdmin();
    }

    public function delete(User $user, CareSetting $setting): bool
    {
        return $setting->plant->user_id === $user->id || $user->isAdmin();
    }
}
