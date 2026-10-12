<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cari master tagihan Double Degree Tiket Pesawat (default id 5435 atau fallback query nama)
        $targetTagihan = DB::table('keuangan_tagihan')->where('id', 5435)->first()
            ?? DB::table('keuangan_tagihan')
                ->where('nama', 'LIKE', '%TIKET PESAWAT%')
                ->where('double_degree', 1)
                ->orderBy('id', 'desc')
                ->first();

        $targetTagihanId = $targetTagihan ? $targetTagihan->id : 5435;

        // Update tagihan_id pada transaksi ID 163973 (NIM 202585030017 - Muhammad Hasan)
        // dari ID 4109 (terhapus) ke master tagihan yang valid
        DB::table('keuangan_pembayaran')
            ->where('id', 163973)
            ->where('nim', '202585030017')
            ->update([
                'tagihan_id' => $targetTagihanId,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('keuangan_pembayaran')
            ->where('id', 163973)
            ->where('nim', '202585030017')
            ->update([
                'tagihan_id' => 4109,
                'updated_at' => now(),
            ]);
    }
};
