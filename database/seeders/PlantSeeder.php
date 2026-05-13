<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlantSeeder extends Seeder
{
    public function run(): void
    {
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->get();

        $plantNames = [
            'Ficus Benjamina',
            'Monstera Deliciosa',
            'Sansevieria',
            'Spathiphyllum',
            'Dracaena Marginata',
            'Chlorophytum',
            'Aloe Vera',
            'Opuntia',
            'Zamioculcas',
            'Saintpaulia',
            'Crassula',
            'Begonia',
            'Pelargonium',
            'Nephrolepis',
            'Tradescantia',
            'Phalaenopsis',
            'Hedera Helix',
            'Anthurium',
            'Kalanchoe',
            'Azalea',
        ];

        foreach ($users as $user) {
            $rooms = Room::where('user_id', $user->id)->get();
            $targetCount = random_int(4, 10);

            for ($i = 0; $i < $targetCount; $i++) {
                Plant::create([
                    'name' => $plantNames[array_rand($plantNames)],
                    'planted_at' => now()->subDays(random_int(15, 720)),
                    'height' => random_int(10, 220) + (random_int(0, 9) / 10),
                    'is_public' => random_int(1, 100) <= 65,
                    'user_id' => $user->id,
                    'room_id' => $rooms->isNotEmpty() ? $rooms->random()->id : null,
                ]);
            }
        }
    }
}
