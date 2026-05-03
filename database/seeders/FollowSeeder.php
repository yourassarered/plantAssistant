<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Follow;
use App\Models\User;

class FollowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role_id', 2)->get();

        if ($users->count() < 2) {
            $this->command->warn('Недостаточно пользователей для создания подписок');
            return;
        }

        foreach ($users as $user) {
            // Находим пользователей, на которых можно подписаться
            $availableUsers = $users->filter(function ($u) use ($user) {
                return $u->id !== $user->id;
            });

            if ($availableUsers->isEmpty()) {
                continue;
            }

            // Каждый пользователь подписывается на 2-5 других пользователей
            $followCount = rand(2, min(5, $availableUsers->count()));

            $usersToFollow = $availableUsers->random($followCount);

            foreach ($usersToFollow as $targetUser) {
                // Проверяем, что подписка ещё не существует
                $exists = Follow::where('follower_id', $user->id)
                    ->where('following_id', $targetUser->id)
                    ->exists();

                if (!$exists) {
                    Follow::create([
                        'follower_id' => $user->id,
                        'following_id' => $targetUser->id,
                    ]);
                }
            }
        }

        $this->command->info('Подписки созданы успешно!');
    }
}