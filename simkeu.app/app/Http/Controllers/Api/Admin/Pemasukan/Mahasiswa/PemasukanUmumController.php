<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Exports\pdf\PemasukanUmumBundlingPdf;
use App\Exports\pdf\PemasukanUmumPdf;
use App\Http\Controllers\Controller;
use App\Models\KeuanganPemasukanUmum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PemasukanUmumController extends Controller
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
            1 => 'admin',
            2 => 'pimpinan',
            3 => 'keuangan',
            4 => 'kabag',
            5 => 'staff',
            13 => 'kabag_pemasukan',
            14 => 'kabag_pengeluaran',
        ];

        return $roleMap[$user->role_id] ?? '';
    }

    /**
     * Role yang diizinkan mengakses modul: admin, keuangan, kabag, staff, pimpinan
     */
    private function canAccess(): bool
    {
        $role = $this->getRoleName();
        return in_array($role, ['admin', 'keuangan', 'kabag', 'kabag_pemasukan', 'kabag_pengeluaran', 'staff', 'staff_pemasukan', 'pimpinan'], true);
    }

    /**
     * Role yang diizinkan menghapus data: HANYA admin dan kabag
     */
    private function canDelete(): bool
    {
        $role = $this->getRoleName();
        return in_array($role, ['admin', 'kabag', 'kabag_pemasukan'], true);
    }

    /**
     * Role yang diizinkan menginput dan mengubah data: admin, keuangan, kabag, staff
     */
    private function canManage(): bool
    {
        return $this->canAccess();
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pemasukan-umum/stats
     */
    public function stats(Request $request)
    {
        if (!$this->canAccess()) {
            return response()->json(['status' => false, 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $query = $this->buildFilteredQuery($request);

        $now = Carbon::now();
        $totalNominal = (double) (clone $query)->sum('nominal');
        $totalTransaksi = (clone $query)->count();

        $nominalBulanIni = (double) KeuanganPemasukanUmum::whereYear('tanggal', $now->year)
            ->whereMonth('tanggal', $now->month)
            ->sum('nominal');

        $transaksiBulanIni = KeuanganPemasukanUmum::whereYear('tanggal', $now->year)
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
            'can_delete' => $this->canDelete(),
            'can_manage' => $this->canManage(),
        ]);
    }

    /**
     * Helper untuk filter query index dan PDF bundling
     */
    private function buildFilteredQuery(Request $request)
    {
        $query = KeuanganPemasukanUmum::with(['petugas:id,name,username,email', 'jenisPembayaran:id,nama,kategori']);

        // Filter Jenis Pembayaran
        if ($request->filled('jenis_pembayaran_id') && $request->jenis_pembayaran_id !== 'all' && $request->jenis_pembayaran_id !== '') {
            $jpVal = strtolower((string) $request->jenis_pembayaran_id);
            if (in_array($jpVal, ['va_transfer_putra', 'va_transfer_putri', 'va_transfer', 'va_transfer_all'])) {
                $kategori = null;
                if ($jpVal === 'va_transfer_putra') $kategori = 'Putra';
                elseif ($jpVal === 'va_transfer_putri') $kategori = 'Putri';

                $ids = \App\Models\KeuanganJenisPembayaran::where(function ($q) {
                    $q->where('nama', 'like', '%transfer%')
                        ->orWhere('nama', 'like', '%va%')
                        ->orWhere('nama', 'like', '%virtual%');
                })
                ->when($kategori, fn($q) => $q->where('kategori', $kategori))
                ->pluck('id')->toArray();

                if (empty($ids)) {
                    $ids = $kategori === 'Putri' ? [13, 17] : ($kategori === 'Putra' ? [8, 16] : [8, 16, 13, 17]);
                }
                $query->whereIn('jenis_pembayaran_id', $ids);
            } else {
                $query->where('jenis_pembayaran_id', $request->jenis_pembayaran_id);
            }
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
            } elseif ($pt === 'va' || $pt === 'virtual') {
                $query->whereHas('jenisPembayaran', function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('nama', 'like', '%va%')
                            ->orWhere('nama', 'like', '%virtual%');
                    });
                });
            } elseif ($pt === 'va_transfer' || $pt === 'va+transfer' || $pt === 'transfer_va') {
                $query->whereHas('jenisPembayaran', function ($q) {
                    $q->where(function ($sub) {
                        $sub->where('nama', 'like', '%transfer%')
                            ->orWhere('nama', 'like', '%tf%')
                            ->orWhere('nama', 'like', '%va%')
                            ->orWhere('nama', 'like', '%virtual%');
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

        // Filter User / Petugas
        if ($request->filled('user_id') && $request->user_id !== 'all' && $request->user_id !== '') {
            $query->where('petugas_id', $request->user_id);
        }

        // Filter Jenis Kelamin Petugas (jika laporan berbasis gender)
        if ($request->filled('jenis_kelamin') && $request->jenis_kelamin !== '%' && $request->jenis_kelamin !== 'all') {
            $jk = strtolower($request->jenis_kelamin);
            $jkTarget = ($jk === 'putra' || $jk === 'laki-laki') ? 'Laki-laki' : (($jk === 'putri' || $jk === 'perempuan') ? 'Perempuan' : null);
            if ($jkTarget) {
                $query->whereHas('petugas', function ($q) use ($jkTarget) {
                    $q->whereIn('jenis_kelamin', [$jkTarget, '*']);
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
                    ->orWhereHas('petugas', function ($qp) use ($s) {
                        $qp->where('name', 'like', "%{$s}%")
                            ->orWhere('username', 'like', "%{$s}%");
                    });
            });
        }

        // Filter Tanggal
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $validStart = null;
        $validEnd = null;

        if (!empty($startDate) && $startDate !== 'undefined' && $startDate !== 'null') {
            try {
                $validStart = Carbon::parse($startDate)->startOfDay();
            } catch (\Throwable $e) {
            }
        }
        if (!empty($endDate) && $endDate !== 'undefined' && $endDate !== 'null') {
            try {
                $validEnd = Carbon::parse($endDate)->endOfDay();
            } catch (\Throwable $e) {
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

        return $query;
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pemasukan-umum
     */
    public function index(Request $request)
    {
        if (!$this->canAccess()) {
            return response()->json(['status' => false, 'message' => 'Anda tidak memiliki hak akses.'], 403);
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
            'can_delete' => $this->canDelete(),
            'can_manage' => $this->canManage(),
            'message'    => 'Data pemasukan umum berhasil dimuat.',
        ]);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pemasukan-umum/{id}
     */
    public function show($id)
    {
        if (!$this->canAccess()) {
            return response()->json(['status' => false, 'message' => 'Anda tidak memiliki hak akses.'], 403);
        }

        $item = KeuanganPemasukanUmum::with(['petugas:id,name,username,email', 'jenisPembayaran:id,nama,kategori'])->find($id);
        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        return response()->json([
            'status'     => true,
            'data'       => $item,
            'can_delete' => $this->canDelete(),
            'can_manage' => $this->canManage(),
        ]);
    }

    /**
     * POST /admin/pemasukan/mahasiswa/pemasukan-umum
     */
    public function store(Request $request)
    {
        if (!$this->canManage()) {
            return response()->json(['status' => false, 'message' => 'Anda tidak memiliki hak akses untuk menambah data.'], 403);
        }

        // Sanitasi nominal jika string berformat
        if ($request->has('nominal')) {
            $rawNom = $request->nominal;
            if (is_string($rawNom)) {
                $rawNom = preg_replace('/[^0-9]/', '', $rawNom);
            }
            $request->merge(['nominal' => $rawNom]);
        }

        $v = Validator::make($request->all(), [
            'tanggal'             => 'required|date',
            'nominal'             => 'required|numeric|min:1',
            'jenis_pembayaran_id' => 'required|exists:keuangan_jenis_pembayaran,id',
            'keterangan'          => 'nullable|string',
            'lampiran'            => 'nullable|array|max:10',
            'lampiran.*'          => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx|max:10240',
        ], [
            'tanggal.required'             => 'Tanggal dan waktu wajib diisi.',
            'tanggal.date'                 => 'Format tanggal dan waktu tidak valid.',
            'nominal.required'             => 'Nominal wajib diisi.',
            'nominal.numeric'              => 'Nominal harus berupa angka.',
            'nominal.min'                  => 'Nominal minimal Rp 1.',
            'jenis_pembayaran_id.required' => 'Jenis pembayaran wajib dipilih.',
            'jenis_pembayaran_id.exists'   => 'Jenis pembayaran yang dipilih tidak valid.',
            'lampiran.max'                 => 'Maksimal 10 file lampiran diperbolehkan.',
            'lampiran.*.max'               => 'Ukuran setiap file lampiran maksimal 10MB.',
            'lampiran.*.mimes'             => 'Format file lampiran harus berupa gambar, PDF, Word, atau Excel.',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()->first()], 422);
        }

        try {
            $parsedDate = Carbon::parse($request->tanggal);
        } catch (\Throwable $e) {
            $parsedDate = Carbon::now();
        }

        $lampiranData = [];
        if ($request->hasFile('lampiran')) {
            foreach ($request->file('lampiran') as $file) {
                if (!$file->isValid()) {
                    continue;
                }
                $origName = $file->getClientOriginalName();
                $ext = $file->getClientOriginalExtension();
                $size = $file->getSize();
                $mime = $file->getMimeType();

                $storedPath = $file->store('pemasukan_umum/lampiran', 'public');

                $lampiranData[] = [
                    'name' => $origName,
                    'path' => $storedPath,
                    'size' => $size,
                    'mime' => $mime,
                ];
            }
        }

        $noTransaksi = KeuanganPemasukanUmum::generateNoTransaksi($parsedDate);

        $record = KeuanganPemasukanUmum::create([
            'no_transaksi'        => $noTransaksi,
            'tanggal'             => $parsedDate,
            'nominal'             => (double) $request->nominal,
            'jenis_pembayaran_id' => $request->jenis_pembayaran_id,
            'keterangan'          => $request->keterangan,
            'petugas_id'          => Auth::id(),
            'lampiran'            => $lampiranData,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Data pemasukan umum berhasil disimpan.',
            'data'    => $record->load(['petugas:id,name,username,email', 'jenisPembayaran:id,nama,kategori']),
        ], 201);
    }

    /**
     * POST /admin/pemasukan/mahasiswa/pemasukan-umum/{id} (Update)
     */
    public function update(Request $request, $id)
    {
        if (!$this->canManage()) {
            return response()->json(['status' => false, 'message' => 'Anda tidak memiliki hak akses untuk mengubah data.'], 403);
        }

        $record = KeuanganPemasukanUmum::find($id);
        if (!$record) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Sanitasi nominal jika string berformat
        if ($request->has('nominal')) {
            $rawNom = $request->nominal;
            if (is_string($rawNom)) {
                $rawNom = preg_replace('/[^0-9]/', '', $rawNom);
            }
            $request->merge(['nominal' => $rawNom]);
        }

        $v = Validator::make($request->all(), [
            'tanggal'             => 'required|date',
            'nominal'             => 'required|numeric|min:1',
            'jenis_pembayaran_id' => 'required|exists:keuangan_jenis_pembayaran,id',
            'keterangan'          => 'nullable|string',
            'lampiran'            => 'nullable|array|max:10',
            'lampiran.*'          => 'file|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx|max:10240',
            'hapus_lampiran'      => 'nullable|array',
            'hapus_lampiran.*'    => 'string',
        ], [
            'tanggal.required'             => 'Tanggal dan waktu wajib diisi.',
            'tanggal.date'                 => 'Format tanggal dan waktu tidak valid.',
            'nominal.required'             => 'Nominal wajib diisi.',
            'nominal.numeric'              => 'Nominal harus berupa angka.',
            'nominal.min'                  => 'Nominal minimal Rp 1.',
            'jenis_pembayaran_id.required' => 'Jenis pembayaran wajib dipilih.',
            'jenis_pembayaran_id.exists'   => 'Jenis pembayaran yang dipilih tidak valid.',
            'lampiran.max'                 => 'Maksimal 10 file lampiran diperbolehkan.',
            'lampiran.*.max'               => 'Ukuran setiap file lampiran maksimal 10MB.',
            'lampiran.*.mimes'             => 'Format file lampiran harus berupa gambar, PDF, Word, atau Excel.',
        ]);

        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()->first()], 422);
        }

        // Ambil lampiran yang sudah ada
        $existingLampiran = $record->lampiran ?? [];
        if (is_string($existingLampiran)) {
            $existingLampiran = json_decode($existingLampiran, true) ?: [];
        }

        // Proses hapus lampiran tertentu jika diminta
        $hapusPaths = $request->input('hapus_lampiran', []);
        if (is_array($hapusPaths) && count($hapusPaths) > 0) {
            $existingLampiran = array_filter($existingLampiran, function ($item) use ($hapusPaths) {
                $path = is_array($item) ? ($item['path'] ?? '') : $item;
                if (in_array($path, $hapusPaths, true)) {
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                    return false;
                }
                return true;
            });
            $existingLampiran = array_values($existingLampiran);
        }

        // Tambahkan file lampiran baru jika ada
        if ($request->hasFile('lampiran')) {
            foreach ($request->file('lampiran') as $file) {
                if (!$file->isValid()) {
                    continue;
                }
                $origName = $file->getClientOriginalName();
                $size = $file->getSize();
                $mime = $file->getMimeType();

                $storedPath = $file->store('pemasukan_umum/lampiran', 'public');

                $existingLampiran[] = [
                    'name' => $origName,
                    'path' => $storedPath,
                    'size' => $size,
                    'mime' => $mime,
                ];
            }
        }

        try {
            $parsedDate = Carbon::parse($request->tanggal);
        } catch (\Throwable $e) {
            $parsedDate = $record->tanggal ?: Carbon::now();
        }

        $record->tanggal             = $parsedDate;
        $record->nominal             = (double) $request->nominal;
        $record->jenis_pembayaran_id = $request->jenis_pembayaran_id;
        $record->keterangan          = $request->keterangan;
        $record->lampiran            = $existingLampiran;
        $record->save();

        return response()->json([
            'status'  => true,
            'message' => 'Data pemasukan umum berhasil diperbarui.',
            'data'    => $record->load(['petugas:id,name,username,email', 'jenisPembayaran:id,nama,kategori']),
        ]);
    }

    /**
     * DELETE /admin/pemasukan/mahasiswa/pemasukan-umum/{id}
     */
    public function destroy($id)
    {
        if (!$this->canDelete()) {
            return response()->json([
                'status'  => false,
                'message' => 'Hanya role Admin dan Kabag yang diizinkan untuk menghapus data pemasukan umum.',
            ], 403);
        }

        $record = KeuanganPemasukanUmum::find($id);
        if (!$record) {
            return response()->json(['status' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        // Hapus berkas fisik lampiran
        $lampiran = $record->lampiran ?? [];
        if (is_string($lampiran)) {
            $lampiran = json_decode($lampiran, true) ?: [];
        }
        foreach ($lampiran as $item) {
            $path = is_array($item) ? ($item['path'] ?? '') : $item;
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $record->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Data pemasukan umum berhasil dihapus.',
        ]);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pemasukan-umum/file/{id}/{index}
     * Stream file lampiran inline
     */
    public function file($id, $index)
    {
        $record = KeuanganPemasukanUmum::find($id);
        if (!$record) {
            abort(404, 'Data tidak ditemukan');
        }

        $lampiran = $record->lampiran ?? [];
        if (is_string($lampiran)) {
            $lampiran = json_decode($lampiran, true) ?: [];
        }

        $item = $lampiran[(int) $index] ?? null;
        if (!$item) {
            abort(404, 'Lampiran tidak ditemukan');
        }

        $path = is_array($item) ? ($item['path'] ?? '') : $item;
        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404, 'Berkas fisik tidak ditemukan di server.');
        }

        $fullPath = Storage::disk('public')->path($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        $fileName = is_array($item) ? ($item['name'] ?? basename($path)) : basename($path);

        return response()->file($fullPath, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pemasukan-umum/{id}/pdf
     * Cetak tanda terima / bukti pemasukan umum tunggal
     */
    public function cetakPdf($id)
    {
        if (!$this->canAccess()) {
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $item = KeuanganPemasukanUmum::with(['petugas:id,name,username,email', 'jenisPembayaran:id,nama,kategori'])->find($id);
        if (!$item) {
            abort(404, 'Data pemasukan umum tidak ditemukan.');
        }

        return PemasukanUmumPdf::generate($item);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pemasukan-umum/pdf-bundling
     * Download bundling PDF dari hasil filter (1 file scrolling)
     */
    public function pdfBundling(Request $request)
    {
        if (!$this->canAccess()) {
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        $query = $this->buildFilteredQuery($request);

        // Sorting default per tanggal & id
        $query->orderBy('tanggal', 'asc')->orderBy('id', 'asc');

        $items = $query->get();

        $filterInfo = [
            'start_date' => $request->input('start_date'),
            'end_date'   => $request->input('end_date'),
            'search'     => $request->input('search'),
        ];

        return PemasukanUmumBundlingPdf::generate($items, $filterInfo);
    }
}
