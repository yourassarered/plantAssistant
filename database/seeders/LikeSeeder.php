<?php

namespace Database\Seeders;

use App\Models\Like;
use App\Models\Plant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class LikeSeeder extends Seeder
{
    public function run(): void
    {
        $publicPlants = Plant::where('is_public', true)->get();
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->get();

        if ($publicPlants->isEmpty() || $users->isEmpty()) {
            return;
        }

        foreach ($users as $user) {
            $availablePlants = $publicPlants->where('user_id', '!=', $user->id)->values();
            if ($availablePlants->isEmpty()) {
                continue;
            }

            $targetCount = min(random_int(8, 20), $availablePlants->count());
            $selected = $availablePlants->shuffle()->take($targetCount);

            foreach ($selected as $plant) {
                Like::firstOrCreate([
                    'user_id' => $user->id,
                    'plant_id' => $plant->id,
                ]);
            }
        }
    }
}
