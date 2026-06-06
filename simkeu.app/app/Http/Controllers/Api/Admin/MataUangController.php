<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataUang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MataUangController extends Controller
{
    public function index(Request $request)
    {
        $query = MataUang::query();

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term) {
                $q->where('kode', 'LIKE', "%{$term}%")
                    ->orWhere('nama', 'LIKE', "%{$term}%")
                    ->orWhere('simbol', 'LIKE', "%{$term}%");
            });
        }

        if ($request->filled('aktif')) {
            $query->where('aktif', $request->boolean('aktif'));
        }

        $sortable = ['id', 'kode', 'nama', 'simbol', 'aktif'];
        $sortKey = in_array($request->input('sort_key'), $sortable, true)
            ? $request->input('sort_key')
            : 'id';
        $sortOrder = $request->input('sort_order') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sortKey, $sortOrder);

        $limit = (int) $request->get('limit', 10);
        if ($limit === 0) {
            $items = $query->get();
            $data = [
                'current_page' => 1,
                'data' => $items,
                'first_page_url' => null,
                'from' => $items->isEmpty() ? null : 1,
                'last_page' => 1,
                'last_page_url' => null,
                'links' => [],
                'next_page_url' => null,
                'path' => $request->url(),
                'per_page' => $items->count(),
                'prev_page_url' => null,
                'to' => $items->count(),
                'total' => $items->count(),
            ];
        } else {
            $data = $query->paginate($limit);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Mata Uang retrieved successfully',
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode' => ['required', 'string', 'max:10', 'unique:mata_uang,kode'],
            'nama' => ['required', 'string', 'max:100'],
            'simbol' => ['nullable', 'string', 'max:10'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $payload['kode'] = strtoupper($payload['kode']);
        $payload['aktif'] = $request->has('aktif') ? $request->boolean('aktif') : true;

        if (MataUang::where('kode', $payload['kode'])->exists()) {
            return response()->json([
                'status' => false,
                'message' => 'Kode mata uang sudah ada',
            ], 409);
        }

        $data = MataUang::create($payload);

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Mata Uang created successfully',
        ], 201);
    }

    public function show($id)
    {
        $data = MataUang::find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Mata Uang Not Found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Mata Uang retrieved successfully',
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $data = MataUang::find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Mata Uang Not Found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'kode' => [
                'required',
                'string',
                'max:10',
                Rule::unique('mata_uang', 'kode')->ignore($data->id),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'simbol' => ['nullable', 'string', 'max:10'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $payload['kode'] = strtoupper($payload['kode']);
        $payload['aktif'] = $request->has('aktif') ? $request->boolean('aktif') : $data->aktif;

        $data->fill($payload);
        $data->save();

        return response()->json([
            'status' => true,
            'data' => $data,
            'message' => 'Mata Uang updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $data = MataUang::find($id);

        if (! $data) {
            return response()->json([
                'status' => false,
                'message' => 'Mata Uang Not Found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Mata Uang deleted successfully',
        ]);
    }
}
