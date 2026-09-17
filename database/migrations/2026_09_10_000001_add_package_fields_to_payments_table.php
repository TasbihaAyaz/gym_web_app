<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')->nullable()->after('member_id')->constrained('membership_plans')->nullOnDelete();
            $table->date('fee_start_date')->nullable()->after('payment_date');
            $table->date('fee_end_date')->nullable()->after('fee_start_date');
            $table->decimal('discount', 10, 2)->default(0)->after('amount');
        });

        // Move package link from invoice → payment for existing rows
        if (Schema::hasTable('invoices')) {
            DB::statement('
                UPDATE payments p
                INNER JOIN invoices i ON i.id = p.invoice_id
                SET p.membership_plan_id = i.membership_plan_id
                WHERE p.membership_plan_id IS NULL
                  AND i.membership_plan_id IS NOT NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('membership_plan_id');
            $table->dropColumn(['fee_start_date', 'fee_end_date', 'discount']);
        });
    }
};
