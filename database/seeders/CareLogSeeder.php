<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CareLog;
use App\Models\Plant;
use App\Models\CareSetting;
use Carbon\Carbon;

class CareLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plants = Plant::with('careSettings')->get();

        $comments = [
            'Растение выглядит здоровым',
            'Земля была сухая, полил обильно',
            'Листья стали более зелёными',
            'Убрал сухие листья',
            'Повернул на 90 градусов',
            'Добавил удобрение согласно инструкции',
            'Земля была влажная, полил умеренно',
            'Появились новые побеги',
            'Провёл опрыскивание листьев',
            'Вода стекала быстро, земля рыхлая',
            null, // иногда без комментария
        ];

        foreach ($plants as $plant) {
            foreach ($plant->careSettings as $setting) {
                // Создаём 3-10 записей в истории для каждой настройки
                $logCount = rand(3, 10);

                for ($i = 0; $i < $logCount; $i++) {
                    $performedAt = Carbon::now()->subDays(rand(1, 120));

                    CareLog::create([
                        'plant_id' => $plant->id,
                        'type' => $setting->type,
                        'performed_at' => $performedAt,
                        'comment' => $comments[array_rand($comments)],
                    ]);
                }
            }
        }

        $this->command->info('История ухода создана успешно!');
    }
}