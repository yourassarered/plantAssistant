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
        $publicPlants = Plant::where('is_public', true)->get();
        $userRoleId = Role::where('name', 'user')->firstOrFail()->id;
        $users = User::where('role_id', $userRoleId)->get();

        if ($publicPlants->isEmpty() || $users->count() < 2) {
            return;
        }

        $tipContents = [
            'Добавьте перлит в грунт, чтобы улучшить дренаж.',
            'Поставьте растение ближе к яркому рассеянному свету.',
            'В холодное время года поливайте растение реже.',
            'Протирайте листья раз в неделю, чтобы убрать пыль.',
            'Используйте отстоянную воду комнатной температуры.',
            'Проверьте корни на признаки переувлажнения.',
            'Поворачивайте горшок раз в неделю для равномерного роста.',
            'Вносите разбавленное удобрение раз в 2-4 недели.',
        ];
        $statuses = ['pending', 'accepted', 'rejected'];

        $tipCount = random_int(60, 120);
        for ($i = 0; $i < $tipCount; $i++) {
            $plant = $publicPlants->random();
            $author = $users->where('id', '!=', $plant->user_id)->random();

            Tip::create([
                'plant_id' => $plant->id,
                'author_id' => $author->id,
                'content' => $tipContents[array_rand($tipContents)],
                'status' => $statuses[array_rand($statuses)],
            ]);
        }
    }
}
