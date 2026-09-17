<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['membership_plan_id']);
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_plan_id')->nullable()->change();
            $table->foreign('membership_plan_id')
                ->references('id')
                ->on('membership_plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['membership_plan_id']);
        });

        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_plan_id')->nullable(false)->change();
            $table->foreign('membership_plan_id')
                ->references('id')
                ->on('membership_plans')
                ->cascadeOnDelete();
        });
    }
};
