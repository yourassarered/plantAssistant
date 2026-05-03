<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CareSetting;
use App\Models\Plant;
use Carbon\Carbon;

class CareSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plants = Plant::all();

        $careTypes = [
            'watering' => [3, 5, 7, 10, 14], // интервалы в днях
            'fertilizing' => [14, 21, 30],
            'pruning' => [30, 60, 90],
            'rotation' => [7, 14, 21],
        ];

        foreach ($plants as $plant) {
            // Каждому растению создаём 2-4 типа ухода
            $typesCount = rand(2, 4);
            $selectedTypes = array_rand($careTypes, $typesCount);

            if (!is_array($selectedTypes)) {
                $selectedTypes = [$selectedTypes];
            }

            foreach ($selectedTypes as $type) {
                $intervals = $careTypes[$type];
                $interval = $intervals[array_rand($intervals)];

                // Последнее выполнение от 0 до interval дней назад
                $lastDoneAt = rand(0, 100) > 30 
                    ? Carbon::now()->subDays(rand(0, $interval))
                    : null;

                CareSetting::create([
                    'plant_id' => $plant->id,
                    'type' => $type,
                    'interval_days' => $interval,
                    'is_enabled' => rand(0, 100) > 10, // 90% включены
                    'last_done_at' => $lastDoneAt,
                ]);
            }
        }

        $this->command->info('Настройки ухода созданы успешно!');
    }
}