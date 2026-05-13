<?php

namespace Database\Seeders;

use App\Models\Follow;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class FollowSeeder extends Seeder
{
    public function run(): void
    {
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->get();

        if ($users->count() < 2) {
            return;
        }

        foreach ($users as $user) {
            $availableUsers = $users->where('id', '!=', $user->id)->values();
            $targetCount = min(random_int(3, 8), $availableUsers->count());
            $toFollow = $availableUsers->shuffle()->take($targetCount);

            foreach ($toFollow as $targetUser) {
                Follow::firstOrCreate([
                    'follower_id' => $user->id,
                    'following_id' => $targetUser->id,
                ]);
            }
        }
    }
}
