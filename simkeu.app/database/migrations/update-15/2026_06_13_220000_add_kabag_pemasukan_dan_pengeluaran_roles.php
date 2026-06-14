<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'name' => 'kabag_pemasukan',
                'keterangan' => 'Kabag Pemasukan',
            ],
            [
                'name' => 'kabag_pengeluaran',
                'keterangan' => 'Kabag Pengeluaran',
            ],
        ];

        foreach ($roles as $role) {
            $exists = DB::table('role')->where('name', $role['name'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('role')->insert([
                ...$role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('role')
            ->whereIn('name', ['kabag_pemasukan', 'kabag_pengeluaran'])
            ->delete();
    }
};
