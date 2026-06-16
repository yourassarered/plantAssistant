<?php

namespace Database\Seeders;

use App\Models\CareSetting;
use App\Models\Plant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CareSettingSeeder extends Seeder
{
    private const REFERENCE_DATE = '2026-06-17';

    private const TEST_CARE_SCENARIOS = [
        'a1@t.ru' => [
            'Фикус Бенджамина у окна' => [
                ['type' => 'watering', 'interval_days' => 3, 'last_done_at' => '2026-06-14'],
                ['type' => 'rotation', 'interval_days' => 7, 'last_done_at' => '2026-06-10'],
                ['type' => 'fertilizing', 'interval_days' => 14, 'last_done_at' => '2026-06-01'],
            ],
            'Монстера деликатесная' => [
                ['type' => 'watering', 'interval_days' => 4, 'last_done_at' => '2026-06-10'],
                ['type' => 'pruning', 'interval_days' => 30, 'last_done_at' => '2026-05-10'],
            ],
        ],
        'a2@t.ru' => [
            'Калатея Орбифолия' => [
                ['type' => 'watering', 'interval_days' => 5, 'last_done_at' => '2026-06-12'],
                ['type' => 'rotation', 'interval_days' => 7, 'last_done_at' => '2026-06-10'],
                ['type' => 'fertilizing', 'interval_days' => 14, 'last_done_at' => '2026-05-30'],
            ],
            'Сансевиерия Лауренти' => [
                ['type' => 'watering', 'interval_days' => 7, 'last_done_at' => '2026-06-06'],
                ['type' => 'pruning', 'interval_days' => 30, 'last_done_at' => '2026-05-18'],
            ],
        ],
    ];

    public function run(): void
    {
        $plants = Plant::orderBy('id')->get();
        $referenceDate = Carbon::parse(self::REFERENCE_DATE)->startOfDay();
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
                            : $referenceDate->copy()->subDays(min($interval, 1 + (($plant->id + $index) % max($interval, 1)))),
                    ]
                );
            }
        }

        $this->seedTestCareScenarios();
    }

    private function seedTestCareScenarios(): void
    {
        foreach (self::TEST_CARE_SCENARIOS as $email => $plantSettings) {
            foreach ($plantSettings as $plantName => $settings) {
                $plant = Plant::whereHas('user', fn ($query) => $query->where('email', $email))
                    ->where('name', $plantName)
                    ->first();

                if (! $plant) {
                    continue;
                }

                foreach ($settings as $setting) {
                    CareSetting::updateOrCreate(
                        [
                            'plant_id' => $plant->id,
                            'type' => $setting['type'],
                        ],
                        [
                            'interval_days' => $setting['interval_days'],
                            'is_enabled' => true,
                            'last_done_at' => Carbon::parse($setting['last_done_at'])->startOfDay(),
                        ]
                    );
                }
            }
        }
    }
}
