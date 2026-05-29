<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('warnings_count')->default(0)->after('rank');
            $table->timestamp('blocked_at')->nullable()->after('warnings_count');
            $table->text('block_reason')->nullable()->after('blocked_at');
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->timestamp('public_hidden_at')->nullable()->after('is_public');
            $table->foreignId('public_hidden_by')->nullable()->after('public_hidden_at')->constrained('users')->nullOnDelete();
            $table->text('public_hidden_reason')->nullable()->after('public_hidden_by');
            $table->boolean('is_public_locked')->default(false)->after('public_hidden_reason');
        });

        Schema::table('tips', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->string('resolution_action')->nullable()->after('admin_comment');
            $table->text('resolution_summary')->nullable()->after('resolution_action');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['resolution_action', 'resolution_summary']);
        });

        Schema::table('tips', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('plants', function (Blueprint $table) {
            $table->dropForeign(['public_hidden_by']);
            $table->dropColumn([
                'public_hidden_at',
                'public_hidden_by',
                'public_hidden_reason',
                'is_public_locked',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['warnings_count', 'blocked_at', 'block_reason']);
        });
    }
};
