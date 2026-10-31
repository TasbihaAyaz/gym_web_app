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
            $table->boolean('is_staff')->default(false)->after('status');
        });

        // Auto-flag members that match active trainer names (they punch on the same biometric).
        $trainers = DB::table('trainers')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get(['first_name', 'last_name']);

        foreach ($trainers as $trainer) {
            $first = trim((string) $trainer->first_name);
            $last = trim((string) $trainer->last_name);
            if ($first === '') {
                continue;
            }

            DB::table('members')
                ->whereNull('deleted_at')
                ->where(function ($q) use ($first, $last) {
                    $q->where('first_name', $first);
                    if ($last !== '') {
                        $q->where('last_name', $last);
                    }
                })
                ->update(['is_staff' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('is_staff');
        });
    }
};
