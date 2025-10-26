<?php

namespace App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa;

use App\Services\Helper;
use App\Services\Mahasiswa;
use App\Models\KeuanganNota;
use Illuminate\Http\Request;
use App\Models\KeuanganDeposit;
use App\Exports\pdf\KwitansiPdf;
use App\Models\KeuanganKamarMhs;
use App\Models\KeuanganPembayaranIDN;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Exports\pdf\KwitansiPreviewPdf;
use App\Models\KeuanganJenisPembayaran;
use Illuminate\Support\Facades\Validator;
use App\Models\KeuanganJenisPembayaranDetail;

class PembayaranIdnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = KeuanganPembayaranIDN::join('th_akademik', 'th_akademik.id', '=', 'idn_pembayaran.th_akademik_id')
            ->join('keuangan_tagihan', 'keuangan_tagihan.id', '=', 'idn_pembayaran.tagihan_id');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('th_akademik.kode', 'LIKE', "%$request->search%")
                    ->orWhere('th_akademik.nama', 'LIKE', "%$request->search%")
                    ->orWhere('keuangan_tagihan.nama', 'LIKE', "%$request->search%")
                    ->orWhere('idn_pembayaran.bill_id', 'LIKE', "%$request->search%")
                    ->orWhere('idn_pembayaran.bill_key', 'LIKE', "%$request->search%")
                    ->orWhere('idn_pembayaran.total_bill_amount', 'LIKE', "%$request->search%");
            });
        }

        if ($request->filled('th_akademik_id')) {
            $query->where('idn_pembayaran.th_akademik_id', $request->th_akademik_id);
        }

        // Sorting
        $sortKey   = $request->input('sort_key', 'id');
        $sortOrder = $request->input('sort_order', 'desc'); // 'asc' or 'desc'

        $query->orderBy($sortKey, $sortOrder);
        $query
            ->select('idn_pembayaran.*', 'th_akademik.kode as th_akademik_kode', 'keuangan_tagihan.nama as keuangan_tagihan_nama');

        $data = $query->paginate($request->get('limit', 10));

        return response()->json([
            'status'  => true,
            'data'    => $data,
            'message' => 'Pembayaran IDN retrieved successfully',
            'req' => $request->all()
        ]);
    }

}
