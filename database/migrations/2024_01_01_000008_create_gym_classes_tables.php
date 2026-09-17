<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('trainer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('capacity')->default(20);
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_class_id')->constrained()->cascadeOnDelete();
            $table->date('schedule_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->timestamps();
        });

        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['enrolled', 'attended', 'cancelled', 'no_show'])->default('enrolled');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamps();
            $table->unique(['gym_class_id', 'member_id', 'class_schedule_id'], 'class_member_schedule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('gym_classes');
    }
};
