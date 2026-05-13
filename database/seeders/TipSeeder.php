<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Seeder;

class TipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publicPlants = Plant::where('is_public', true)->get();
        $users = User::where('role_id', 2)->get();

        // Если нет публичных растений или пользователей - выходим
        if ($publicPlants->isEmpty() || $users->isEmpty()) {
            $this->command->warn('Нет публичных растений или пользователей для создания советов');

            return;
        }

        $tipContents = [
            'Рекомендую пересадить растение в более широкий горшок для лучшего роста корневой системы',
            'Попробуйте добавить перлит в почву для улучшения дренажа',
            'Листья желтеют? Возможно, вы переливаете растение. Сократите полив',
            'Для лучшего роста поставьте растение ближе к окну, но избегайте прямых солнечных лучей',
            'Рекомендую опрыскивать листья 2-3 раза в неделю для поддержания влажности',
            'Обратите внимание на нижние листья - если они желтеют, это нормальный процесс',
            'Попробуйте удобрение с повышенным содержанием азота для роста зелёной массы',
            'Растение вытягивается? Ему не хватает света',
            'Протирайте листья влажной тряпкой раз в неделю от пыли',
            'После пересадки не поливайте 3-4 дня, дайте корням адаптироваться',
            'Используйте отстоянную воду комнатной температуры для полива',
            'Зимой сократите полив в два раза, растение находится в покое',
            'Появились вредители? Обработайте мыльным раствором',
            'Добавьте дренажный слой на дно горшка высотой 2-3 см',
            'Не ставьте растение рядом с батареей - сухой воздух вреден',
        ];

        $statuses = ['pending', 'accepted', 'rejected'];

        // Создаём 30-50 советов
        $tipCount = rand(30, 50);

        for ($i = 0; $i < $tipCount; $i++) {
            // Выбираем случайное растение
            $plant = $publicPlants->random();

            // Находим автора, который не является владельцем растения
            $availableAuthors = $users->filter(function ($user) use ($plant) {
                return $user->id !== $plant->user_id;
            });

            if ($availableAuthors->isEmpty()) {
                $author = $users->random();
            } else {
                $author = $availableAuthors->random();
            }

            Tip::create([
                'plant_id' => $plant->id,
                'author_id' => $author->id,
                'content' => $tipContents[array_rand($tipContents)],
                'status' => $statuses[array_rand($statuses)],
            ]);
        }

        $this->command->info('Советы созданы успешно!');
    }
}
