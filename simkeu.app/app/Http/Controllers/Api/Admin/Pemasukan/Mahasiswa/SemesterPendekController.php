<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KeuanganPembayaranSemesterPendek;
use App\Services\SemesterPendek;
use App\Services\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Exports\pdf\KwitansiSemesterPendekPdf;
use App\Models\KeuanganJenisPembayaran;

class SemesterPendekController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->limit ?? 10;
        $search = $request->search;
        
        $query = KeuanganPembayaranSemesterPendek::query()
            ->with(['thAkademik', 'user', 'jenisPembayaran'])
            ->orderBy($request->sort_key ?? 'id', $request->sort_order ?? 'desc');

        if ($search) {
            $query->where('nomor', 'like', "%$search%");
        }

        if ($request->filled('periode_id')) {
            $query->where('periode_id', $request->periode_id);
        }

        if ($request->filled('jenis_pembayaran_id')) {
            $query->where('jenis_pembayaran_id', $request->jenis_pembayaran_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_akhir')) {
            $query->whereDate('tanggal', '<=', $request->tanggal_akhir);
        }

        $data = $query->paginate($limit);

        return response()->json($data);
    }

    public function searchKrs(Request $request)
    {
        $search = $request->search;
        // Hit krs endpoint from SIAKAD with search parameter
        $krsData = SemesterPendek::krs($search);

        if (!$krsData) {
            return response()->json([]);
        }

        return response()->json($krsData);
    }

    public function searchKrsData(Request $request)
    {
        $krsIds = json_decode($request->krs_id, true);
        if (!$krsIds || !is_array($krsIds)) {
            return response()->json([]);
        }

        $data = SemesterPendek::searchKrs($krsIds);
        return response()->json($data);
    }

    public function getPeriode()
    {
        $data = SemesterPendek::periode();
        return response()->json($data);
    }

    public function statistic(Request $request)
    {
        $jkUser = Helper::getJenisKelaminUser();
        $periodeId = $request->periode_id ?? 'all';
        $jenisPembayaranFilter = $request->jenis_pembayaran_id ?? 'all';
        $userIdFilter = $request->user_id ?? 'all';
        $tanggalMulai = $request->tanggal_mulai ?? null;
        $tanggalAkhir = $request->tanggal_akhir ?? null;

        $pembayaranQuery = DB::table('keuangan_pembayaran_semester_pendek');
        
        if ($jkUser->id != '%') {
            $pembayaranQuery->where('jk_id', $jkUser->id);
        }
        if ($periodeId !== 'all') {
            $pembayaranQuery->where('periode_id', $periodeId);
        }
        if ($jenisPembayaranFilter !== 'all') {
            $pembayaranQuery->where('jenis_pembayaran_id', $jenisPembayaranFilter);
        }
        if ($userIdFilter !== 'all') {
            $pembayaranQuery->where('user_id', $userIdFilter);
        }

        $today = $tanggalMulai ? $tanggalMulai : \Carbon\Carbon::today()->format('Y-m-d');
        $startOfWeek = \Carbon\Carbon::now()->startOfWeek()->format('Y-m-d');
        $startOfMonth = \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d');

        $semuaCondLaki = "jk_id = 8";
        $semuaCondPerempuan = "jk_id = 9";
        $semuaBindingsSingle = [];

        if ($tanggalMulai) {
            $semuaCondLaki .= " AND DATE(tanggal) >= ?";
            $semuaCondPerempuan .= " AND DATE(tanggal) >= ?";
            $semuaBindingsSingle[] = $tanggalMulai;
        }
        if ($tanggalAkhir) {
            $semuaCondLaki .= " AND DATE(tanggal) <= ?";
            $semuaCondPerempuan .= " AND DATE(tanggal) <= ?";
            $semuaBindingsSingle[] = $tanggalAkhir;
        }

        $selectRaw = "
            COALESCE(SUM(CASE WHEN {$semuaCondLaki} THEN jumlah ELSE 0 END), 0) as semua_laki,
            SUM(CASE WHEN {$semuaCondLaki} THEN 1 ELSE 0 END) as count_semua_laki,
            COALESCE(SUM(CASE WHEN {$semuaCondPerempuan} THEN jumlah ELSE 0 END), 0) as semua_perempuan,
            SUM(CASE WHEN {$semuaCondPerempuan} THEN 1 ELSE 0 END) as count_semua_perempuan,
            
            COALESCE(SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 8 THEN jumlah ELSE 0 END), 0) as harian_laki,
            SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 8 THEN 1 ELSE 0 END) as count_harian_laki,
            COALESCE(SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 9 THEN jumlah ELSE 0 END), 0) as harian_perempuan,
            SUM(CASE WHEN DATE(tanggal) = ? AND jk_id = 9 THEN 1 ELSE 0 END) as count_harian_perempuan,
            
            COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN jumlah ELSE 0 END), 0) as mingguan_laki,
            SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN 1 ELSE 0 END) as count_mingguan_laki,
            COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN jumlah ELSE 0 END), 0) as mingguan_perempuan,
            SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN 1 ELSE 0 END) as count_mingguan_perempuan,
            
            COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN jumlah ELSE 0 END), 0) as bulanan_laki,
            SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 8 THEN 1 ELSE 0 END) as count_bulanan_laki,
            COALESCE(SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN jumlah ELSE 0 END), 0) as bulanan_perempuan,
            SUM(CASE WHEN DATE(tanggal) >= ? AND jk_id = 9 THEN 1 ELSE 0 END) as count_bulanan_perempuan
        ";

        $bindings = array_merge(
            $semuaBindingsSingle, $semuaBindingsSingle, $semuaBindingsSingle, $semuaBindingsSingle,
            [$today, $today, $today, $today],
            [$startOfWeek, $startOfWeek, $startOfWeek, $startOfWeek],
            [$startOfMonth, $startOfMonth, $startOfMonth, $startOfMonth]
        );

        $pmb = $pembayaranQuery->selectRaw($selectRaw, $bindings)->first();

        $buildPeriod = function($laki, $countLaki, $perempuan, $countPerempuan) use ($jkUser) {
            $result = [];
            $keseluruhan = 0;
            $countKeseluruhan = 0;

            if ($jkUser->id == 8 || $jkUser->id === '%') {
                $result['Laki-laki'] = ['value' => (float)$laki, 'change' => (int)$countLaki];
                $keseluruhan += (float)$laki;
                $countKeseluruhan += (int)$countLaki;
            }
            if ($jkUser->id == 9 || $jkUser->id === '%') {
                $result['Perempuan'] = ['value' => (float)$perempuan, 'change' => (int)$countPerempuan];
                $keseluruhan += (float)$perempuan;
                $countKeseluruhan += (int)$countPerempuan;
            }
            $result['Keseluruhan'] = ['value' => $keseluruhan, 'change' => $countKeseluruhan];

            return $result;
        };

        return response()->json([
            'status' => true,
            'message' => 'Data statistik berhasil diambil',
            'data' => [
                'Harian' => $buildPeriod($pmb->harian_laki, $pmb->count_harian_laki, $pmb->harian_perempuan, $pmb->count_harian_perempuan),
                'Mingguan' => $buildPeriod($pmb->mingguan_laki, $pmb->count_mingguan_laki, $pmb->mingguan_perempuan, $pmb->count_mingguan_perempuan),
                'Bulanan' => $buildPeriod($pmb->bulanan_laki, $pmb->count_bulanan_laki, $pmb->bulanan_perempuan, $pmb->count_bulanan_perempuan),
                'Semua' => $buildPeriod($pmb->semua_laki, $pmb->count_semua_laki, $pmb->semua_perempuan, $pmb->count_semua_perempuan),
            ],
        ]);
    }

    public function getRiwayat($krsId)
    {
        $riwayat = KeuanganPembayaranSemesterPendek::where('krs_id', $krsId)
            ->with('user')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => 'true',
            'riwayat' => $riwayat
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'krs_id' => 'required',
            'th_akademik_id' => 'required',
            'jumlah' => 'required|numeric|min:0',
            'jk_id' => 'required',
            'jenis_pembayaran_id' => 'nullable',
            'deposit' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $tanggal = $request->filled('tanggal')
                ? Carbon::parse($request->tanggal)->format('Y-m-d H:i:s')
                : Carbon::now()->format('Y-m-d H:i:s');
            $jenisPembayaranId = $this->resolveJenisPembayaranId($request);
            $jumlahPembayaran = $this->isDhomin($request) ? 0 : (float) $request->jumlah;
            $targetPembayaranKrs = null;

            if ($this->isSamahah($request) || $this->isDhomin($request)) {
                $targetPembayaranKrs = $this->resolveTargetLunasKrs(
                    $request->krs_id,
                    $request->input('total_pembayaran', $jumlahPembayaran)
                );
            }

            // Generate nota dari tabel semester pendek
            $nomor = Helper::generateNotaSp($tanggal, $request->jk_id);
            $nomor = 'SP-' . $nomor;

            $pembayaranId = null;

            // Insert pembayaran biasa (jika jumlah > 0)
            if ($jumlahPembayaran > 0 || $this->isDhomin($request)) {
                $pembayaran = KeuanganPembayaranSemesterPendek::create([
                    'nomor' => $nomor,
                    'tanggal' => $tanggal,
                    'th_akademik_id' => $request->th_akademik_id,
                    'periode_id' => $request->periode_id,
                    'krs_id' => $request->krs_id,
                    'jumlah' => $jumlahPembayaran,
                    'jk_id' => $request->jk_id,
                    'user_id' => \Auth::user()->id ?? 1,
                    'jenis_pembayaran_id' => $jenisPembayaranId,
                ]);
                $pembayaranId = $pembayaran->id;
            }

            // Insert deposit record (jika deposit > 0)
            $depositAmount = $this->isDhomin($request) ? 0 : $request->input('deposit', 0);
            if ($depositAmount > 0) {
                // Auto-detect jenis pembayaran deposit
                $jk = Helper::getJenisKelaminUser();
                $jenisPembayaranDeposit = KeuanganJenisPembayaran::where([
                    ['nama', 'LIKE', "%deposit%"],
                    ['kategori', 'LIKE', "%$jk->kategori%"],
                ])->first();

                $depositJpId = $jenisPembayaranDeposit ? $jenisPembayaranDeposit->id : $request->jenis_pembayaran_id;

                $pembayaranDeposit = KeuanganPembayaranSemesterPendek::create([
                    'nomor' => $nomor, // nomor sama agar kwitansi bisa munculkan 2 baris
                    'tanggal' => $tanggal,
                    'th_akademik_id' => $request->th_akademik_id,
                    'periode_id' => $request->periode_id,
                    'krs_id' => $request->krs_id,
                    'jumlah' => $depositAmount,
                    'jk_id' => $request->jk_id,
                    'user_id' => \Auth::user()->id ?? 1,
                    'jenis_pembayaran_id' => $depositJpId,
                ]);

                if (!$pembayaranId) {
                    $pembayaranId = $pembayaranDeposit->id;
                }

                // Kurangi catatan deposit
                $nim = null;
                try {
                    $krsData = SemesterPendek::krsDetail($request->krs_id);
                    $nim = $krsData->nim ?? null;
                } catch (\Exception $e) {}

                if ($nim) {
                    $deposit = \App\Models\KeuanganDeposit::where('nim', $nim)->first();
                    if ($deposit) {
                        $deposit->jumlah = max(0, $deposit->jumlah - $depositAmount);
                        $deposit->save();
                    }
                }
            }

            // Hitung total seluruh pembayaran yang sudah masuk untuk krs_id ini
            $totalSudahBayar = KeuanganPembayaranSemesterPendek::where('krs_id', $request->krs_id)->sum('jumlah');

            // Update ke SIAKAD
            SemesterPendek::updatePembayaranKrs($request->krs_id, $targetPembayaranKrs ?? $totalSudahBayar);

            DB::commit();

            return response()->json([
                'status' => 'true',
                'message' => 'Pembayaran Semester Pendek berhasil disimpan',
                'id' => $pembayaranId,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'false',
                'message' => 'Gagal menyimpan pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $data = KeuanganPembayaranSemesterPendek::with(['jenisPembayaran', 'user'])->findOrFail($id);
        
        // Fetch KRS info from SIAKAD, gracefully handle failure
        $krsData = null;
        try {
            $krsData = SemesterPendek::krsDetail($data->krs_id);
        } catch (\Exception $e) {
            // SIAKAD might be unreachable, continue without KRS data
        }

        return response()->json([
            'status' => 'true',
            'data' => [
                'pembayaran' => $data,
                'krs' => $krsData
            ]
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'jumlah' => 'required|numeric|min:0',
            'jenis_pembayaran_id' => 'nullable'
        ]);

        try {
            DB::beginTransaction();

            $pembayaran = KeuanganPembayaranSemesterPendek::findOrFail($id);
            $jenisPembayaranId = $this->resolveJenisPembayaranId($request);
            $jumlahPembayaran = $this->isDhomin($request) ? 0 : (float) $request->jumlah;
            $targetPembayaranKrs = null;

            if ($this->isSamahah($request) || $this->isDhomin($request)) {
                $targetPembayaranKrs = $this->resolveTargetLunasKrs(
                    $pembayaran->krs_id,
                    $request->input('total_pembayaran', $jumlahPembayaran)
                );
            }
            
            $updateData = [
                'jumlah' => $jumlahPembayaran,
                'jenis_pembayaran_id' => $jenisPembayaranId,
            ];

            // Update tanggal if provided
            if ($request->filled('tanggal')) {
                $updateData['tanggal'] = $request->tanggal;
            }

            $pembayaran->update($updateData);

            // Hitung total seluruh pembayaran yang sudah masuk untuk krs_id ini
            $totalSudahBayar = KeuanganPembayaranSemesterPendek::where('krs_id', $pembayaran->krs_id)->sum('jumlah');

            // Update ke SIAKAD
            SemesterPendek::updatePembayaranKrs($pembayaran->krs_id, $targetPembayaranKrs ?? $totalSudahBayar);

            DB::commit();

            return response()->json([
                'status' => 'true',
                'message' => 'Data pembayaran Semester Pendek berhasil diperbarui',
                'data' => $pembayaran
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'false',
                'message' => 'Gagal memperbarui pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $pembayaran = KeuanganPembayaranSemesterPendek::findOrFail($id);
            $krs_id = $pembayaran->krs_id;
            
            DB::beginTransaction();
            $pembayaran->delete();

            // Recalculate total
            $totalSudahBayar = KeuanganPembayaranSemesterPendek::where('krs_id', $krs_id)->sum('jumlah');

            // Update ke SIAKAD
            SemesterPendek::updatePembayaranKrs($krs_id, $totalSudahBayar);

            DB::commit();

            return response()->json([
                'status' => 'true',
                'message' => 'Pembayaran berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'false',
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function kwitansi($id)
    {
        $pembayaran = KeuanganPembayaranSemesterPendek::with(['user', 'jenisPembayaran'])->findOrFail($id);
        return KwitansiSemesterPendekPdf::pdf($pembayaran);
    }

    private function isSamahah(Request $request): bool
    {
        return filter_var($request->input('samahah'), FILTER_VALIDATE_BOOL);
    }

    private function isDhomin(Request $request): bool
    {
        return filter_var($request->input('dhomin'), FILTER_VALIDATE_BOOL);
    }

    private function resolveJenisPembayaranId(Request $request): int|string
    {
        if (! $this->isDhomin($request)) {
            return $request->jenis_pembayaran_id;
        }

        $kategori = Helper::getJenisKelaminUser()->kategori;
        $jenisPembayaran = KeuanganJenisPembayaran::where('nama', 'LIKE', '%yayasan%')
            ->where('kategori', 'LIKE', "%{$kategori}%")
            ->first()
            ?? KeuanganJenisPembayaran::where('nama', 'LIKE', '%yayasan%')->first();

        if (! $jenisPembayaran) {
            throw new \Exception('Jenis pembayaran Yayasan tidak ditemukan.');
        }

        return $jenisPembayaran->id;
    }

    private function resolveTargetLunasKrs($krsId, $fallbackJumlah = 0): float
    {
        $krsData = SemesterPendek::krsDetail($krsId);
        $target = (float) ($krsData->total_pembayaran ?? 0);
        if ($target <= 0) {
            $target = (float) $fallbackJumlah;
        }

        return $target;
    }
}
