<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Role;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Database\Seeder;

class TipSeeder extends Seeder
{
    public function run(): void
    {
        $publicPlants = Plant::where('is_public', true)->orderBy('id')->get();
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->orderBy('id')->get();

        if ($publicPlants->isEmpty() || $users->count() < 2) {
            return;
        }

        $tipContents = [
            'Добавьте немного перлита в грунт, так корни будут лучше дышать.',
            'Поставьте растение ближе к яркому рассеянному свету, листья будут компактнее.',
            'Зимой поливайте реже и обязательно проверяйте влажность грунта пальцем.',
            'Протирайте листья раз в неделю, чтобы растение лучше фотосинтезировало.',
            'Используйте отстоянную воду комнатной температуры.',
            'Проверьте, не стал ли горшок тесным, корням может не хватать места.',
            'Раз в неделю поворачивайте горшок, чтобы крона росла ровнее.',
            'Подкармливайте слабым раствором удобрения один раз в 2-4 недели.',
            'Если кончики листьев сохнут, попробуйте повысить влажность воздуха.',
            'После полива сливайте лишнюю воду из поддона, чтобы не было застоя.',
        ];
        $statuses = ['pending', 'accepted', 'rejected'];

        foreach ($publicPlants as $plantIndex => $plant) {
            $availableAuthors = $users->where('id', '!=', $plant->user_id)->values();
            if ($availableAuthors->isEmpty()) {
                continue;
            }

            $targetCount = min(3, $availableAuthors->count());
            for ($i = 0; $i < $targetCount; $i++) {
                $author = $availableAuthors[($plantIndex + $i) % $availableAuthors->count()];
                $content = $tipContents[($plant->id + $i) % count($tipContents)];
                $status = $statuses[($plant->id + $i) % count($statuses)];

                Tip::updateOrCreate(
                    [
                        'plant_id' => $plant->id,
                        'author_id' => $author->id,
                        'content' => $content,
                    ],
                    [
                        'status' => $status,
                        'status_changed_at' => $status === 'pending'
                            ? null
                            : now()->subDays(1 + (($plant->id + $i) % 20)),
                    ]
                );
            }
        }
    }
}
