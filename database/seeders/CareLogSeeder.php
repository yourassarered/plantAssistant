<?php

namespace Database\Seeders;

use App\Models\CareLog;
use App\Models\Plant;
use Illuminate\Database\Seeder;

class CareLogSeeder extends Seeder
{
    public function run(): void
    {
        $comments = [
            'Почва подсохла, сделал обильный полив.',
            'Удалил сухие листья и осмотрел стебли.',
            'Повернул горшок к свету другой стороной.',
            'Внес половину дозировки удобрения.',
            'Протер листья от пыли.',
            'Заметил новый лист, растение чувствует себя хорошо.',
            'Проверил корни и дренаж, без замечаний.',
            null,
        ];

        $plants = Plant::with('careSettings')->orderBy('id')->get();
        foreach ($plants as $plant) {
            foreach ($plant->careSettings as $setting) {
                $existingCount = CareLog::where('plant_id', $plant->id)
                    ->where('type', $setting->type)
                    ->count();

                $targetCount = 3 + (($plant->id + strlen($setting->type)) % 4);
                $latest = $setting->last_done_at;

                for ($i = $existingCount; $i < $targetCount; $i++) {
                    $performedAt = now()->subDays(max(1, ($i + 1) * max($setting->interval_days ?? 7, 2) - (($plant->id + $i) % 4)));
                    if ($latest === null || $performedAt->greaterThan($latest)) {
                        $latest = $performedAt;
                    }

                    CareLog::create([
                        'plant_id' => $plant->id,
                        'type' => $setting->type,
                        'performed_at' => $performedAt,
                        'comment' => $comments[($plant->id + $i) % count($comments)],
                    ]);
                }

                if ($latest !== null && ($setting->last_done_at === null || $latest->greaterThan($setting->last_done_at))) {
                    $setting->update(['last_done_at' => $latest]);
                }
            }
        }
    }
}
