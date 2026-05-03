<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Like;
use App\Models\Plant;
use App\Models\User;

class LikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publicPlants = Plant::where('is_public', true)->get();
        $users = User::where('role_id', 2)->get();

        if ($publicPlants->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Нет публичных растений или пользователей для создания лайков');
            return;
        }

        foreach ($users as $user) {
            // Находим растения, которые не принадлежат текущему пользователю
            $availablePlants = $publicPlants->filter(function ($plant) use ($user) {
                return $plant->user_id !== $user->id;
            });

            if ($availablePlants->isEmpty()) {
                continue;
            }

            // Каждый пользователь лайкает 5-15 случайных публичных растений
            $likeCount = rand(5, min(15, $availablePlants->count()));

            $plantsToLike = $availablePlants->random($likeCount);

            foreach ($plantsToLike as $plant) {
                // Проверяем, что лайк ещё не существует
                $exists = Like::where('user_id', $user->id)
                    ->where('plant_id', $plant->id)
                    ->exists();

                if (!$exists) {
                    Like::create([
                        'user_id' => $user->id,
                        'plant_id' => $plant->id,
                    ]);
                }
            }
        }

        $this->command->info('Лайки созданы успешно!');
    }
}