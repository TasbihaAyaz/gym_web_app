<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->decimal('trainer_fee', 12, 2)->default(0)->after('trainer_id');
            $table->decimal('trainer_commission', 12, 2)->default(0)->after('trainer_fee');
            $table->decimal('gym_commission', 12, 2)->default(0)->after('trainer_commission');
        });

        Schema::create('member_trainer_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('trainers')->nullOnDelete();
            $table->string('previous_trainer_name')->nullable();
            $table->decimal('trainer_fee', 12, 2)->default(0);
            $table->decimal('trainer_commission', 12, 2)->default(0);
            $table->decimal('gym_commission', 12, 2)->default(0);
            $table->string('change_type', 40)->default('change');
            $table->date('effective_date')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_trainer_histories');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['trainer_fee', 'trainer_commission', 'gym_commission']);
        });
    }
};
