<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('attendances')->where('method', 'card')->update(['method' => 'manual']);

        // Keep settings in sync if default was card
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'attendance_method')->where('value', 'card')->update(['value' => 'manual']);
        }

        DB::statement("ALTER TABLE attendances MODIFY method ENUM('manual','biometric','app') NOT NULL DEFAULT 'manual'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY method ENUM('manual','card','biometric','app') NOT NULL DEFAULT 'manual'");
    }
};
