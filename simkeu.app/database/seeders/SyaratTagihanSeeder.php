<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\TagihanMahasiswa;
use App\Models\SyaratTagihan;

class SyaratTagihanSeeder extends Seeder
{
    /**
     * Seed the syarat_tagihan table with unique tagihan names.
     * Auto-parse semester number from tagihan name using regex.
     */
    public function run(): void
    {
        $result = TagihanMahasiswa::getUniqueTagihan();

        if (!$result['status'] || empty($result['data'])) {
            $this->command->warn('Tidak ada data tagihan unik ditemukan.');
            return;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($result['data'] as $nama) {
            // Skip jika sudah ada
            if (SyaratTagihan::where('nama', $nama)->exists()) {
                $skipped++;
                continue;
            }

            // Auto-parse semester dari nama tagihan
            // Pattern: "SPP Semester 5", "UAS SEMESTER 3", "KRS Semester 1", dll.
            $smt = null;
            if (preg_match('/semester\s+(\d+)/i', $nama, $matches)) {
                $smt = (int) $matches[1];
            }

            SyaratTagihan::create([
                'nama'       => $nama,
                'smt'        => $smt,
                'keterangan' => $smt ? "Otomatis terdeteksi dari nama tagihan" : null,
            ]);

            $inserted++;
        }

        $this->command->info("Syarat Tagihan seeder selesai: {$inserted} inserted, {$skipped} skipped (sudah ada).");
    }
}
