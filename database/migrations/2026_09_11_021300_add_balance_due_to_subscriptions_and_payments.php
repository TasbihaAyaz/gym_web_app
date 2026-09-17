<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->decimal('balance_due', 10, 2)->default(0)->after('amount_paid');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->default(0)->after('discount');
        });
    }

    public function down(): void
    {
        Schema::table('member_subscriptions', function (Blueprint $table) {
            $table->dropColumn('balance_due');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
