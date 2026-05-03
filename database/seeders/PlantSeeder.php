<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plant;
use App\Models\User;
use App\Models\Room;
use Carbon\Carbon;

class PlantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plantNames = [
            'Фикус Бенджамина',
            'Монстера Деликатесная',
            'Сансевиерия (Тёщин язык)',
            'Спатифиллум (Женское счастье)',
            'Драцена Маргината',
            'Хлорофитум',
            'Алоэ Вера',
            'Кактус Опунция',
            'Замиокулькас',
            'Фиалка Узамбарская',
            'Толстянка (Денежное дерево)',
            'Бегония Королевская',
            'Пеларгония (Герань)',
            'Папоротник Нефролепис',
            'Традесканция',
            'Орхидея Фаленопсис',
            'Плющ Обыкновенный',
            'Антуриум',
            'Каланхоэ',
            'Азалия',
        ];

        $users = User::where('role_id', 2)->get();

        foreach ($users as $user) {
            $rooms = Room::where('user_id', $user->id)->get();
            
            // Каждому пользователю создаём 3-8 растений
            $plantCount = rand(3, 8);

            for ($i = 0; $i < $plantCount; $i++) {
                $plantedAt = Carbon::now()->subDays(rand(30, 365));

                Plant::create([
                    'name' => $plantNames[array_rand($plantNames)],
                    'planted_at' => $plantedAt,
                    'height' => rand(10, 150) + (rand(0, 9) / 10), // 10.5 - 150.9 см
                    'is_public' => rand(0, 100) > 40, // 60% публичных
                    'user_id' => $user->id,
                    'room_id' => $rooms->isNotEmpty() ? $rooms->random()->id : null,
                ]);
            }
        }

        $this->command->info('Растения созданы успешно!');
    }
}