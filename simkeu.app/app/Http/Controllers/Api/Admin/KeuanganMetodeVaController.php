<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KeuanganMetodeVa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KeuanganMetodeVaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => KeuanganMetodeVa::orderBy('id')->get(),
        ]);
    }

    public function update(
        Request $request,
        KeuanganMetodeVa $paymentMethod
    ): JsonResponse {
        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('keuangan_metode_va', 'nama')->ignore($paymentMethod->id),
            ],
            'keterangan' => 'nullable|string|max:1000',
            'aktif' => 'required|boolean',
        ]);

        $paymentMethod->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'Metode pembayaran berhasil diperbarui.',
            'data' => $paymentMethod->refresh(),
        ]);
    }
}
