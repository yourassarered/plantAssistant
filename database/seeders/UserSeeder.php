<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $userRole = Role::where('name', 'user')->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Администратор',
                'password' => Hash::make('password123'),
                'role_id' => $adminRole->id,
                'rank' => 100,
                'warnings_count' => 0,
                'blocked_at' => null,
                'block_reason' => null,
            ]
        );

        $presetUsers = [
            ['name' => 'Иван Петров', 'email' => 'ivan@example.com', 'rank' => 15],
            ['name' => 'Мария Сидорова', 'email' => 'maria@example.com', 'rank' => 23],
            ['name' => 'Алексей Смирнов', 'email' => 'alex@example.com', 'rank' => 8],
            ['name' => 'Елена Васильева', 'email' => 'elena@example.com', 'rank' => 12],
            ['name' => 'Дмитрий Козлов', 'email' => 'dmitry@example.com', 'rank' => 5],
            ['name' => 'Ольга Морозова', 'email' => 'olga@example.com', 'rank' => 19],
            ['name' => 'Сергей Волков', 'email' => 'sergey@example.com', 'rank' => 11],
            ['name' => 'Анна Федорова', 'email' => 'anna@example.com', 'rank' => 17],
            ['name' => 'Павел Никитин', 'email' => 'pavel@example.com', 'rank' => 9],
            ['name' => 'Наталья Орлова', 'email' => 'natalia@example.com', 'rank' => 21],
            ['name' => 'Кирилл Романов', 'email' => 'kirill@example.com', 'rank' => 6],
            ['name' => 'Татьяна Лебедева', 'email' => 'tatyana@example.com', 'rank' => 14],
            ['name' => 'Виктор Крылов', 'email' => 'viktor@example.com', 'rank' => 7],
            ['name' => 'Светлана Белова', 'email' => 'svetlana@example.com', 'rank' => 18],
            ['name' => 'Артем Гусев', 'email' => 'artem@example.com', 'rank' => 10],
        ];

        foreach ($presetUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password123'),
                    'role_id' => $userRole->id,
                    'rank' => $userData['rank'],
                    'warnings_count' => 0,
                    'blocked_at' => null,
                    'block_reason' => null,
                ]
            );
        }
    }
}
