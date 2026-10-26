<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganJenisPembayaran;
use App\Models\KeuanganSettingAkhirLaporan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingAkhirLaporanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = KeuanganSettingAkhirLaporan::with(['user:id,name,username', 'jenisPembayaran:id,nama,kategori']);

            if ($request->filled('tahun')) {
                $query->where('tahun', (int) $request->tahun);
            }

            if ($request->filled('bulan')) {
                $query->where('bulan', (int) $request->bulan);
            }

            $settings = $query->orderBy('tahun', 'desc')
                ->orderBy('bulan', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Data setting akhir laporan berhasil diambil',
                'data' => $settings,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mengambil data: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function getOptions(): JsonResponse
    {
        try {
            $rawJenisPembayaran = KeuanganJenisPembayaran::select('id', 'nama', 'kategori')->get();

            // Preset kategori pembayaran utama
            $options = [
                [
                    'value' => 'all',
                    'label' => 'Semua Jenis Pembayaran (Global)',
                    'id' => null,
                    'group' => 'Global',
                ],
                [
                    'value' => 'cash',
                    'label' => 'Cash / Tunai (Semua)',
                    'id' => null,
                    'group' => 'Kategori Utama',
                ],
                [
                    'value' => 'transfer',
                    'label' => 'Transfer Bank (Semua)',
                    'id' => null,
                    'group' => 'Kategori Utama',
                ],
                [
                    'value' => 'va_bsi',
                    'label' => 'VA BSI (Semua)',
                    'id' => null,
                    'group' => 'Kategori Utama',
                ],
                [
                    'value' => 'va_pondok',
                    'label' => 'VA Pondok (Semua)',
                    'id' => null,
                    'group' => 'Kategori Utama',
                ],
            ];

            // Tambahkan opsi spesifik dari tabel keuangan_jenis_pembayaran
            foreach ($rawJenisPembayaran as $jp) {
                $options[] = [
                    'value' => 'jp_' . $jp->id,
                    'label' => $jp->nama . ($jp->kategori ? ' (' . $jp->kategori . ')' : ''),
                    'id' => $jp->id,
                    'group' => 'Spesifik (' . ($jp->kategori ?? 'Umum') . ')',
                ];
            }

            $bulanOptions = [
                ['value' => 1, 'label' => 'Januari'],
                ['value' => 2, 'label' => 'Februari'],
                ['value' => 3, 'label' => 'Maret'],
                ['value' => 4, 'label' => 'April'],
                ['value' => 5, 'label' => 'Mei'],
                ['value' => 6, 'label' => 'Juni'],
                ['value' => 7, 'label' => 'Juli'],
                ['value' => 8, 'label' => 'Agustus'],
                ['value' => 9, 'label' => 'September'],
                ['value' => 10, 'label' => 'Oktober'],
                ['value' => 11, 'label' => 'November'],
                ['value' => 12, 'label' => 'Desember'],
            ];

            return response()->json([
                'status' => true,
                'data' => [
                    'jenis_pembayaran' => $options,
                    'bulan' => $bulanOptions,
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mengambil opsi: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tahun' => 'required|integer|min:2000|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
            'tanggal_akhir' => 'required|integer|min:1|max:31',
            'jenis_pembayaran_key' => 'required|string',
            'keterangan' => 'nullable|string|max:255',
        ]);

        try {
            $tahun = (int) $request->tahun;
            $bulan = (int) $request->bulan;
            $tanggalAkhir = (int) $request->tanggal_akhir;
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);

            if ($tanggalAkhir > $daysInMonth) {
                return response()->json([
                    'status' => false,
                    'message' => "Bulan {$bulan} tahun {$tahun} hanya memiliki {$daysInMonth} hari.",
                ], 422);
            }

            $key = strtolower(trim((string) $request->jenis_pembayaran_key));
            if (in_array($key, ['semua', 'global', '*'], true)) {
                $key = 'all';
            }
            $jpId = null;
            $jpNama = 'Semua Jenis Pembayaran';

            if ($key === 'all') {
                $jpNama = 'Semua Jenis Pembayaran (Global)';
            } elseif ($key === 'cash') {
                $jpNama = 'Cash / Tunai (Semua)';
            } elseif ($key === 'transfer') {
                $jpNama = 'Transfer Bank (Semua)';
            } elseif ($key === 'va_bsi') {
                $jpNama = 'VA BSI (Semua)';
            } elseif ($key === 'va_pondok') {
                $jpNama = 'VA Pondok (Semua)';
            } elseif (str_starts_with($key, 'jp_')) {
                $jpId = (int) substr($key, 3);
                $jp = KeuanganJenisPembayaran::find($jpId);
                if ($jp) {
                    $jpNama = $jp->nama . ($jp->kategori ? ' (' . $jp->kategori . ')' : '');
                }
            } elseif ($request->filled('jenis_pembayaran_id')) {
                $jpId = (int) $request->jenis_pembayaran_id;
                $jp = KeuanganJenisPembayaran::find($jpId);
                if ($jp) {
                    $jpNama = $jp->nama . ($jp->kategori ? ' (' . $jp->kategori . ')' : '');
                }
            }

            // Cek duplikasi
            $exists = KeuanganSettingAkhirLaporan::where('tahun', $tahun)
                ->where('bulan', $bulan)
                ->where('jenis_pembayaran_key', $key)
                ->first();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Setting untuk bulan, tahun, dan jenis pembayaran ini sudah ada. Silakan edit data yang sudah ada.',
                ], 422);
            }

            $setting = KeuanganSettingAkhirLaporan::create([
                'tahun' => $tahun,
                'bulan' => $bulan,
                'tanggal_akhir' => $tanggalAkhir,
                'jenis_pembayaran_key' => $key,
                'jenis_pembayaran_id' => $jpId,
                'jenis_pembayaran_nama' => $jpNama,
                'keterangan' => $request->keterangan,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Setting akhir laporan berhasil disimpan',
                'data' => $setting->load(['user:id,name,username']),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal menyimpan data: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $setting = KeuanganSettingAkhirLaporan::findOrFail($id);

        $request->validate([
            'tanggal_akhir' => 'required|integer|min:1|max:31',
            'keterangan' => 'nullable|string|max:255',
        ]);

        try {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $setting->bulan, $setting->tahun);
            $tanggalAkhir = (int) $request->tanggal_akhir;

            if ($tanggalAkhir > $daysInMonth) {
                return response()->json([
                    'status' => false,
                    'message' => "Bulan {$setting->bulan} tahun {$setting->tahun} hanya memiliki {$daysInMonth} hari.",
                ], 422);
            }

            $setting->tanggal_akhir = $tanggalAkhir;
            if ($request->has('keterangan')) {
                $setting->keterangan = $request->keterangan;
            }
            $setting->user_id = auth()->id();
            $setting->save();

            return response()->json([
                'status' => true,
                'message' => 'Setting akhir laporan berhasil diperbarui',
                'data' => $setting->load(['user:id,name,username']),
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal memperbarui data: ' . $th->getMessage(),
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $setting = KeuanganSettingAkhirLaporan::findOrFail($id);
            $setting->delete();

            return response()->json([
                'status' => true,
                'message' => 'Setting akhir laporan berhasil dihapus',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal menghapus data: ' . $th->getMessage(),
            ], 500);
        }
    }
}
