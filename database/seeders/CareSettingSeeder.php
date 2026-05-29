<?php

namespace Database\Seeders;

use App\Models\CareSetting;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class CareSettingSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::orderBy('id')->get();
        $careTypes = [
            'watering' => [3, 5, 7, 10, 14],
            'fertilizing' => [14, 21, 30],
            'pruning' => [30, 60, 90],
            'rotation' => [7, 14, 21],
        ];

        foreach ($plants as $plant) {
            $typeKeys = array_keys($careTypes);
            shuffle($typeKeys);
            $selectedTypes = array_slice($typeKeys, 0, 2 + ($plant->id % 3));

            foreach ($selectedTypes as $index => $type) {
                $intervals = $careTypes[$type];
                $interval = $intervals[($plant->id + $index) % count($intervals)];

                CareSetting::updateOrCreate(
                    ['plant_id' => $plant->id, 'type' => $type],
                    [
                        'interval_days' => $interval,
                        'is_enabled' => (($plant->id + $index) % 10) !== 0,
                        'last_done_at' => (($plant->id + $index) % 4) === 0
                            ? null
                            : now()->subDays(min($interval, 1 + (($plant->id + $index) % max($interval, 1)))),
                    ]
                );
            }
        }
    }
}
