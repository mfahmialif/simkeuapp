<?php

namespace App\Http\Controllers\Api\Admin\Pengeluaran;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LpjController extends Controller
{
    private const MODULES = [
        'dosen' => [
            'module_key' => 'tatap_muka',
            'title' => 'Barokah Dosen Tatapmuka',
            'rekap_table' => 'keuangan_pengeluaran_dosen_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen',
            'lpj_table' => 'keuangan_pengeluaran_dosen_lpj',
            'pegawai_tipe' => null,
            'type' => 'tatapmuka',
        ],
        'kegiatan' => [
            'module_key' => 'kegiatan',
            'title' => 'Barokah Pegawai Kegiatan',
            'rekap_table' => 'keuangan_pengeluaran_dosen_kegiatan_rekap',
            'detail_table' => 'keuangan_pengeluaran_dosen_kegiatan',
            'lpj_table' => 'keuangan_pengeluaran_dosen_kegiatan_lpj',
            'pegawai_tipe' => null,
            'type' => 'kegiatan',
        ],
        'rumah_tangga' => [
            'module_key' => 'rumah_tangga',
            'title' => 'Rumah Tangga',
            'rekap_table' => 'keuangan_pengeluaran_rumah_tangga_rekap',
            'detail_table' => 'keuangan_pengeluaran_rumah_tangga',
            'lpj_table' => 'keuangan_pengeluaran_rumah_tangga_lpj',
            'pegawai_tipe' => null,
            'type' => 'rumah-tangga',
        ],
        'sarana_prasarana' => [
            'module_key' => 'sarana_prasarana',
            'title' => 'Sarana Prasarana',
            'rekap_table' => 'keuangan_pengeluaran_sarana_prasarana_rekap',
            'detail_table' => 'keuangan_pengeluaran_sarana_prasarana',
            'lpj_table' => 'keuangan_pengeluaran_sarana_prasarana_lpj',
            'pegawai_tipe' => null,
            'type' => 'sarana-prasarana',
        ],
        'transportasi' => [
            'module_key' => 'transportasi',
            'title' => 'Transportasi',
            'rekap_table' => 'keuangan_pengeluaran_transportasi_rekap',
            'detail_table' => 'keuangan_pengeluaran_transportasi',
            'lpj_table' => 'keuangan_pengeluaran_transportasi_lpj',
            'pegawai_tipe' => null,
            'type' => 'transportasi',
        ],
        'dosen_bulanan' => [
            'module_key' => 'dosen_bulanan',
            'title' => 'Barokah Dosen Bulanan',
            'rekap_table' => 'keuangan_pengeluaran_dosen_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
            'pegawai_tipe' => 'dosen',
            'type' => 'dosen-bulanan',
        ],
        'staff_bulanan' => [
            'module_key' => 'staff_bulanan',
            'title' => 'Barokah Staff Bulanan',
            'rekap_table' => 'keuangan_pengeluaran_staff_bulanan_rekap',
            'detail_table' => 'keuangan_pengeluaran_pegawai_bulanan',
            'lpj_table' => 'keuangan_pengeluaran_pegawai_bulanan_lpj',
            'pegawai_tipe' => 'staff',
            'type' => 'bulanan',
        ],
    ];

    public function dosenShow(Request $request, $id)
    {
        return $this->showModule('dosen', $id);
    }

    public function kegiatanShow(Request $request, $id)
    {
        return $this->showModule('kegiatan', $id);
    }

    public function rumahTanggaShow(Request $request, $id)
    {
        return $this->showModule('rumah_tangga', $id);
    }

    public function saranaPrasaranaShow(Request $request, $id)
    {
        return $this->showModule('sarana_prasarana', $id);
    }

    public function transportasiShow(Request $request, $id)
    {
        return $this->showModule('transportasi', $id);
    }

    public function dosenBulananShow(Request $request, $id)
    {
        return $this->showModule('dosen_bulanan', $id);
    }

    public function staffBulananShow(Request $request, $id)
    {
        return $this->showModule('staff_bulanan', $id);
    }

    public function dosenCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'dosen', $id);
    }

    public function kegiatanCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'kegiatan', $id);
    }

    public function rumahTanggaCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'rumah_tangga', $id);
    }

    public function saranaPrasaranaCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'sarana_prasarana', $id);
    }

    public function transportasiCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'transportasi', $id);
    }

    public function dosenBulananCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'dosen_bulanan', $id);
    }

    public function staffBulananCopy(Request $request, $id)
    {
        return $this->copyModule($request, 'staff_bulanan', $id);
    }

    public function dosenUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'dosen', $id);
    }

    public function kegiatanUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'kegiatan', $id);
    }

    public function rumahTanggaUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'rumah_tangga', $id);
    }

    public function saranaPrasaranaUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'sarana_prasarana', $id);
    }

    public function transportasiUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'transportasi', $id);
    }

    public function dosenBulananUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'dosen_bulanan', $id);
    }

    public function staffBulananUpdate(Request $request, $id)
    {
        return $this->updateModule($request, 'staff_bulanan', $id);
    }

    private function showModule(string $module, $rekapId)
    {
        $source = $this->source($module);
        $rekap = $this->rekapSummary($source, $rekapId);

        if (! $rekap) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        $lpj = $this->lpjSummary($source, $rekapId, $rekap['jumlah']);

        return response()->json([
            'status' => true,
            'data' => [
                'module_key' => $source['module_key'],
                'module_type' => $source['type'],
                'title' => $source['title'],
                'rekap' => $rekap,
                'lpj' => $lpj,
                'details' => $this->lpjRows($source, $rekapId),
            ],
            'message' => 'LPJ retrieved successfully',
        ]);
    }

    private function copyModule(Request $request, string $module, $rekapId)
    {
        $validator = Validator::make($request->all(), [
            'sama_dengan_rab' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $source = $this->source($module);
        $sameAsRab = $request->boolean('sama_dengan_rab');

        $result = DB::transaction(function () use ($source, $rekapId, $sameAsRab) {
            $rekap = DB::table($source['rekap_table'])
                ->where('id', $rekapId)
                ->lockForUpdate()
                ->first();

            if (! $rekap) {
                return null;
            }

            $this->deleteLpjRows($source, $rekapId);

            $copyColumns = $this->copyColumns($source);
            $now = now();
            $rows = $this->rabDetailQuery($source, $rekapId)
                ->orderBy("{$source['detail_table']}.id")
                ->get()
                ->map(function ($row) use ($copyColumns, $now) {
                    $rowArray = (array) $row;
                    $data = Arr::only($rowArray, $copyColumns);
                    $data['rab_detail_id'] = $row->id;
                    $data['petugas_id'] = auth()->id();
                    $data['created_at'] = $now;
                    $data['updated_at'] = $now;

                    return $data;
                })
                ->values()
                ->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($source['lpj_table'])->insert($chunk);
            }

            $rekapSummary = $this->rekapSummary($source, $rekapId);
            $lpjSummary = $this->lpjSummary($source, $rekapId, $rekapSummary['jumlah']);
            $totalLpj = $lpjSummary['jumlah_data'] > 0
                ? $lpjSummary['total_lpj']
                : ($sameAsRab ? $rekapSummary['jumlah'] : 0);

            $this->upsertStatus($source, $rekapId, [
                'sama_dengan_rab' => $sameAsRab,
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $totalLpj,
                'selesai_at' => $sameAsRab ? $now : null,
            ]);

            return [
                'copied' => count($rows),
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $totalLpj,
            ];
        });

        if (! $result) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => [
                ...$result,
                'edit_required' => ! $sameAsRab,
            ],
            'message' => $sameAsRab
                ? 'LPJ berhasil disalin dari RAB dan ditandai selesai.'
                : 'LPJ berhasil disalin dari RAB. Silakan sesuaikan detail LPJ.',
        ]);
    }

    private function updateModule(Request $request, string $module, $rekapId)
    {
        $source = $this->source($module);
        $rules = [
            'items' => ['present', 'array', 'max:500'],
        ];

        if (in_array($source['type'], ['rumah-tangga', 'sarana-prasarana'], true)) {
            $rules['items.*.kelompok_anggaran'] = ['required', 'string', 'max:255', 'not_regex:/^\s*$/'];
        }
        if ($source['type'] === 'transportasi') {
            $rules['items.*.prioritas'] = ['required', Rule::in(['Tinggi', 'Sedang', 'Rendah', 'tinggi', 'sedang', 'rendah'])];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors(),
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $source, $rekapId) {
            $rekap = DB::table($source['rekap_table'])
                ->where('id', $rekapId)
                ->lockForUpdate()
                ->first();

            if (! $rekap) {
                return null;
            }

            $this->deleteLpjRows($source, $rekapId);

            $columns = Schema::getColumnListing($source['lpj_table']);
            $now = now();
            $rows = collect($request->input('items', []))
                ->map(fn ($item) => $this->normalizeLpjRow($source, (array) $item, $rekapId, $columns, $now))
                ->values()
                ->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                if ($chunk) {
                    DB::table($source['lpj_table'])->insert($chunk);
                }
            }

            $rekapSummary = $this->rekapSummary($source, $rekapId);
            $lpjSummary = $this->lpjSummary($source, $rekapId, $rekapSummary['jumlah']);
            $this->upsertStatus($source, $rekapId, [
                'sama_dengan_rab' => false,
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $lpjSummary['total_lpj'],
                'selesai_at' => now(),
            ]);

            return [
                'updated' => count($rows),
                'total_rab' => $rekapSummary['jumlah'],
                'total_lpj' => $lpjSummary['total_lpj'],
                'selisih' => $rekapSummary['jumlah'] - $lpjSummary['total_lpj'],
            ];
        });

        if (! $result) {
            return response()->json([
                'status' => false,
                'message' => 'Rekap not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => 'Detail LPJ berhasil diperbarui.',
        ]);
    }

    private function source(string $module): array
    {
        abort_unless(isset(self::MODULES[$module]), 404, 'Modul LPJ tidak ditemukan.');

        return self::MODULES[$module];
    }

    private function rabDetailQuery(array $source, $rekapId)
    {
        $query = DB::table($source['detail_table'])
            ->where("{$source['detail_table']}.rekap_id", $rekapId);

        if ($source['pegawai_tipe']) {
            $query->where("{$source['detail_table']}.pegawai_tipe", $source['pegawai_tipe']);
        }

        return $query;
    }

    private function deleteLpjRows(array $source, $rekapId): void
    {
        $query = DB::table($source['lpj_table'])->where('rekap_id', $rekapId);

        if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
            $query->where('pegawai_tipe', $source['pegawai_tipe']);
        }

        $query->delete();
    }

    private function copyColumns(array $source): array
    {
        $sourceColumns = collect(Schema::getColumnListing($source['detail_table']));
        $lpjColumns = collect(Schema::getColumnListing($source['lpj_table']));

        return $sourceColumns
            ->intersect($lpjColumns)
            ->reject(fn ($column) => in_array($column, ['id', 'created_at', 'updated_at'], true))
            ->values()
            ->all();
    }

    private function rekapSummary(array $source, $rekapId): ?array
    {
        $rekap = DB::table($source['rekap_table'])->where('id', $rekapId)->first();

        if (! $rekap) {
            return null;
        }

        $summary = $this->rabDetailQuery($source, $rekapId)
            ->selectRaw('COUNT(*) as jumlah_data, COALESCE(SUM(total), 0) as total_pengeluaran')
            ->first();

        $jumlahData = (int) ($summary->jumlah_data ?? 0);
        $totalPengeluaran = (int) ($summary->total_pengeluaran ?? 0);
        $jumlahSementara = $rekap->jumlah_sementara === null ? null : (int) $rekap->jumlah_sementara;
        $jumlah = $jumlahData > 0 ? $totalPengeluaran : ($jumlahSementara ?? 0);

        return [
            'id' => (int) $rekap->id,
            'nama' => $rekap->nama,
            'bulan_tahun' => $rekap->bulan_tahun,
            'tanggal_rekap' => $rekap->tanggal_rekap,
            'jumlah_sementara' => $jumlahSementara,
            'jumlah' => $jumlah,
            'jumlah_data' => $jumlahData,
            'total_pengeluaran' => $totalPengeluaran,
            'is_jumlah_sementara' => $jumlahData === 0,
            'keterangan' => $rekap->keterangan,
        ];
    }

    private function lpjSummary(array $source, $rekapId, int $totalRab): array
    {
        $query = DB::table($source['lpj_table'])
            ->where('rekap_id', $rekapId);

        if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
            $query->where('pegawai_tipe', $source['pegawai_tipe']);
        }

        $summary = $query
            ->selectRaw('COUNT(*) as jumlah_data, COALESCE(SUM(total), 0) as total_lpj')
            ->first();
        $status = DB::table('keuangan_pengeluaran_lpj_rekap_status')
            ->where('module_key', $source['module_key'])
            ->where('rekap_id', $rekapId)
            ->first();

        $jumlahData = (int) ($summary->jumlah_data ?? 0);
        $totalLpj = (int) ($summary->total_lpj ?? 0);

        if ($jumlahData === 0 && $status?->sama_dengan_rab) {
            $totalLpj = (int) ($status->total_lpj ?: $totalRab);
        }

        return [
            'jumlah_data' => $jumlahData,
            'total_lpj' => $totalLpj,
            'selisih' => $totalRab - $totalLpj,
            'sama_dengan_rab' => (bool) ($status?->sama_dengan_rab ?? false),
            'selesai_at' => $status?->selesai_at,
        ];
    }

    private function lpjRows(array $source, $rekapId)
    {
        $select = [
            'lpj.*',
        ];

        $query = DB::table("{$source['lpj_table']} as lpj")
            ->where('lpj.rekap_id', $rekapId);

        if (Schema::hasColumn($source['lpj_table'], 'pegawai_id')) {
            $query
                ->leftJoin('pegawai', 'pegawai.id', '=', 'lpj.pegawai_id')
                ->leftJoin('dosen', 'dosen.pegawai_id', '=', 'pegawai.id')
                ->leftJoin('staff', 'staff.pegawai_id', '=', 'pegawai.id')
                ->leftJoin('prodi', 'prodi.id', '=', 'dosen.prodi_id');

            $select[] = 'pegawai.nama as nama_pegawai';
            $select[] = 'pegawai.kode as kode_pegawai';
            $select[] = 'pegawai.tipe as tipe_pegawai';
            $select[] = 'pegawai.nama as nama_dosen';
            $select[] = 'pegawai.kode as kode_dosen';
            $select[] = 'prodi.nama as nama_prodi_dosen';
            $select[] = 'staff.jabatan as jabatan_staff';
        } else {
            $select[] = DB::raw('NULL as nama_pegawai');
            $select[] = DB::raw('NULL as kode_pegawai');
            $select[] = DB::raw('NULL as tipe_pegawai');
            $select[] = DB::raw('NULL as nama_dosen');
            $select[] = DB::raw('NULL as kode_dosen');
            $select[] = DB::raw('NULL as nama_prodi_dosen');
            $select[] = DB::raw('NULL as jabatan_staff');
        }

        $query->select($select);

        if ($source['pegawai_tipe'] && Schema::hasColumn($source['lpj_table'], 'pegawai_tipe')) {
            $query->where('lpj.pegawai_tipe', $source['pegawai_tipe']);
        }

        return $query
            ->orderBy('lpj.id')
            ->get()
            ->map(function ($row) {
                if (isset($row->lampiran) && is_string($row->lampiran)) {
                    $row->lampiran = json_decode($row->lampiran, true) ?: [];
                }

                return $row;
            })
            ->values();
    }

    private function normalizeLpjRow(
        array $source,
        array $item,
        $rekapId,
        array $columns,
        $now
    ): array {
        $row = match ($source['type']) {
            'tatapmuka' => $this->tatapmukaRow($item),
            'kegiatan' => $this->kegiatanRow($item),
            'rumah-tangga' => $this->rumahTanggaRow($item),
            'sarana-prasarana' => $this->saranaPrasaranaRow($item),
            'transportasi' => $this->transportasiRow($item),
            'dosen-bulanan' => $this->dosenBulananRow($item),
            default => $this->staffBulananRow($item),
        };

        $row['rekap_id'] = (int) $rekapId;
        $row['rab_detail_id'] = $this->nullableInt($item['rab_detail_id'] ?? null);
        $row['petugas_id'] = auth()->id();
        $row['created_at'] = $now;
        $row['updated_at'] = $now;

        if ($source['pegawai_tipe'] && in_array('pegawai_tipe', $columns, true)) {
            $row['pegawai_tipe'] = $source['pegawai_tipe'];
        }

        if (in_array('lampiran', $columns, true) && isset($row['lampiran']) && is_array($row['lampiran'])) {
            $row['lampiran'] = json_encode($row['lampiran']);
        }

        return Arr::only($row, $columns);
    }

    private function tatapmukaRow(array $item): array
    {
        $transportMotor = $this->number($item['transport_motor'] ?? $item['transport'] ?? 0);
        $hariTransportMotor = $this->number($item['hari_transport_motor'] ?? $item['hari'] ?? 0);
        $transportMobil = $this->number($item['transport_mobil'] ?? $item['transport_mobil_tanpa_tol'] ?? 0);
        $hariTransportMobil = $this->number($item['hari_transport_mobil'] ?? $item['hari_transport_mobil_tanpa_tol'] ?? 0);
        $barokahMengajarBiasa = $this->number($item['barokah_mengajar_biasa'] ?? $item['barokah'] ?? 0);
        $barokahMengajarDouble = $this->number($item['barokah_mengajar_double_degree'] ?? 0);
        $jam = $this->number($item['jam'] ?? 0);
        $jamDouble = $this->number($item['jam_mengajar_double_degree'] ?? $jam);
        $barokahUas = $this->number($item['barokah_uas'] ?? 0);
        $jumlahMahasiswaUas = $this->number($item['jumlah_mahasiswa_uas'] ?? 0);
        $barokahSempro = $this->number($item['barokah_sempro'] ?? 0);
        $jamSempro = $this->number($item['jam_sempro'] ?? ($barokahSempro > 0 ? 1 : 0));
        $total = (int) round(
            ($transportMotor * $hariTransportMotor)
            + ($transportMobil * $hariTransportMobil)
            + ($barokahMengajarBiasa * $jam)
            + ($barokahMengajarDouble * $jamDouble)
            + ($barokahUas * $jumlahMahasiswaUas)
            + ($barokahSempro * $jamSempro)
        );

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'pegawai_id' => $this->nullableInt($item['pegawai_id'] ?? null),
            'hari' => (int) round($hariTransportMotor + $hariTransportMobil),
            'hari_transport_motor' => (int) round($hariTransportMotor),
            'hari_transport_mobil' => (int) round($hariTransportMobil),
            'hari_transport_mobil_tol' => 0,
            'hari_transport_mobil_tanpa_tol' => (int) round($hariTransportMobil),
            'jam' => (int) round($jam),
            'jam_mengajar_double_degree' => (int) round($jamDouble),
            'transport' => (int) round($transportMotor + $transportMobil),
            'transport_motor' => (int) round($transportMotor),
            'transport_mobil' => (int) round($transportMobil),
            'transport_mobil_tol' => 0,
            'transport_mobil_tanpa_tol' => (int) round($transportMobil),
            'barokah' => (int) round($barokahMengajarBiasa),
            'barokah_mengajar_biasa' => (int) round($barokahMengajarBiasa),
            'barokah_mengajar_double_degree' => (int) round($barokahMengajarDouble),
            'barokah_uas' => (int) round($barokahUas),
            'jumlah_mahasiswa_uas' => (int) round($jumlahMahasiswaUas),
            'barokah_sempro' => (int) round($barokahSempro),
            'jam_sempro' => (int) round($jamSempro),
            'keterangan_sempro' => $item['keterangan_sempro'] ?? null,
            'total' => $total,
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'CUS BSI',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function kegiatanRow(array $item): array
    {
        $kategoriDetail = ($item['kategori_detail'] ?? 'pegawai') === 'non_pegawai'
            ? 'non_pegawai'
            : 'pegawai';
        $isPegawai = $kategoriDetail === 'pegawai';
        $transport = $isPegawai ? $this->number($item['transport'] ?? 0) : 0;
        $barokah = $isPegawai ? $this->number($item['barokah'] ?? 0) : 0;
        $nominal = $isPegawai ? null : (int) round($this->number($item['nominal'] ?? 0));

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'kategori_detail' => $kategoriDetail,
            'pegawai_id' => $isPegawai ? $this->nullableInt($item['pegawai_id'] ?? null) : null,
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Kegiatan',
            'transport' => (int) round($transport),
            'barokah' => (int) round($barokah),
            'nominal' => $nominal,
            'total' => $isPegawai ? (int) round($transport + $barokah) : $nominal,
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? ($isPegawai ? 'CUS BSI' : 'Tunai'),
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function rumahTanggaRow(array $item): array
    {
        $nominal = (int) round($this->number($item['nominal'] ?? $item['total'] ?? 0));
        $volume = $this->nullableInt($item['volume'] ?? null);
        $satuan = $this->nullableString($item['satuan'] ?? null);

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'kelompok_anggaran' => trim((string) ($item['kelompok_anggaran'] ?? '')),
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Rumah Tangga',
            'nominal' => $nominal,
            'volume' => $volume,
            'satuan' => $satuan,
            'total' => $nominal * ($volume ?? 1),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'Tunai',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function saranaPrasaranaRow(array $item): array
    {
        $nominal = (int) round($this->number($item['nominal'] ?? $item['total'] ?? 0));
        $volume = $this->nullableInt($item['volume'] ?? null);
        $satuan = $this->nullableString($item['satuan'] ?? null);

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'kelompok_anggaran' => trim((string) ($item['kelompok_anggaran'] ?? '')),
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Sarana Prasarana',
            'nominal' => $nominal,
            'volume' => $volume,
            'satuan' => $satuan,
            'total' => $nominal * ($volume ?? 1),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'Tunai',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function transportasiRow(array $item): array
    {
        $nominal = (int) round($this->number($item['nominal'] ?? $item['total'] ?? 0));
        $volume = $this->nullableInt($item['volume'] ?? null);
        $satuan = $this->nullableString($item['satuan'] ?? null);

        return [
            'tanggal' => $item['tanggal'] ?? now()->toDateString(),
            'prioritas' => $this->normalizePrioritas($item['prioritas'] ?? 'Sedang'),
            'nama_kegiatan' => $item['nama_kegiatan'] ?? 'LPJ Transportasi',
            'nominal' => $nominal,
            'volume' => $volume,
            'satuan' => $satuan,
            'total' => $nominal * ($volume ?? 1),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'Tunai',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function normalizePrioritas($value): string
    {
        return match (strtolower(trim((string) $value))) {
            'tinggi' => 'Tinggi',
            'rendah' => 'Rendah',
            default => 'Sedang',
        };
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function dosenBulananRow(array $item): array
    {
        $tanggal = $item['tanggal'] ?? now()->toDateString();
        $dosenTetap = $this->number($item['barokah_dosen_tetap'] ?? 0);
        $struktural = $this->number($item['barokah_struktural'] ?? 0);

        return [
            ...$this->baseBulananRow($item, $tanggal),
            'hari' => 0,
            'barokah_harian' => 0,
            'barokah_bulanan' => 0,
            'barokah_dosen_tetap' => (int) round($dosenTetap),
            'barokah_struktural' => (int) round($struktural),
            'total' => (int) round($dosenTetap + $struktural),
        ];
    }

    private function staffBulananRow(array $item): array
    {
        $tanggal = $item['tanggal'] ?? now()->toDateString();
        $hari = $this->number($item['hari'] ?? 0);
        $barokahHarian = $this->number($item['barokah_harian'] ?? 0);
        $barokahBulanan = $this->number($item['barokah_bulanan'] ?? 0);

        return [
            ...$this->baseBulananRow($item, $tanggal),
            'hari' => (int) round($hari),
            'barokah_harian' => (int) round($barokahHarian),
            'barokah_bulanan' => (int) round($barokahBulanan),
            'barokah_dosen_tetap' => 0,
            'barokah_struktural' => 0,
            'total' => (int) round(($barokahHarian * $hari) + $barokahBulanan),
        ];
    }

    private function baseBulananRow(array $item, string $tanggal): array
    {
        return [
            'tanggal' => $tanggal,
            'bulan' => (int) ($item['bulan'] ?? date('n', strtotime($tanggal))),
            'tahun' => (int) ($item['tahun'] ?? date('Y', strtotime($tanggal))),
            'pegawai_id' => $this->nullableInt($item['pegawai_id'] ?? null),
            'jenis_pembayaran' => $item['jenis_pembayaran'] ?? 'CUS BSI',
            'bukti_transfer' => $item['bukti_transfer'] ?? null,
            'keterangan' => $item['keterangan'] ?? null,
            'lampiran' => $item['lampiran'] ?? [],
        ];
    }

    private function upsertStatus(array $source, $rekapId, array $values): void
    {
        DB::table('keuangan_pengeluaran_lpj_rekap_status')->updateOrInsert(
            [
                'module_key' => $source['module_key'],
                'rekap_id' => (int) $rekapId,
            ],
            [
                ...$values,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function number($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }

    private function nullableInt($value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
