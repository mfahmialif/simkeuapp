<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role') || ! Schema::hasTable('users')) {
            return;
        }

        $roleId = DB::table('role')->where('name', 'umum')->value('id');

        DB::table('users')->where('username', 'umum')->delete();

        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('role')->where('id', $roleId)->delete();
        }
    }

    public function down(): void
    {
        // Role khusus "umum" sudah tidak dipakai.
    }
};
