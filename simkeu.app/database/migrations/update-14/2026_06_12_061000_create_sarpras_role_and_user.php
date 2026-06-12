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

        if (! DB::table('role')->whereIn('name', ['admin', 'rumahtangga'])->exists()) {
            return;
        }

        $now = now();
        $roleId = DB::table('role')->where('name', 'sarpras')->value('id');

        if ($roleId) {
            DB::table('role')
                ->where('id', $roleId)
                ->update([
                    'keterangan' => 'Sarana Prasarana',
                    'updated_at' => $now,
                ]);
        } else {
            $roleId = DB::table('role')->insertGetId([
                'name' => 'sarpras',
                'keterangan' => 'Sarana Prasarana',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $user = DB::table('users')->where('username', 'sarpras')->first();
        $data = [
            'fullname' => 'Sarana Prasarana',
            'name' => 'Sarana Prasarana',
            'email' => 'sarpras@gmail.com',
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
            'username' => 'sarpras',
            ...$data,
            'created_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('role') || ! Schema::hasTable('users')) {
            return;
        }

        $roleId = DB::table('role')->where('name', 'sarpras')->value('id');

        DB::table('users')->where('username', 'sarpras')->delete();

        if ($roleId && ! DB::table('users')->where('role_id', $roleId)->exists()) {
            DB::table('role')->where('id', $roleId)->delete();
        }
    }
};
