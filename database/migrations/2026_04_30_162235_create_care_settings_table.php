<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::create('care_settings', function (Blueprint $table) {
    $table->id();

    $table->foreignId('plant_id')->constrained()->cascadeOnDelete();

    $table->enum('type', ['watering', 'fertilizing', 'pruning', 'rotation']);

    $table->integer('interval_days')->nullable();
    $table->boolean('is_enabled')->default(true);

    $table->timestamp('last_done_at')->nullable();

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('care_settings');
    }
};
