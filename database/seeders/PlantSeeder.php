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
        $users = User::where('role_id', $userRoleId)->orderBy('id')->get();

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
                    'planted_at' => now()->subDays(45 + (($user->id + $i) * 17 % 720)),
                    'height' => 12 + (($user->id * 9 + $i * 7) % 190) + ((($user->id + $i) % 10) / 10),
                    'is_public' => (($user->id + $i) % 100) < 68,
                    'user_id' => $user->id,
                    'room_id' => $room?->id,
                ]);
            }
        }
    }
}
