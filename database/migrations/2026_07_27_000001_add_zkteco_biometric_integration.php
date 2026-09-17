<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('device_user_id', 32)->nullable()->unique()->after('member_code');
        });

        Schema::create('zkteco_devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 64)->unique();
            $table->string('name')->nullable();
            $table->string('model')->nullable()->default('K50');
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('att_log_stamp')->default(0);
            $table->unsignedInteger('oper_log_stamp')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('zkteco_punches', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 64)->index();
            $table->string('device_user_id', 32)->index();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->dateTime('punched_at');
            $table->unsignedTinyInteger('status')->nullable();
            $table->unsignedTinyInteger('verify_type')->nullable();
            $table->string('raw_line', 500)->nullable();
            $table->enum('applied_as', ['check_in', 'check_out', 'ignored', 'unmatched'])->default('unmatched');
            $table->timestamps();

            $table->unique(['serial_number', 'device_user_id', 'punched_at'], 'zkteco_punches_unique_punch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zkteco_punches');
        Schema::dropIfExists('zkteco_devices');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('device_user_id');
        });
    }
};
