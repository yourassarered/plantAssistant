<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::orderBy('id')->get();

        $roomTemplates = ['Гостиная', 'Спальня', 'Кухня', 'Балкон', 'Кабинет', 'Ванная', 'Прихожая'];

        foreach ($users as $user) {
            $targetCount = 2 + ($user->id % 3);
            $selectedRooms = collect($roomTemplates)
                ->shuffle()
                ->take($targetCount)
                ->values();

            foreach ($selectedRooms as $roomName) {
                Room::firstOrCreate([
                    'user_id' => $user->id,
                    'name' => $roomName,
                ]);
            }
        }
    }
}
