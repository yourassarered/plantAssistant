<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->get();

        $roomTemplates = ['Гостиная', 'Спальня', 'Кухня', 'Балкон', 'Кабинет', 'Ванная', 'Прихожая'];

        foreach ($users as $user) {
            $targetCount = random_int(2, 4);
            $selected = array_slice(fake()->shuffle($roomTemplates), 0, $targetCount);

            foreach ($selected as $roomName) {
                Room::firstOrCreate([
                    'user_id' => $user->id,
                    'name' => $roomName,
                ]);
            }
        }
    }
}
