<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permission = DB::table('permissions')->where('slug', 'reports.view')->first();
        $role = DB::table('roles')->where('slug', 'receptionist')->first();

        if (! $permission || ! $role) {
            return;
        }

        $exists = DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->exists();

        if (! $exists) {
            DB::table('permission_role')->insert([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('slug', 'reports.view')->first();
        $role = DB::table('roles')->where('slug', 'receptionist')->first();

        if (! $permission || ! $role) {
            return;
        }

        DB::table('permission_role')
            ->where('role_id', $role->id)
            ->where('permission_id', $permission->id)
            ->delete();
    }
};
