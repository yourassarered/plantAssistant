<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->boolean('hidden_due_to_block')->default(false)->after('is_public_locked');
            $table->boolean('was_public_before_block')->default(false)->after('hidden_due_to_block');
        });
    }

    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropColumn(['hidden_due_to_block', 'was_public_before_block']);
        });
    }
};
