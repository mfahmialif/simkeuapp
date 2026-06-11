<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('keuangan_saldo_pengeluaran')
            || Schema::hasColumn('keuangan_saldo_pengeluaran', 'petugas_id')
        ) {
            return;
        }

        Schema::table('keuangan_saldo_pengeluaran', function (Blueprint $table) {
            $table->foreignId('petugas_id')
                ->nullable()
                ->after('saldo_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        $petugasId = $this->defaultPetugasId();

        if ($petugasId) {
            DB::table('keuangan_saldo_pengeluaran')
                ->whereNull('petugas_id')
                ->update(['petugas_id' => $petugasId]);
        }
    }

    public function down(): void
    {
        if (
            ! Schema::hasTable('keuangan_saldo_pengeluaran')
            || ! Schema::hasColumn('keuangan_saldo_pengeluaran', 'petugas_id')
        ) {
            return;
        }

        Schema::table('keuangan_saldo_pengeluaran', function (Blueprint $table) {
            $table->dropForeign(['petugas_id']);
            $table->dropColumn('petugas_id');
        });
    }

    private function defaultPetugasId(): ?int
    {
        foreach (['rumahtangga', 'staff', 'kabag', 'keuangan', 'admin'] as $roleName) {
            $roleId = DB::table('role')->where('name', $roleName)->value('id');

            if (! $roleId) {
                continue;
            }

            $userId = DB::table('users')->where('role_id', $roleId)->value('id');

            if ($userId) {
                return (int) $userId;
            }
        }

        return null;
    }
};
