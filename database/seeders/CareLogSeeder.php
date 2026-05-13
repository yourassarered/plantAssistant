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
            'Plant looks healthy',
            'Soil was dry, watered deeply',
            'Leaves are greener after care',
            'Removed dry leaves',
            'Rotated pot by 90 degrees',
            'Applied fertilizer as instructed',
            'Watered moderately',
            'New growth noticed',
            null,
        ];

        $plants = Plant::with('careSettings')->get();
        foreach ($plants as $plant) {
            foreach ($plant->careSettings as $setting) {
                $logCount = random_int(2, 8);
                $latest = null;

                for ($i = 0; $i < $logCount; $i++) {
                    $performedAt = now()->subDays(random_int(1, 180));
                    if ($latest === null || $performedAt->greaterThan($latest)) {
                        $latest = $performedAt;
                    }

                    CareLog::create([
                        'plant_id' => $plant->id,
                        'type' => $setting->type,
                        'performed_at' => $performedAt,
                        'comment' => $comments[array_rand($comments)],
                    ]);
                }

                if ($latest !== null && ($setting->last_done_at === null || $latest->greaterThan($setting->last_done_at))) {
                    $setting->update(['last_done_at' => $latest]);
                }
            }
        }
    }
}
