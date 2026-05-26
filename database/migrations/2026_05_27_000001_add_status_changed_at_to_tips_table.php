<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tips', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });

        DB::table('tips')
            ->whereIn('status', ['accepted', 'rejected'])
            ->whereNull('status_changed_at')
            ->update(['status_changed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('tips', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });
    }
};
