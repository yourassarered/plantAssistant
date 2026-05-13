<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderator_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 120);
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['actor_id', 'created_at'], 'mod_audit_actor_created_idx');
            $table->index(['action', 'created_at'], 'mod_audit_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderator_audit_logs');
    }
};
