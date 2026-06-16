<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PlantSeeder extends Seeder
{
    private const REFERENCE_DATE = '2026-06-17';

    private const TEST_ADMIN_PLANTS = [
        'a1@t.ru' => [
            'Фикус Бенджамина у окна',
            'Монстера деликатесная',
            'Пилея пеперомиевидная',
        ],
        'a2@t.ru' => [
            'Калатея Орбифолия',
            'Сансевиерия Лауренти',
            'Крассула Овата',
        ],
        'a3@t.ru' => [
            'Драцена Маргината',
            'Хлорофитум хохлатый',
            'Антуриум Андре',
        ],
        'a4@t.ru' => [
            'Алоэ вера',
            'Опунция микродазис',
            'Фаленопсис белый',
        ],
    ];

    private const LEGACY_TEST_ADMIN_PLANT_PATTERNS = [
        'a1@t.ru' => [
            'Фикус%Сегодня' => 'Фикус Бенджамина у окна',
            'Монстера%Просрочка' => 'Монстера деликатесная',
            'Пилея%Лента' => 'Пилея пеперомиевидная',
        ],
        'a2@t.ru' => [
            'Калатея%Сегодня' => 'Калатея Орбифолия',
            'Сансевиерия%Просрочка' => 'Сансевиерия Лауренти',
            'Крассула%Лента' => 'Крассула Овата',
        ],
        'a3@t.ru' => [
            'Драцена%Кабинет' => 'Драцена Маргината',
            'Хлорофитум%Балкон' => 'Хлорофитум хохлатый',
            'Антуриум%Профиль' => 'Антуриум Андре',
        ],
        'a4@t.ru' => [
            'Алоэ%Ванная' => 'Алоэ вера',
            'Опунция%Окно' => 'Опунция микродазис',
            'Фаленопсис%Отчеты' => 'Фаленопсис белый',
        ],
    ];

    public function run(): void
    {
        $users = User::orderBy('id')->get();
        $referenceDate = Carbon::parse(self::REFERENCE_DATE)->startOfDay();

        $plantNames = [
            'Фикус Бенджамина',
            'Монстера деликатесная',
            'Сансевиерия',
            'Спатифиллум',
            'Драцена окаймленная',
            'Хлорофитум',
            'Алоэ вера',
            'Опунция',
            'Замиокулькас',
            'Сенполия',
            'Крассула',
            'Бегония',
            'Пеларгония',
            'Нефролепис',
            'Традесканция',
            'Фаленопсис',
            'Плющ обыкновенный',
            'Антуриум',
            'Каланхоэ',
            'Азалия',
        ];

        foreach ($users as $user) {
            $rooms = Room::where('user_id', $user->id)->orderBy('id')->get();
            $existingCount = Plant::where('user_id', $user->id)->count();
            $targetCount = 4 + ($user->id % 5);

            for ($i = $existingCount; $i < $targetCount; $i++) {
                $plantName = $plantNames[($user->id + $i) % count($plantNames)];
                $room = $rooms->isNotEmpty() ? $rooms[($user->id + $i) % $rooms->count()] : null;

                Plant::create([
                    'name' => $plantName,
                    'planted_at' => $referenceDate->copy()->subDays(45 + (($user->id + $i) * 17 % 720)),
                    'height' => 12 + (($user->id * 9 + $i * 7) % 190) + ((($user->id + $i) % 10) / 10),
                    'is_public' => (($user->id + $i) % 100) < 68,
                    'user_id' => $user->id,
                    'room_id' => $room?->id,
                ]);
            }
        }

        $this->seedTestAdminPlants($referenceDate);
    }

    private function seedTestAdminPlants(Carbon $referenceDate): void
    {
        foreach (self::TEST_ADMIN_PLANTS as $email => $plantNames) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $this->cleanupLegacyTestAdminData($user, $email);

            $room = Room::firstOrCreate([
                'user_id' => $user->id,
                'name' => 'Домашняя оранжерея',
            ]);

            foreach ($plantNames as $index => $plantName) {
                Plant::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'name' => $plantName,
                    ],
                    [
                        'planted_at' => $referenceDate->copy()->subDays(120 + ($index * 35)),
                        'height' => 32 + ($index * 11.5),
                        'is_public' => true,
                        'public_hidden_at' => null,
                        'public_hidden_by' => null,
                        'public_hidden_reason' => null,
                        'is_public_locked' => false,
                        'hidden_due_to_block' => false,
                        'was_public_before_block' => false,
                        'room_id' => $room->id,
                    ]
                );
            }
        }
    }

    private function cleanupLegacyTestAdminData(User $user, string $email): void
    {
        Room::where('user_id', $user->id)
            ->where('name', 'like', '%зона')
            ->update(['name' => 'Домашняя оранжерея']);

        $patterns = self::LEGACY_TEST_ADMIN_PLANT_PATTERNS[$email] ?? [];

        foreach ($patterns as $legacyPattern => $currentName) {
            $hasCurrentPlant = Plant::where('user_id', $user->id)
                ->where('name', $currentName)
                ->exists();

            $legacyPlants = Plant::where('user_id', $user->id)
                ->where('name', 'like', $legacyPattern);

            if ($hasCurrentPlant) {
                $legacyPlants->delete();
                continue;
            }

            $legacyPlants->update(['name' => $currentName]);
        }
    }
}
