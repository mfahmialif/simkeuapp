<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('role') || ! Schema::hasTable('users')) {
            return;
        }

        if (! DB::table('role')->whereIn('name', ['admin', 'sarpras'])->exists()) {
            return;
        }

        $now = now();
        $roleId = DB::table('role')->where('name', 'transportasi')->value('id');

        if ($roleId) {
            DB::table('role')
                ->where('id', $roleId)
                ->update([
                    'keterangan' => 'Transportasi',
                    'updated_at' => $now,
                ]);
        } else {
            $roleId = DB::table('role')->insertGetId([
                'name' => 'transportasi',
                'keterangan' => 'Transportasi',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $user = DB::table('users')->where('username', 'transportasi')->first();
        $data = [
            'fullname' => 'Transportasi',
            'name' => 'Transportasi',
            'email' => 'transportasi@gmail.com',
            'password' => Hash::make('dalwa123'),
            'role_id' => $roleId,
            'jenis_kelamin' => 'Laki-laki',
            'avatar' => '/images/avatars/avatar-9.png',
            'updated_at' => $now,
        ];

        if ($user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update($data);

            return;
        }

        DB::table('users')->insert([
            'username' => 'transportasi',
            ...$data,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('role') || ! Schema::hasTable('users')) {
            return;
        }

        $roleId = DB::table('role')->where('name', 'transportasi')->value('id');

        DB::table('users')->where('username', 'transportasi')->delete();

        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('role')->where('id', $roleId)->delete();
        }
    }
};
