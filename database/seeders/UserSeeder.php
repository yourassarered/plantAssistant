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
            ]
        );

        $presetUsers = [
            ['name' => 'Иван Петров', 'email' => 'ivan@example.com', 'rank' => 15],
            ['name' => 'Мария Сидорова', 'email' => 'maria@example.com', 'rank' => 23],
            ['name' => 'Алексей Смирнов', 'email' => 'alex@example.com', 'rank' => 8],
            ['name' => 'Елена Васильева', 'email' => 'elena@example.com', 'rank' => 12],
            ['name' => 'Дмитрий Козлов', 'email' => 'dmitry@example.com', 'rank' => 5],
        ];

        foreach ($presetUsers as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password123'),
                    'role_id' => $userRole->id,
                    'rank' => $userData['rank'],
                ]
            );
        }

        $existingRegular = User::where('role_id', $userRole->id)->count();
        if ($existingRegular < 15) {
            User::factory()
                ->count(15 - $existingRegular)
                ->create([
                    'role_id' => $userRole->id,
                    'rank' => fake()->numberBetween(0, 35),
                ]);
        }
    }
}
