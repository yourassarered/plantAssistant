<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            RoomSeeder::class,
            PlantSeeder::class,
            PlantImageSeeder::class,
            CareSettingSeeder::class,
            CareLogSeeder::class,
            TipSeeder::class,
            LikeSeeder::class,
            FollowSeeder::class,
            ReportSeeder::class,
            ModeratorAuditLogSeeder::class,
        ]);
    }
}
