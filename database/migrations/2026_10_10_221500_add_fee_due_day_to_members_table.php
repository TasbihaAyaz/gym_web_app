<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedTinyInteger('fee_due_day')->nullable()->after('joined_at');
        });

        // Seed from each member's latest active subscription end day.
        $rows = DB::table('member_subscriptions')
            ->select('member_id', DB::raw('DAY(end_date) as due_day'))
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->orderByDesc('id')
            ->get()
            ->unique('member_id');

        foreach ($rows as $row) {
            $day = (int) $row->due_day;
            if ($day >= 1 && $day <= 31) {
                DB::table('members')
                    ->where('id', $row->member_id)
                    ->update(['fee_due_day' => $day]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('fee_due_day');
        });
    }
};
