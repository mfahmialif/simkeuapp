<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

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
     * Cek apakah user saat ini berhak (Admin atau Kabag)
     */
    private function canManage(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $roleName = strtolower($user->role->name ?? '');

        return in_array($roleName, ['admin', 'kabag', 'kabag_pemasukan'], true);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian/stats
     */
    public function stats(Request $request)
    {
        $query = KeuanganPengembalianDana::query();

        // Optional filter tanggal untuk stat jika diinginkan
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!empty($startDate) && $startDate !== 'undefined' && $startDate !== 'null' &&
            !empty($endDate) && $endDate !== 'undefined' && $endDate !== 'null') {
            try {
                $query->whereBetween('tanggal', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            } catch (\Throwable $e) {
                // Ignore invalid date strings
            }
        }

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
     * GET /admin/pemasukan/mahasiswa/pengembalian
     */
    public function index(Request $request)
    {
        $query = KeuanganPengembalianDana::with('petugas:id,name,email');

        // Pencarian umum
        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('keterangan', 'like', "%{$s}%")
                    ->orWhere('nominal', 'like', "%{$s}%")
                    ->orWhereHas('petugas', function ($qp) use ($s) {
                        $qp->where('name', 'like', "%{$s}%");
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
        if ($request->filled('petugas_id') && is_numeric($request->petugas_id)) {
            $query->where('petugas_id', $request->petugas_id);
        }

        // Sorting
        $sortKey = $request->input('sort_key', 'id');
        $allowedSort = ['id', 'nominal', 'tanggal', 'created_at', 'updated_at'];
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
                'message' => 'Hanya role Admin dan Kabag yang diizinkan untuk menambah pengembalian dana.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'nominal'           => 'required|numeric|min:1',
            'tanggal'           => 'required|date',
            'file_bukti_masuk'  => 'required|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'file_bukti_keluar' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'keterangan'        => 'nullable|string|max:1000',
        ], [
            'nominal.required'          => 'Nominal wajib diisi.',
            'nominal.min'               => 'Nominal minimal Rp 1.',
            'tanggal.required'          => 'Tanggal dan waktu wajib diisi.',
            'tanggal.date'              => 'Format tanggal dan waktu tidak valid.',
            'file_bukti_masuk.required' => 'File bukti dana masuk wajib diunggah.',
            'file_bukti_masuk.file'     => 'File bukti dana masuk harus berupa file yang valid.',
            'file_bukti_masuk.mimes'    => 'Format file bukti dana masuk harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_masuk.max'      => 'Ukuran file bukti dana masuk maksimal 10MB.',
            'file_bukti_keluar.file'    => 'File bukti dana dikeluarkan harus berupa file yang valid.',
            'file_bukti_keluar.mimes'   => 'Format file bukti dana dikeluarkan harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_keluar.max'     => 'Ukuran file bukti dana dikeluarkan maksimal 10MB.',
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

        $record = KeuanganPengembalianDana::create([
            'nominal'           => $request->nominal,
            'tanggal'           => Carbon::parse($request->tanggal),
            'petugas_id'        => Auth::id(),
            'file_bukti_masuk'  => $pathMasuk,
            'file_bukti_keluar' => $pathKeluar,
            'keterangan'        => $request->keterangan,
        ]);

        return response()->json([
            'status'  => true,
            'data'    => $record->load('petugas:id,name,email'),
            'message' => 'Data pengembalian dana berhasil disimpan.',
        ], 201);
    }

    /**
     * GET /admin/pemasukan/mahasiswa/pengembalian/{id}
     */
    public function show($id)
    {
        $data = KeuanganPengembalianDana::with('petugas:id,name,email')->find($id);
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
                'message' => 'Hanya role Admin dan Kabag yang diizinkan untuk mengedit pengembalian dana.',
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
            'nominal'           => 'required|numeric|min:1',
            'tanggal'           => 'required|date',
            'file_bukti_masuk'  => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'file_bukti_keluar' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf,webp',
            'keterangan'        => 'nullable|string|max:1000',
        ], [
            'nominal.required'        => 'Nominal wajib diisi.',
            'nominal.min'             => 'Nominal minimal Rp 1.',
            'tanggal.required'        => 'Tanggal dan waktu wajib diisi.',
            'tanggal.date'            => 'Format tanggal dan waktu tidak valid.',
            'file_bukti_masuk.file'   => 'File bukti dana masuk harus berupa file yang valid.',
            'file_bukti_masuk.mimes'  => 'Format file bukti dana masuk harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_masuk.max'    => 'Ukuran file bukti dana masuk maksimal 10MB.',
            'file_bukti_keluar.file'  => 'File bukti dana dikeluarkan harus berupa file yang valid.',
            'file_bukti_keluar.mimes' => 'Format file bukti dana dikeluarkan harus berupa PDF atau Gambar (JPG, JPEG, PNG, WEBP).',
            'file_bukti_keluar.max'   => 'Ukuran file bukti dana dikeluarkan maksimal 10MB.',
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

        $record->nominal    = $request->nominal;
        $record->tanggal    = Carbon::parse($request->tanggal);
        $record->keterangan = $request->keterangan;
        $record->save();

        return response()->json([
            'status'  => true,
            'data'    => $record->load('petugas:id,name,email'),
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
                'message' => 'Hanya role Admin dan Kabag yang diizinkan untuk menghapus pengembalian dana.',
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
}
