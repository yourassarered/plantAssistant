<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Role;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Seeder;

class TipSeeder extends Seeder
{
    public function run(): void
    {
        $publicPlants = Plant::where('is_public', true)->get();
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->get();

        if ($publicPlants->isEmpty() || $users->count() < 2) {
            return;
        }

        $tipContents = [
            'Try adding perlite for better drainage.',
            'Move it closer to indirect bright light.',
            'Reduce watering frequency during colder days.',
            'Wipe leaves weekly to remove dust.',
            'Use room-temperature settled water.',
            'Check roots for signs of overwatering.',
            'Rotate the plant weekly for even growth.',
            'Use diluted fertilizer once every 2-4 weeks.',
        ];
        $statuses = ['pending', 'accepted', 'rejected'];

        $tipCount = random_int(60, 120);
        for ($i = 0; $i < $tipCount; $i++) {
            $plant = $publicPlants->random();
            $author = $users->where('id', '!=', $plant->user_id)->random();

            Tip::create([
                'plant_id' => $plant->id,
                'author_id' => $author->id,
                'content' => $tipContents[array_rand($tipContents)],
                'status' => $statuses[array_rand($statuses)],
            ]);
        }
    }
}
