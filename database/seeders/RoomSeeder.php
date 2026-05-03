<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Room;
use App\Models\User;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role_id', 2)->get(); // Только обычные пользователи

        $roomTemplates = [
            'Гостиная',
            'Спальня',
            'Кухня',
            'Балкон',
            'Кабинет',
            'Ванная',
            'Прихожая',
        ];

        foreach ($users as $user) {
            // Каждому пользователю создаём 2-4 случайные комнаты
            $roomCount = rand(2, 4);
            $selectedRooms = array_rand(array_flip($roomTemplates), $roomCount);

            if (!is_array($selectedRooms)) {
                $selectedRooms = [$selectedRooms];
            }

            foreach ($selectedRooms as $roomName) {
                Room::create([
                    'name' => $roomName,
                    'user_id' => $user->id,
                ]);
            }
        }

        $this->command->info('Комнаты созданы успешно!');
    }
}