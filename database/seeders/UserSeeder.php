<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        // Администратор
        User::create([
            'name' => 'Администратор',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role_id' => $adminRole->id,
            'rank' => 100,
        ]);

        // Обычные пользователи
        $users = [
            [
                'name' => 'Иван Петров',
                'email' => 'ivan@example.com',
                'password' => Hash::make('password123'),
                'role_id' => $userRole->id,
                'rank' => 15,
            ],
            [
                'name' => 'Мария Сидорова',
                'email' => 'maria@example.com',
                'password' => Hash::make('password123'),
                'role_id' => $userRole->id,
                'rank' => 23,
            ],
            [
                'name' => 'Алексей Смирнов',
                'email' => 'alex@example.com',
                'password' => Hash::make('password123'),
                'role_id' => $userRole->id,
                'rank' => 8,
            ],
            [
                'name' => 'Елена Васильева',
                'email' => 'elena@example.com',
                'password' => Hash::make('password123'),
                'role_id' => $userRole->id,
                'rank' => 12,
            ],
            [
                'name' => 'Дмитрий Козлов',
                'email' => 'dmitry@example.com',
                'password' => Hash::make('password123'),
                'role_id' => $userRole->id,
                'rank' => 5,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }

        $this->command->info('Пользователи созданы успешно!');
    }
}
