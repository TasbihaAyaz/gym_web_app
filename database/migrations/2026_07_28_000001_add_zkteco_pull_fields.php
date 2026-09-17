<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->unsignedSmallInteger('port')->default(4370)->after('ip_address');
            $table->timestamp('last_synced_at')->nullable()->after('last_seen_at');
            $table->string('firmware', 80)->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('zkteco_devices', function (Blueprint $table) {
            $table->dropColumn(['port', 'last_synced_at', 'firmware']);
        });
    }
};
