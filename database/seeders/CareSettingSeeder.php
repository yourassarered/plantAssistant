<?php

namespace Database\Seeders;

use App\Models\CareSetting;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class CareSettingSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();
        $careTypes = [
            'watering' => [3, 5, 7, 10, 14],
            'fertilizing' => [14, 21, 30],
            'pruning' => [30, 60, 90],
            'rotation' => [7, 14, 21],
        ];

        foreach ($plants as $plant) {
            $selectedTypes = array_slice(array_keys($careTypes), 0, random_int(2, 4));
            shuffle($selectedTypes);

            foreach ($selectedTypes as $type) {
                $intervals = $careTypes[$type];
                $interval = $intervals[array_rand($intervals)];

                CareSetting::firstOrCreate(
                    ['plant_id' => $plant->id, 'type' => $type],
                    [
                        'interval_days' => $interval,
                        'is_enabled' => random_int(1, 100) <= 90,
                        'last_done_at' => random_int(1, 100) <= 70 ? now()->subDays(random_int(0, $interval)) : null,
                    ]
                );
            }
        }
    }
}
