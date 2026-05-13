<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'plants_user_created_idx');
            $table->index(['is_public', 'created_at'], 'plants_public_created_idx');
            $table->index(['room_id', 'name'], 'plants_room_name_idx');
        });

        Schema::table('care_settings', function (Blueprint $table) {
            $table->index(['plant_id', 'type'], 'care_settings_plant_type_idx');
            $table->index(['is_enabled', 'last_done_at'], 'care_settings_enabled_last_done_idx');
        });

        Schema::table('care_logs', function (Blueprint $table) {
            $table->index(['plant_id', 'performed_at'], 'care_logs_plant_performed_idx');
        });

        Schema::table('tips', function (Blueprint $table) {
            $table->index(['plant_id', 'status', 'created_at'], 'tips_plant_status_created_idx');
            $table->index(['author_id', 'created_at'], 'tips_author_created_idx');
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->index(['plant_id', 'created_at'], 'likes_plant_created_idx');
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->index(['following_id', 'created_at'], 'follows_following_created_idx');
            $table->index(['follower_id', 'created_at'], 'follows_follower_created_idx');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'reports_status_created_idx');
            $table->index(['target_type', 'status'], 'reports_target_status_idx');
        });

        Schema::table('plant_images', function (Blueprint $table) {
            $table->index(['plant_id', 'created_at'], 'plant_images_plant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropIndex('plants_user_created_idx');
            $table->dropIndex('plants_public_created_idx');
            $table->dropIndex('plants_room_name_idx');
        });

        Schema::table('care_settings', function (Blueprint $table) {
            $table->dropIndex('care_settings_plant_type_idx');
            $table->dropIndex('care_settings_enabled_last_done_idx');
        });

        Schema::table('care_logs', function (Blueprint $table) {
            $table->dropIndex('care_logs_plant_performed_idx');
        });

        Schema::table('tips', function (Blueprint $table) {
            $table->dropIndex('tips_plant_status_created_idx');
            $table->dropIndex('tips_author_created_idx');
        });

        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex('likes_plant_created_idx');
        });

        Schema::table('follows', function (Blueprint $table) {
            $table->dropIndex('follows_following_created_idx');
            $table->dropIndex('follows_follower_created_idx');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex('reports_status_created_idx');
            $table->dropIndex('reports_target_status_idx');
        });

        Schema::table('plant_images', function (Blueprint $table) {
            $table->dropIndex('plant_images_plant_created_idx');
        });
    }
};
