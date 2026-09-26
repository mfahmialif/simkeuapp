<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Exports\pdf\PengembalianDanaBundlingPdf;
use App\Exports\pdf\PengembalianDanaPdf;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPengembalianDana;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PengembalianDanaController extends Controller
{
    /**
     * Ambil nama role user dengan aman (termasuk fallback via role_id)
     */
    private function getRoleName(): string
    {
        $user = Auth::user();
        if (!$user) {
            return '';
        }

        if ($user->role && !empty($user->role->name)) {
            return strtolower(trim($user->role->name));
        }

        $roleMap = [
            1  => 'admin',
            2  => 'pimpinan',
            3  => 'keuangan',
            4  => 'kabag',
            5  => 'staff',
            13 => 'kabag_pemasukan',
            14 => 'kabag_pengeluaran',
        ];

        return $roleMap[$user->role_id] ?? '';
    }

    /**
     * Cek apakah user berhak mengakses modul pengembalian dana
     * Role yang diizinkan: admin, kabag, kabag_pemasukan, staff, keuangan
     */
    private function canAccess(): bool
    {
        $role = $this->getRoleName();

        return in_array($role, ['admin', 'kabag', 'kabag_pemasukan', 'staff', 'keuangan'], true);
    }

    /**
     * Cek apakah user berhak mengelola data pengembalian dana (tambah, edit, hapus)
     * Role yang diizinkan: admin, kabag, kabag_pemasukan, staff, keuangan
     */
    private function canManage(): bool
    {
        return $this->canAccess();
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian/stats
     */
    public function stats(Request $request)
    {
        if (!$this->canAccess()) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda tidak memiliki hak akses ke modul pengembalian dana.',
            ], 403);
        }

        $query = $this->buildFilteredQuery($request);

        $now = Carbon::now();
        $totalNominal = (double) (clone $query)->sum('nominal');
        $totalTransaksi = (clone $query)->count();

        $nominalBulanIni = (double) KeuanganPengembalianDana::whereYear('tanggal', $now->year)
            ->whereMonth('tanggal', $now->month)
            ->sum('nominal');

        $transaksiBulanIni = KeuanganPengembalianDana::whereYear('tanggal', $now->year)
            ->whereMonth('tanggal', $now->month)
            ->count();

        return response()->json([
            'status' => true,
            'data' => [
                'total_nominal'       => $totalNominal,
                'total_transaksi'     => $totalTransaksi,
                'nominal_bulan_ini'   => $nominalBulanIni,
                'transaksi_bulan_ini' => $transaksiBulanIni,
            ],
            'can_manage' => $this->canManage(),
        ]);
    }

    /**
     * Helper untuk filter query index dan PDF bundling
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = KeuanganPengembalianDana::with(['petugas:id,name,email', 'jenisPembayaran:id,nama,kategori']);

        // Filter Jenis Pembayaran
        if ($request->filled('jenis_pembayaran_id') && $request->jenis_pembayaran_id !== 'all' && $request->jenis_pembayaran_id !== '') {
            $query->where('jenis_pembayaran_id', $request->jenis_pembayaran_id);
        }

        // Filter Metode Pembayaran Group / Type (e.g. 'tunai', 'transfer', 'yayasan')
        if ($request->filled('payment_type') && $request->payment_type !== 'all' && $request->payment_type !== '') {
            $pt = strtolower($request->payment_type);
            if ($pt === 'tunai' || $pt === 'cash') {
                $query->whereHas('jenisPembayaran', function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('nama', 'like', '%cash%')
                            ->orWhere('nama', 'like', '%tunai%');
                    });
                });
            } elseif ($pt === 'transfer' || $pt === 'tf') {
                $query->whereHas('jenisPembayaran', function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('nama', 'like', '%transfer%')
                            ->orWhere('nama', 'like', '%tf%');
                    });
                });
            } elseif ($pt === 'yayasan' || $pt === 'yys') {
                $query->whereHas('jenisPembayaran', function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('nama', 'like', '%yayasan%')
                            ->orWhere('nama', 'like', '%yys%');
                    });
                });
            }
        }

        // Pencarian umum
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('no_transaksi', 'like', "%{$s}%")
                    ->orWhere('keterangan', 'like', "%{$s}%")
                    ->orWhere('nominal', 'like', "%{$s}%")
                    ->orWhere('id', 'like', "%{$s}%")
                    ->orWhere('nama_bank', 'like', "%{$s}%")
                    ->orWhere('no_rek_tujuan', 'like', "%{$s}%")
                    ->orWhereHas('petugas', function ($qp) use ($s) {
                        $qp->where('name', 'like', "%{$s}%");
                    })
                    ->orWhereHas('jenisPembayaran', function ($qjp) use ($s) {
                        $qjp->where('nama', 'like', "%{$s}%");
                    });
            });
        }

        // Filter Rentang Tanggal
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $validStart = null;
        $validEnd = null;

        if (!empty($startDate) && $startDate !== 'undefined' && $startDate !== 'null') {
            try {
                $validStart = Carbon::parse($startDate)->startOfDay();
            } catch (\Throwable $e) {
                $validStart = null;
            }
        }

        if (!empty($endDate) && $endDate !== 'undefined' && $endDate !== 'null') {
            try {
                $validEnd = Carbon::parse($endDate)->endOfDay();
            } catch (\Throwable $e) {
                $validEnd = null;
            }
        }

        if ($validStart && $validEnd) {
            $query->whereBetween('tanggal', [$validStart, $validEnd]);
        } elseif ($validStart) {
            $query->where('tanggal', '>=', $validStart);
        } elseif ($validEnd) {
            $query->where('tanggal', '<=', $validEnd);
        }

        // Filter Nominal
        if ($request->filled('min_nominal') && is_numeric($request->min_nominal)) {
            $query->where('nominal', '>=', (double) $request->min_nominal);
        }
        if ($request->filled('max_nominal') && is_numeric($request->max_nominal)) {
            $query->where('nominal', '<=', (double) $request->max_nominal);
        }

        // Filter Petugas
        $petugasId = $request->input('petugas_id') ?: $request->input('user_id');
        if (!empty($petugasId) && is_numeric($petugasId)) {
            $query->where('petugas_id', $petugasId);
        }

        // Filter Jenis Kelamin Petugas
        if ($request->filled('jenis_kelamin') && $request->jenis_kelamin !== '%' && $request->jenis_kelamin !== 'Semua') {
            $jk = $request->jenis_kelamin;
            $query->whereHas('petugas', function ($q) use ($jk) {
                $q->whereIn('jenis_kelamin', [$jk, '*']);
            });
        }

        return $query;
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian
     */
    public function index(Request $request)
    {
        if (!$this->canAccess()) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda tidak memiliki hak akses ke modul pengembalian dana.',
            ], 403);
        }

        $query = $this->buildFilteredQuery($request);

        // Sorting
        $sortKey = $request->input('sort_key', 'id');
        $allowedSort = ['id', 'no_transaksi', 'nominal', 'tanggal', 'created_at', 'updated_at'];
        if (!in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'id';
        }

        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortKey, $sortOrder);

        $limit = (int) $request->input('limit', 10);
        $data = $query->paginate($limit > 0 ? $limit : 10);

        return response()->json([
            'status'     => true,
            'data'       => $data,
            'can_manage' => $this->canManage(),
            'message'    => 'Data pengembalian dana berhasil diambil.',
        ]);
    }

    /**
     * POST /admin/pemasukan/mahasiswa/pengembalian
     */
    public function store(Request $request)
    {
        if (!$this->canManage()) {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya role Admin, Kabag, Staff, dan Keuangan yang diizinkan untuk menambah pengembalian dana.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nominal'             => 'required|numeric|min:1',
            'tanggal'             => 'required|date',
            'jenis_pembayaran_id' => 'required|exists:keuangan_jenis_pembayaran,id',
            'nama_bank'           => 'nullable|string|max:100',
            'no_rek_tujuan'       => 'nullable|string|max:100',
            'file_bukti_masuk'    => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'file_bukti_keluar'   => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'keterangan'          => 'nullable|string|max:1000',
        ], [
            'nominal.required'             => 'Nominal wajib diisi.',
            'nominal.min'                  => 'Nominal minimal Rp 1.',
            'tanggal.required'             => 'Tanggal dan waktu wajib diisi.',
            'tanggal.date'                 => 'Format tanggal dan waktu tidak valid.',
            'jenis_pembayaran_id.required' => 'Jenis pembayaran wajib dipilih.',
            'jenis_pembayaran_id.exists'   => 'Jenis pembayaran yang dipilih tidak valid.',
            'file_bukti_masuk.required'    => 'File bukti dana masuk wajib diunggah.',
            'file_bukti_masuk.file'        => 'File bukti dana masuk harus berupa file yang valid.',
            'file_bukti_masuk.mimes'       => 'Format file bukti dana masuk harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_masuk.max'         => 'Ukuran file bukti dana masuk maksimal 10MB.',
            'file_bukti_keluar.file'       => 'File bukti dana dikeluarkan harus berupa file yang valid.',
            'file_bukti_keluar.mimes'      => 'Format file bukti dana dikeluarkan harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_keluar.max'        => 'Ukuran file bukti dana dikeluarkan maksimal 10MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Upload file bukti masuk
        $pathMasuk = $request->file('file_bukti_masuk')->store('pengembalian-dana/masuk', 'public');

        // Upload file bukti keluar jika ada
        $pathKeluar = null;
        if ($request->hasFile('file_bukti_keluar')) {
            $pathKeluar = $request->file('file_bukti_keluar')->store('pengembalian-dana/keluar', 'public');
        }

        $parsedDate = Carbon::parse($request->tanggal);
        $noTransaksi = KeuanganPengembalianDana::generateNoTransaksi($parsedDate);

        $record = KeuanganPengembalianDana::create([
            'no_transaksi'        => $noTransaksi,
            'nominal'             => $request->nominal,
            'tanggal'             => $parsedDate,
            'petugas_id'          => Auth::id(),
            'jenis_pembayaran_id' => $request->jenis_pembayaran_id,
            'nama_bank'           => $request->nama_bank,
            'no_rek_tujuan'       => $request->no_rek_tujuan,
            'file_bukti_masuk'    => $pathMasuk,
            'file_bukti_keluar'   => $pathKeluar,
            'keterangan'          => $request->keterangan,
        ]);

        return response()->json([
            'status'  => true,
            'data'    => $record->load(['petugas:id,name,email', 'jenisPembayaran:id,nama,kategori']),
            'message' => 'Data pengembalian dana berhasil disimpan.',
        ], 201);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian/{id}
     */
    public function show($id)
    {
        if (!$this->canAccess()) {
            return response()->json([
                'status'  => false,
                'message' => 'Anda tidak memiliki hak akses ke modul pengembalian dana.',
            ], 403);
        }

        $data = KeuanganPengembalianDana::with(['petugas:id,name,email', 'jenisPembayaran:id,nama,kategori'])->find($id);
        if (!$data) {
            return response()->json([
                'status'  => false,
                'message' => 'Data pengembalian dana tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status'     => true,
            'data'       => $data,
            'can_manage' => $this->canManage(),
        ]);
    }

    /**
     * POST/PUT /admin/pemasukan/mahasiswa/pengembalian/{id}
     */
    public function update(Request $request, $id)
    {
        if (!$this->canManage()) {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya role Admin, Kabag, Staff, dan Keuangan yang diizinkan untuk mengedit pengembalian dana.',
            ], 403);
        }

        $record = KeuanganPengembalianDana::find($id);
        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Data pengembalian dana tidak ditemukan.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nominal'             => 'required|numeric|min:1',
            'tanggal'             => 'required|date',
            'jenis_pembayaran_id' => 'required|exists:keuangan_jenis_pembayaran,id',
            'nama_bank'           => 'nullable|string|max:100',
            'no_rek_tujuan'       => 'nullable|string|max:100',
            'file_bukti_masuk'    => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'file_bukti_keluar'   => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'keterangan'          => 'nullable|string|max:1000',
        ], [
            'nominal.required'             => 'Nominal wajib diisi.',
            'nominal.min'                  => 'Nominal minimal Rp 1.',
            'tanggal.required'             => 'Tanggal dan waktu wajib diisi.',
            'tanggal.date'                 => 'Format tanggal dan waktu tidak valid.',
            'jenis_pembayaran_id.required' => 'Jenis pembayaran wajib dipilih.',
            'jenis_pembayaran_id.exists'   => 'Jenis pembayaran yang dipilih tidak valid.',
            'file_bukti_masuk.file'        => 'File bukti dana masuk harus berupa file yang valid.',
            'file_bukti_masuk.mimes'       => 'Format file bukti dana masuk harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_masuk.max'         => 'Ukuran file bukti dana masuk maksimal 10MB.',
            'file_bukti_keluar.file'       => 'File bukti dana dikeluarkan harus berupa file yang valid.',
            'file_bukti_keluar.mimes'      => 'Format file bukti dana dikeluarkan harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_keluar.max'        => 'Ukuran file bukti dana dikeluarkan maksimal 10MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Cek update file bukti masuk
        if ($request->hasFile('file_bukti_masuk')) {
            if ($record->file_bukti_masuk && Storage::disk('public')->exists($record->file_bukti_masuk)) {
                Storage::disk('public')->delete($record->file_bukti_masuk);
            }
            $record->file_bukti_masuk = $request->file('file_bukti_masuk')->store('pengembalian-dana/masuk', 'public');
        }

        // Cek update file bukti keluar
        if ($request->hasFile('file_bukti_keluar')) {
            if ($record->file_bukti_keluar && Storage::disk('public')->exists($record->file_bukti_keluar)) {
                Storage::disk('public')->delete($record->file_bukti_keluar);
            }
            $record->file_bukti_keluar = $request->file('file_bukti_keluar')->store('pengembalian-dana/keluar', 'public');
        }

        $record->nominal             = $request->nominal;
        $record->tanggal             = Carbon::parse($request->tanggal);
        $record->jenis_pembayaran_id = $request->jenis_pembayaran_id;
        $record->nama_bank           = $request->nama_bank;
        $record->no_rek_tujuan       = $request->no_rek_tujuan;
        $record->keterangan          = $request->keterangan;
        $record->save();

        return response()->json([
            'status'  => true,
            'data'    => $record->load(['petugas:id,name,email', 'jenisPembayaran:id,nama,kategori']),
            'message' => 'Data pengembalian dana berhasil diperbarui.',
        ]);
    }

    /**
     * DELETE /admin/pemasukan/mahasiswa/pengembalian/{id}
     */
    public function destroy($id)
    {
        if (!$this->canManage()) {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya role Admin, Kabag, Staff, dan Keuangan yang diizinkan untuk menghapus pengembalian dana.',
            ], 403);
        }

        $record = KeuanganPengembalianDana::find($id);
        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Data pengembalian dana tidak ditemukan.',
            ], 404);
        }

        // Hapus file fisik
        if ($record->file_bukti_masuk && Storage::disk('public')->exists($record->file_bukti_masuk)) {
            Storage::disk('public')->delete($record->file_bukti_masuk);
        }
        if ($record->file_bukti_keluar && Storage::disk('public')->exists($record->file_bukti_keluar)) {
            Storage::disk('public')->delete($record->file_bukti_keluar);
        }

        $record->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Data pengembalian dana berhasil dihapus.',
        ]);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian/file/{id}/{type}
     * Stream / download file bukti
     */
    public function file($id, $type)
    {
        $record = KeuanganPengembalianDana::find($id);
        if (!$record) {
            abort(404, 'Data tidak ditemukan');
        }

        $path = $type === 'masuk' ? $record->file_bukti_masuk : $record->file_bukti_keluar;
        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404, 'File bukti tidak ditemukan di server.');
        }

        $fullPath = Storage::disk('public')->path($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian/{id}/pdf
     * Cetak dokumen bukti pengembalian dana resmi satuan
     */
    public function cetakPdf($id)
    {
        if (!$this->canAccess()) {
            abort(403, 'Anda tidak memiliki hak akses ke dokumen pengembalian dana.');
        }

        $item = KeuanganPengembalianDana::with(['petugas:id,name,email', 'jenisPembayaran:id,nama,kategori'])->find($id);
        if (!$item) {
            abort(404, 'Data pengembalian dana tidak ditemukan.');
        }

        return PengembalianDanaPdf::generate($item);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian/pdf-bundling
     * Cetak rekap dan bundling berkas pengembalian dana berdasarkan filter aktif
     */
    public function pdfBundling(Request $request)
    {
        if (!$this->canAccess()) {
            abort(403, 'Anda tidak memiliki hak akses ke dokumen rekap pengembalian dana.');
        }

        $query = $this->buildFilteredQuery($request);

        $sortKey = $request->input('sort_key', 'tanggal');
        $allowedSort = ['id', 'no_transaksi', 'nominal', 'tanggal', 'created_at', 'updated_at'];
        if (!in_array($sortKey, $allowedSort, true)) {
            $sortKey = 'tanggal';
        }
        $sortOrder = strtolower($request->input('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortKey, $sortOrder);

        $items = $query->get();

        $filterInfo = [
            'start_date' => $request->input('start_date'),
            'end_date'   => $request->input('end_date'),
        ];

        return PengembalianDanaBundlingPdf::generate($items, $filterInfo);
    }
}
