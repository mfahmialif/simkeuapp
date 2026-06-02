<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use App\Models\KeuanganPengeluaranDosenKegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DosenKegiatanController extends Controller
{
    private const JENIS_PEMBAYARAN = ['CUS BSI', 'Transfer'];
    private const BUKTI_TRANSFER_DIR = 'bukti-transfer/barokah-dosen/kegiatan';

    public function index(Request $request)
    {
        $query = KeuanganPengeluaranDosenKegiatan::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->orWhere('nama_kegiatan', 'LIKE', "%$request->search%")
                    ->orWhere('transport', 'LIKE', "%$request->search%")
                    ->orWhere('barokah', 'LIKE', "%$request->search%")
                    ->orWhere('total', 'LIKE', "%$request->search%")
                    ->orWhere('jenis_pembayaran', 'LIKE', "%$request->search%")
                    ->orWhere('keterangan', 'LIKE', "%$request->search%");
            });
        }

        $sortColumns = [
            'id' => 'id',
            'nama_kegiatan' => 'nama_kegiatan',
            'transport' => 'transport',
            'barokah' => 'barokah',
            'total' => 'total',
            'jenis_pembayaran' => 'jenis_pembayaran',
            'created_at' => 'created_at',
        ];

        $sortKey = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortColumns[$sortKey] ?? 'id', $sortOrder);

        $data = $query->paginate($request->get('limit', 10));
        $data->getCollection()->transform(fn ($item) => $this->appendBuktiTransferUrl($item));

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Barokah Dosen Kegiatan retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if ($this->needsBuktiTransfer($request, null)) {
            return $this->buktiTransferRequiredResponse();
        }

        $data = new KeuanganPengeluaranDosenKegiatan();
        $this->fillData($data, $request);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($data),
            'message' => 'Barokah Dosen Kegiatan created successfully',
        ], 201);
    }

    public function show($id)
    {
        $data = KeuanganPengeluaranDosenKegiatan::find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Dosen Kegiatan not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($data),
            'message' => 'Barokah Dosen Kegiatan retrieved successfully',
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $data = KeuanganPengeluaranDosenKegiatan::find($id);
        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Dosen Kegiatan not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        if ($this->needsBuktiTransfer($request, $data)) {
            return $this->buktiTransferRequiredResponse();
        }

        $this->fillData($data, $request);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $this->appendBuktiTransferUrl($data),
            'message' => 'Barokah Dosen Kegiatan updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = KeuanganPengeluaranDosenKegiatan::find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Barokah Dosen Kegiatan not found',
            ], 404);
        }

        $this->deleteBuktiTransfer($data->bukti_transfer);
        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Barokah Dosen Kegiatan deleted successfully',
        ]);
    }

    private function rules(): array
    {
        return [
            'nama_kegiatan' => 'required|string|max:255',
            'transport' => 'nullable|numeric|min:0',
            'barokah' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'jenis_pembayaran' => 'required|in:' . implode(',', self::JENIS_PEMBAYARAN),
            'bukti_transfer' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'keterangan' => 'nullable|string',
        ];
    }

    private function fillData(KeuanganPengeluaranDosenKegiatan $data, Request $request): void
    {
        $transport = $this->number($request->transport);
        $barokah = $this->number($request->barokah);

        $data->nama_kegiatan = $request->nama_kegiatan;
        $data->transport = $transport;
        $data->barokah = $barokah;
        $data->total = (int) round($transport + $barokah);
        $data->jenis_pembayaran = $request->jenis_pembayaran;
        $data->keterangan = $request->keterangan;

        if ($request->hasFile('bukti_transfer')) {
            $this->deleteBuktiTransfer($data->bukti_transfer);
            $data->bukti_transfer = $request->file('bukti_transfer')->store(self::BUKTI_TRANSFER_DIR, 'public');
        }

        if ($request->jenis_pembayaran !== 'Transfer') {
            $this->deleteBuktiTransfer($data->bukti_transfer);
            $data->bukti_transfer = null;
        }
    }

    private function needsBuktiTransfer(Request $request, ?KeuanganPengeluaranDosenKegiatan $data): bool
    {
        return $request->jenis_pembayaran === 'Transfer'
            && ! $request->hasFile('bukti_transfer')
            && ! ($data?->bukti_transfer);
    }

    private function buktiTransferRequiredResponse()
    {
        return response()->json([
            'status' => false,
            'message' => [
                'bukti_transfer' => ['Bukti transfer wajib diupload jika jenis pembayaran Transfer.'],
            ],
        ], 422);
    }

    private function appendBuktiTransferUrl($data)
    {
        $data->bukti_transfer_url = $data->bukti_transfer
            ? Storage::disk('public')->url($data->bukti_transfer)
            : null;

        return $data;
    }

    private function deleteBuktiTransfer(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }
}
