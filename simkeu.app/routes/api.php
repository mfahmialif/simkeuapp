<?php

use App\Http\Controllers\Api\Admin\AbsensiController;
use App\Models\FormSchadule;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BsiPaymentController as PublicBsiPaymentController;
use App\Http\Controllers\Api\HelperController;
use App\Http\Controllers\Api\Admin\RefController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\DosenController;
use App\Http\Controllers\Api\Admin\ProdiController;
use App\Http\Controllers\Api\Admin\JadwalController;
use App\Http\Controllers\Api\Admin\ProfilController;
use App\Http\Controllers\Api\Admin\PegawaiController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\MahasiswaController;
use App\Http\Controllers\Api\Admin\MataUangController;
use App\Http\Controllers\Api\Admin\ThAkademikController;
use App\Http\Controllers\Api\Admin\AktifkanMahasiswaController;
use App\Http\Controllers\Api\Admin\FormSchaduleController;

use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\LaporanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\SetoranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\TagihanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\TagihanPeroranganController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\CekTagihanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\DispensasiController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\PembayaranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\UasSusulanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\DispensasiUasController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\PembayaranIdnController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\CatatanDepositController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\JenisPembayaranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\DispensasiTagihanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\PembayaranTambahanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\PemasukanPengeluaranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\SemesterPendekController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\BsiPaymentController;
use App\Http\Controllers\Api\Admin\Pengeluaran\DosenTatapMukaController;
use App\Http\Controllers\Api\Admin\Pengeluaran\DosenBulananController;
use App\Http\Controllers\Api\Admin\Pengeluaran\StaffBulananController;
use App\Http\Controllers\Api\Admin\Pengeluaran\RabController;
use App\Http\Controllers\Api\Admin\Pengeluaran\LpjController;
use App\Http\Controllers\Api\Admin\Pengeluaran\DosenKegiatanController as PengeluaranDosenKegiatanController;
use App\Http\Controllers\Api\Admin\Pengeluaran\RumahTanggaController as PengeluaranRumahTanggaController;
use App\Http\Controllers\Api\Admin\Pengeluaran\SaranaPrasaranaController as PengeluaranSaranaPrasaranaController;
use App\Http\Controllers\Api\Admin\Pengeluaran\TransportasiController as PengeluaranTransportasiController;

Route::prefix('bsi')->group(function () {
    Route::get('tagihan', [PublicBsiPaymentController::class, 'tagihan'])
        ->middleware('simkeuv2.apikey');
    Route::get('tagihan/{nim}', [PublicBsiPaymentController::class, 'tagihan'])
        ->middleware('simkeuv2.apikey');
    Route::post('pembayaran', [PublicBsiPaymentController::class, 'store'])
        ->middleware('simkeuv2.apikey');
    Route::get('pembayaran/{requestId}', [PublicBsiPaymentController::class, 'show'])
        ->middleware('simkeuv2.apikey');
    Route::post('callback', [PublicBsiPaymentController::class, 'callback'])
        ->middleware('bsi.callback');
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'getUser']);
    });
});

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::get('pegawai', [PegawaiController::class, 'index']);
    Route::get('pegawai/export-excel', [PegawaiController::class, 'exportExcel']);
    Route::post('pegawai/import-excel', [PegawaiController::class, 'importExcel'])->middleware('role:admin');
    Route::get('pegawai/{pegawai}', [PegawaiController::class, 'show']);
});

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,pimpinan,keuangan,kabag,staff,rumahtangga,sarpras,transportasi,barokahdosen_tatapmuka,barokahdosen_kegiatan,barokahdosen_bulanan'])->group(function () {
// Route::prefix('admin')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/widget', [DashboardController::class, 'widget'])->name('admin.dashboard.widget');
        Route::get('/finance-overview', [DashboardController::class, 'financeOverview'])->name('admin.dashboard.finance-overview');
        Route::get('/finance-overview-detail', [DashboardController::class, 'financeOverviewDetail'])->name('admin.dashboard.finance-overview-detail');
        Route::get('/statistic', [DashboardController::class, 'statistic'])->name('admin.dashboard.statistic');
        Route::get('/barokah-summary', [DashboardController::class, 'barokahSummary'])->name('admin.dashboard.barokah-summary');
        Route::get('/krs-report', [DashboardController::class, 'krsReport'])->name('admin.dashboard.krs-report');
        Route::get('/krs-report-detail', [DashboardController::class, 'krsReportDetail'])->name('admin.dashboard.krs-report-detail');
        Route::get('/krs-report-local', [DashboardController::class, 'krsReportLocal'])->name('admin.dashboard.krs-report-local');
        Route::get('/krs-report-detail-local', [DashboardController::class, 'krsReportDetailLocal'])->name('admin.dashboard.krs-report-detail-local');
        Route::get('/uas-report', [DashboardController::class, 'uasReport'])->name('admin.dashboard.uas-report');
        Route::get('/uas-report-detail', [DashboardController::class, 'uasReportDetail'])->name('admin.dashboard.uas-report-detail');
    });



    Route::prefix('pemasukan')->group(function () {
        Route::prefix('mahasiswa')->group(function () {
            Route::apiResource('jenis-pembayaran', JenisPembayaranController::class);
            Route::get('tagihan/export-excel', [TagihanController::class, 'exportExcel']);
            Route::post('tagihan/import', [TagihanController::class, 'import']);
            Route::get('tagihan/template', [TagihanController::class, 'downloadTemplate']);
            Route::get('tagihan-perorangan/export-excel', [TagihanPeroranganController::class, 'exportExcel']);
            Route::apiResource('tagihan-perorangan', TagihanPeroranganController::class);
            Route::apiResource('tagihan', TagihanController::class);

            Route::get('cek-tagihan/pdf', [CekTagihanController::class, 'pdf']);
            Route::get('cek-tagihan/excel', [CekTagihanController::class, 'excel']);
            Route::apiResource('cek-tagihan', CekTagihanController::class);

            Route::get('pembayaran/kwitansi/{id}', [PembayaranController::class, 'kwitansi'])->name('admin.pemasukan.mahasiswa.pembayaran.kwitansi');
            Route::get('pembayaran/kwitansi/{id}/view', [PembayaranController::class, 'kwitansiPreview'])->name('admin.pemasukan.mahasiswa.pembayaran.kwitansi.view');
            Route::get('pembayaran-statistic', [PembayaranController::class, 'statistic'])->name('admin.pemasukan.mahasiswa.pembayaran.statistic');
            Route::get('pembayaran-statistic-detail-prodi', [PembayaranController::class, 'statisticDetailProdi'])->name('admin.pemasukan.mahasiswa.pembayaran.statistic-detail-prodi');
            Route::get('wisuda/tahun', [PembayaranController::class, 'tahunWisuda'])->name('admin.pemasukan.mahasiswa.wisuda.tahun');
            Route::apiResource('pembayaran', PembayaranController::class);

            Route::prefix('pembayaran-bsi')->middleware('role:admin,pimpinan,keuangan')->group(function () {
                Route::get('/', [BsiPaymentController::class, 'index']);
                Route::get('{paymentBsi}', [BsiPaymentController::class, 'show']);
                Route::post('{paymentBsi}/post', [BsiPaymentController::class, 'post'])
                    ->middleware('role:admin,keuangan');
                Route::post('{paymentBsi}/reject', [BsiPaymentController::class, 'reject'])
                    ->middleware('role:admin,keuangan');
            });

            Route::get('pembayaran-tambahan/kwitansi/{id}', [PembayaranTambahanController::class, 'kwitansi'])->name('admin.pemasukan.mahasiswa.pembayaran-tambahan.kwitansi');
            Route::get('pembayaran-tambahan/kwitansi/{id}/view', [PembayaranTambahanController::class, 'kwitansiPreview'])->name('admin.pemasukan.mahasiswa.pembayaran-tambahan.kwitansi.view');
            Route::apiResource('pembayaran-tambahan', PembayaranTambahanController::class);

            Route::apiResource('pembayaran-idn', PembayaranIdnController::class);

            Route::post('uas-susulan/full', [UasSusulanController::class, 'storeFull'])
                ->name('admin.pemasukan.mahasiswa.uas-susulan.storeFull');
            Route::put('uas-susulan/full/{id}', [UasSusulanController::class, 'updateFull'])
                ->name('admin.pemasukan.mahasiswa.uas-susulan.updateFull');
            Route::delete('uas-susulan/full/{id}', [UasSusulanController::class, 'destroyFull'])
                ->name('admin.pemasukan.mahasiswa.uas-susulan.destroyFull');
            Route::get('uas-susulan/jadwal-kuliah', [UasSusulanController::class, 'getJadwalKuliah'])
                ->name('admin.pemasukan.mahasiswa.uas-susulan.getJadwalKuliah');
            Route::get('uas-susulan/excel', [UasSusulanController::class, 'excel'])
                ->name('admin.pemasukan.mahasiswa.uas-susulan.excel');
            Route::apiResource('uas-susulan', UasSusulanController::class);

            Route::apiResource('setoran', SetoranController::class);
            Route::put('setoran/{id}/validasi', [SetoranController::class, 'validasi'])->name('admin.pemasukan.mahasiswa.setoran.validasi');

            Route::get('catatan-deposit/nim/{nim}', [CatatanDepositController::class, 'nim'])->name('admin.pemasukan.mahasiswa.catatan-deposit.nim');
            Route::apiResource('catatan-deposit', CatatanDepositController::class);

            Route::apiResource('pemasukan-pengeluaran', PemasukanPengeluaranController::class);

            Route::get('dispensasi/auto-complete/{search}', [DispensasiController::class, 'autoComplete']);
            Route::apiResource('dispensasi', DispensasiController::class);

            Route::get('dispensasi-tagihan/auto-complete/{search}', [DispensasiTagihanController::class, 'autoComplete']);
            Route::post('dispensasi-tagihan/join', [DispensasiTagihanController::class, 'gabung'])->name('admin.pemasukan.mahasiswa.dispensasi-tagihan.join');
            Route::apiResource('dispensasi-tagihan', DispensasiTagihanController::class);

            Route::get('dispensasi-uas/auto-complete/{search}', [DispensasiUasController::class, 'autoComplete']);
            Route::apiResource('dispensasi-uas', DispensasiUasController::class);

            Route::get('semester-pendek/krs-detail/{krsId}', [SemesterPendekController::class, 'krsDetail']);
            Route::any('semester-pendek/{path?}', function () {
                return response()->json([
                    'status' => false,
                    'message' => 'Modul Semester Pendek sudah dinonaktifkan. Gunakan pembayaran mahasiswa biasa.',
                ], 403);
            })->where('path', '.*');

            Route::prefix('laporan')->group(function () {
                Route::get('harian', [LaporanController::class, 'harian']);
                Route::get('bulanan', [LaporanController::class, 'bulanan']);
                Route::get('tahunan', [LaporanController::class, 'tahunan']);
                Route::get('rekap', [LaporanController::class, 'rekap']);
                Route::get('rekap-tahunan', [LaporanController::class, 'rekapTahunan']);
                Route::get('jumlah-mahasiswa-bayar', [LaporanController::class, 'jumlahMahasiswaBayar']);
                Route::get('pemasukan-tunai-harian', [LaporanController::class, 'pemasukanTunaiHarian']);
                Route::get('laporan-harian', [LaporanController::class, 'laporanHarianDetail']);
                Route::get('pemasukan-uii-dalwa', [LaporanController::class, 'pemasukanUiiDalwa']);
            });
        });


    });

    Route::prefix('pengeluaran')->group(function () {
        Route::get('/dosen/by-date', [DosenTatapMukaController::class, 'byDate']);
        Route::get('/dosen/print-slip/{id}', [DosenTatapMukaController::class, 'printSlip']);
        Route::get('/dosen/export-excel', [DosenTatapMukaController::class, 'exportExcel']);
        Route::get('/dosen/export-bsi', [DosenTatapMukaController::class, 'exportBsi']);
        Route::get('/dosen/copy-bsi', [DosenTatapMukaController::class, 'copyBsi']);
        Route::get('/dosen/rekap', [DosenTatapMukaController::class, 'rekapIndex']);
        Route::post('/dosen/rekap', [DosenTatapMukaController::class, 'rekapStore']);
        Route::post('/dosen/rekap/bulk-update', [DosenTatapMukaController::class, 'rekapBulkUpdate']);
        Route::post('/dosen/rekap/{id}/release', [DosenTatapMukaController::class, 'rekapRelease']);
        Route::put('/dosen/rekap/{id}', [DosenTatapMukaController::class, 'rekapUpdate']);
        Route::delete('/dosen/rekap/{id}', [DosenTatapMukaController::class, 'rekapDestroy']);
        Route::get('/dosen/rekap/{id}/lpj', [LpjController::class, 'dosenShow']);
        Route::post('/dosen/rekap/{id}/lpj/copy', [LpjController::class, 'dosenCopy']);
        Route::put('/dosen/rekap/{id}/lpj', [LpjController::class, 'dosenUpdate']);
        Route::get('/dosen/rekap/{id}', [DosenTatapMukaController::class, 'rekapShow']);
        Route::apiResource('dosen', DosenTatapMukaController::class);
        Route::get('/dosen-kegiatan/by-date', [PengeluaranDosenKegiatanController::class, 'byDate']);
        Route::get('/dosen-kegiatan/export-excel', [PengeluaranDosenKegiatanController::class, 'exportExcel']);
        Route::get('/dosen-kegiatan/export-bsi', [PengeluaranDosenKegiatanController::class, 'exportBsi']);
        Route::get('/dosen-kegiatan/copy-bsi', [PengeluaranDosenKegiatanController::class, 'copyBsi']);
        Route::post('/dosen-kegiatan/batch-store', [PengeluaranDosenKegiatanController::class, 'batchStore']);
        Route::post('/dosen-kegiatan/batch-update', [PengeluaranDosenKegiatanController::class, 'batchUpdate']);
        Route::get('/dosen-kegiatan/rekap', [PengeluaranDosenKegiatanController::class, 'rekapIndex']);
        Route::post('/dosen-kegiatan/rekap', [PengeluaranDosenKegiatanController::class, 'rekapStore']);
        Route::post('/dosen-kegiatan/rekap/bulk-update', [PengeluaranDosenKegiatanController::class, 'rekapBulkUpdate']);
        Route::post('/dosen-kegiatan/rekap/{id}/release', [PengeluaranDosenKegiatanController::class, 'rekapRelease']);
        Route::put('/dosen-kegiatan/rekap/{id}', [PengeluaranDosenKegiatanController::class, 'rekapUpdate']);
        Route::delete('/dosen-kegiatan/rekap/{id}', [PengeluaranDosenKegiatanController::class, 'rekapDestroy']);
        Route::get('/dosen-kegiatan/rekap/{id}/lpj', [LpjController::class, 'kegiatanShow']);
        Route::post('/dosen-kegiatan/rekap/{id}/lpj/copy', [LpjController::class, 'kegiatanCopy']);
        Route::put('/dosen-kegiatan/rekap/{id}/lpj', [LpjController::class, 'kegiatanUpdate']);
        Route::get('/dosen-kegiatan/rekap/{id}', [PengeluaranDosenKegiatanController::class, 'rekapShow']);
        Route::apiResource('dosen-kegiatan', PengeluaranDosenKegiatanController::class);
        Route::get('/rumah-tangga/export-excel', [PengeluaranRumahTanggaController::class, 'exportExcel']);
        Route::post('/rumah-tangga/batch-store', [PengeluaranRumahTanggaController::class, 'batchStore']);
        Route::post('/rumah-tangga/batch-update', [PengeluaranRumahTanggaController::class, 'batchUpdate']);
        Route::get('/rumah-tangga/rekap', [PengeluaranRumahTanggaController::class, 'rekapIndex']);
        Route::post('/rumah-tangga/rekap', [PengeluaranRumahTanggaController::class, 'rekapStore']);
        Route::post('/rumah-tangga/rekap/bulk-update', [PengeluaranRumahTanggaController::class, 'rekapBulkUpdate']);
        Route::post('/rumah-tangga/rekap/{id}/release', [PengeluaranRumahTanggaController::class, 'rekapRelease']);
        Route::put('/rumah-tangga/rekap/{id}', [PengeluaranRumahTanggaController::class, 'rekapUpdate']);
        Route::delete('/rumah-tangga/rekap/{id}', [PengeluaranRumahTanggaController::class, 'rekapDestroy']);
        Route::get('/rumah-tangga/rekap/{id}/lpj', [LpjController::class, 'rumahTanggaShow']);
        Route::post('/rumah-tangga/rekap/{id}/lpj/copy', [LpjController::class, 'rumahTanggaCopy']);
        Route::put('/rumah-tangga/rekap/{id}/lpj', [LpjController::class, 'rumahTanggaUpdate']);
        Route::get('/rumah-tangga/rekap/{id}', [PengeluaranRumahTanggaController::class, 'rekapShow']);
        Route::apiResource('rumah-tangga', PengeluaranRumahTanggaController::class);
        Route::get('/sarana-prasarana/export-excel', [PengeluaranSaranaPrasaranaController::class, 'exportExcel']);
        Route::post('/sarana-prasarana/batch-store', [PengeluaranSaranaPrasaranaController::class, 'batchStore']);
        Route::post('/sarana-prasarana/batch-update', [PengeluaranSaranaPrasaranaController::class, 'batchUpdate']);
        Route::get('/sarana-prasarana/rekap', [PengeluaranSaranaPrasaranaController::class, 'rekapIndex']);
        Route::post('/sarana-prasarana/rekap', [PengeluaranSaranaPrasaranaController::class, 'rekapStore']);
        Route::post('/sarana-prasarana/rekap/bulk-update', [PengeluaranSaranaPrasaranaController::class, 'rekapBulkUpdate']);
        Route::post('/sarana-prasarana/rekap/{id}/release', [PengeluaranSaranaPrasaranaController::class, 'rekapRelease']);
        Route::put('/sarana-prasarana/rekap/{id}', [PengeluaranSaranaPrasaranaController::class, 'rekapUpdate']);
        Route::delete('/sarana-prasarana/rekap/{id}', [PengeluaranSaranaPrasaranaController::class, 'rekapDestroy']);
        Route::get('/sarana-prasarana/rekap/{id}/lpj', [LpjController::class, 'saranaPrasaranaShow']);
        Route::post('/sarana-prasarana/rekap/{id}/lpj/copy', [LpjController::class, 'saranaPrasaranaCopy']);
        Route::put('/sarana-prasarana/rekap/{id}/lpj', [LpjController::class, 'saranaPrasaranaUpdate']);
        Route::get('/sarana-prasarana/rekap/{id}', [PengeluaranSaranaPrasaranaController::class, 'rekapShow']);
        Route::apiResource('sarana-prasarana', PengeluaranSaranaPrasaranaController::class);
        Route::get('/transportasi/export-excel', [PengeluaranTransportasiController::class, 'exportExcel']);
        Route::post('/transportasi/batch-store', [PengeluaranTransportasiController::class, 'batchStore']);
        Route::post('/transportasi/batch-update', [PengeluaranTransportasiController::class, 'batchUpdate']);
        Route::get('/transportasi/rekap', [PengeluaranTransportasiController::class, 'rekapIndex']);
        Route::post('/transportasi/rekap', [PengeluaranTransportasiController::class, 'rekapStore']);
        Route::post('/transportasi/rekap/bulk-update', [PengeluaranTransportasiController::class, 'rekapBulkUpdate']);
        Route::post('/transportasi/rekap/{id}/release', [PengeluaranTransportasiController::class, 'rekapRelease']);
        Route::put('/transportasi/rekap/{id}', [PengeluaranTransportasiController::class, 'rekapUpdate']);
        Route::delete('/transportasi/rekap/{id}', [PengeluaranTransportasiController::class, 'rekapDestroy']);
        Route::get('/transportasi/rekap/{id}/lpj', [LpjController::class, 'transportasiShow']);
        Route::post('/transportasi/rekap/{id}/lpj/copy', [LpjController::class, 'transportasiCopy']);
        Route::put('/transportasi/rekap/{id}/lpj', [LpjController::class, 'transportasiUpdate']);
        Route::get('/transportasi/rekap/{id}', [PengeluaranTransportasiController::class, 'rekapShow']);
        Route::apiResource('transportasi', PengeluaranTransportasiController::class);
        Route::get('dosen-bulanan/export-bsi', [DosenBulananController::class, 'exportBsi'])->middleware('role:admin,barokahdosen_bulanan');
        Route::get('dosen-bulanan/copy-bsi', [DosenBulananController::class, 'copyBsi'])->middleware('role:admin,barokahdosen_bulanan');
        Route::get('dosen-bulanan/form-data', [DosenBulananController::class, 'formData'])->middleware('role:admin,barokahdosen_bulanan');
        Route::post('dosen-bulanan/batch-store', [DosenBulananController::class, 'batchStore'])->middleware('role:admin,barokahdosen_bulanan');
        Route::get('dosen-bulanan/rekap', [DosenBulananController::class, 'rekapIndex'])->middleware('role:admin,barokahdosen_bulanan');
        Route::post('dosen-bulanan/rekap', [DosenBulananController::class, 'rekapStore'])->middleware('role:admin,barokahdosen_bulanan');
        Route::post('dosen-bulanan/rekap/bulk-update', [DosenBulananController::class, 'rekapBulkUpdate'])->middleware('role:admin,barokahdosen_bulanan');
        Route::post('dosen-bulanan/rekap/{id}/release', [DosenBulananController::class, 'rekapRelease'])->middleware('role:admin,barokahdosen_bulanan');
        Route::put('dosen-bulanan/rekap/{id}', [DosenBulananController::class, 'rekapUpdate'])->middleware('role:admin,barokahdosen_bulanan');
        Route::delete('dosen-bulanan/rekap/{id}', [DosenBulananController::class, 'rekapDestroy'])->middleware('role:admin,barokahdosen_bulanan');
        Route::get('dosen-bulanan/rekap/{id}/lpj', [LpjController::class, 'dosenBulananShow'])->middleware('role:admin,barokahdosen_bulanan');
        Route::post('dosen-bulanan/rekap/{id}/lpj/copy', [LpjController::class, 'dosenBulananCopy'])->middleware('role:admin,barokahdosen_bulanan');
        Route::put('dosen-bulanan/rekap/{id}/lpj', [LpjController::class, 'dosenBulananUpdate'])->middleware('role:admin,barokahdosen_bulanan');
        Route::get('dosen-bulanan/rekap/{id}', [DosenBulananController::class, 'rekapShow'])->middleware('role:admin,barokahdosen_bulanan');
        Route::apiResource('dosen-bulanan', DosenBulananController::class)->middleware('role:admin,barokahdosen_bulanan');
        Route::get('staff-bulanan/export-excel', [StaffBulananController::class, 'exportExcel'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::get('staff-bulanan/export-bsi', [StaffBulananController::class, 'exportBsi'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::get('staff-bulanan/copy-bsi', [StaffBulananController::class, 'copyBsi'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::get('staff-bulanan/rekap', [StaffBulananController::class, 'rekapIndex'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::post('staff-bulanan/rekap', [StaffBulananController::class, 'rekapStore'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::post('staff-bulanan/rekap/bulk-update', [StaffBulananController::class, 'rekapBulkUpdate'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::post('staff-bulanan/rekap/{id}/release', [StaffBulananController::class, 'rekapRelease'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::put('staff-bulanan/rekap/{id}', [StaffBulananController::class, 'rekapUpdate'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::delete('staff-bulanan/rekap/{id}', [StaffBulananController::class, 'rekapDestroy'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::get('staff-bulanan/rekap/{id}/lpj', [LpjController::class, 'staffBulananShow'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::post('staff-bulanan/rekap/{id}/lpj/copy', [LpjController::class, 'staffBulananCopy'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::put('staff-bulanan/rekap/{id}/lpj', [LpjController::class, 'staffBulananUpdate'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::get('staff-bulanan/rekap/{id}', [StaffBulananController::class, 'rekapShow'])->middleware('role:admin,barokahdosen_kegiatan');
        Route::apiResource('staff-bulanan', StaffBulananController::class)->middleware('role:admin,barokahdosen_kegiatan');
    });
    Route::get('laporan/rab/kas', [RabController::class, 'kas'])
        ->middleware('role:admin,pimpinan,keuangan,kabag,barokahdosen_tatapmuka,barokahdosen_kegiatan,barokahdosen_bulanan');
    Route::post('laporan/rab/kas/manual', [RabController::class, 'storeKasManual'])
        ->middleware('role:admin,keuangan,kabag,barokahdosen_tatapmuka,barokahdosen_kegiatan,barokahdosen_bulanan');
    Route::put('laporan/rab/kas/manual/{id}', [RabController::class, 'updateKasManual'])
        ->middleware('role:admin,keuangan,kabag,barokahdosen_tatapmuka,barokahdosen_kegiatan,barokahdosen_bulanan');
    Route::delete('laporan/rab/kas/manual/{id}', [RabController::class, 'destroyKasManual'])
        ->middleware('role:admin,keuangan,kabag,barokahdosen_tatapmuka,barokahdosen_kegiatan,barokahdosen_bulanan');
    Route::get('laporan/rab', [RabController::class, 'index'])
        ->middleware('role:admin,pimpinan,keuangan,kabag,barokahdosen_tatapmuka,barokahdosen_kegiatan,barokahdosen_bulanan');


    Route::apiResource('users', UserController::class);
    Route::apiResource('role', RoleController::class);
    Route::apiResource('th-akademik', ThAkademikController::class);
    Route::apiResource('prodi', ProdiController::class);
    Route::apiResource('mata-uang', MataUangController::class);
    Route::apiResource('ref', RefController::class);
    Route::get('pegawai/dosen-siakad/preview', [PegawaiController::class, 'previewDosenSiakad'])->middleware('role:admin');
    Route::get('pegawai/dosen-siakad/ids', [PegawaiController::class, 'dosenSiakadIds'])->middleware('role:admin');
    Route::post('pegawai/sync-dosen-siakad', [PegawaiController::class, 'syncDosenSiakad'])->middleware('role:admin');
    Route::get('pegawai/staff-absensi/preview', [PegawaiController::class, 'previewStaffAbsensi'])->middleware('role:admin');
    Route::get('pegawai/staff-absensi/ids', [PegawaiController::class, 'staffAbsensiIds'])->middleware('role:admin');
    Route::post('pegawai/sync-staff-absensi', [PegawaiController::class, 'syncStaffAbsensi'])->middleware('role:admin');
    Route::apiResource('pegawai', PegawaiController::class)->except(['index', 'show'])->middleware('role:admin');

    Route::prefix('aktifkan-mahasiswa')->middleware('role:admin')->group(function () {
        Route::get('preview', [AktifkanMahasiswaController::class, 'preview']);
        Route::post('activate', [AktifkanMahasiswaController::class, 'activate']);
    });

    Route::get('/mahasiswa/search/{search}', [MahasiswaController::class, 'search']);
    Route::get('/mahasiswa/nim', [MahasiswaController::class, 'nim']);
    Route::get('/mahasiswa/cek-pelanggaran/{nim}', [MahasiswaController::class, 'cekPelanggaran']);
    Route::apiResource('mahasiswa', MahasiswaController::class);

    Route::apiResource('form-schadule', FormSchaduleController::class);

    Route::prefix('profil')->group(function () {
        Route::get('/', [ProfilController::class, 'index']);
        Route::put('/', [ProfilController::class, 'update']);
    });

    Route::prefix('dosen')->group(function () {
        Route::get('/', [DosenController::class, 'index']);
        Route::get('/kode', [DosenController::class, 'kode']);
        Route::get('/search/{search}', [DosenController::class, 'search']);
        Route::get('/show/{id}', [DosenController::class, 'show']);
    });

    Route::prefix('jadwal')->group(function () {
        Route::get('/', [JadwalController::class, 'index']);
        Route::get('/dosenTable', [JadwalController::class, 'dosenTable']);
        Route::get('/show/{id}', [JadwalController::class, 'show']);
    });

    Route::prefix('absensi')->group(function () {
        Route::get('/', [AbsensiController::class, 'index']);
    });
});

Route::prefix('helper')->group(function () {
    Route::get('/get-enum-values', [HelperController::class, 'getEnumValues'])->middleware('auth:sanctum');
    Route::prefix('bsi')->group(function () {
        Route::get('tagihan', [PublicBsiPaymentController::class, 'tagihan'])
            ->middleware('simkeuv2.apikey');
        Route::post('pembayaran', [PublicBsiPaymentController::class, 'store'])
            ->middleware('simkeuv2.apikey');
        Route::get('pembayaran/{requestId}', [PublicBsiPaymentController::class, 'show'])
            ->middleware('simkeuv2.apikey');
        Route::post('callback', [PublicBsiPaymentController::class, 'callback'])
            ->middleware('bsi.callback');
    });
    Route::post('tagihan-perorangan', [HelperController::class, 'createTagihanPerorangan'])->middleware('simkeuv2.apikey');
    Route::delete('tagihan-perorangan', [HelperController::class, 'deleteTagihanPerorangan'])->middleware('simkeuv2.apikey');
    Route::get('pembayaran-wisuda', [HelperController::class, 'getDataPembayaranWisuda'])->middleware('simkeuv2.apikey');
    Route::post('pembayaran-wisuda', [HelperController::class, 'createPembayaranWisuda'])->middleware('simkeuv2.apikey');
    Route::get('cek-pembayaran', [HelperController::class, 'cekPembayaran']);
    Route::get('cek-pembayaran-uas', [HelperController::class, 'cekPembayaranUas']);
    Route::get('cek-pembayaran-uts', [HelperController::class, 'cekPembayaranUTS']);
    Route::get('petugas-pembayaran', [HelperController::class, 'petugasPembayaran'])->middleware('auth:sanctum');
    Route::get('petugas-pengeluaran', [HelperController::class, 'petugasPengeluaran'])->middleware('auth:sanctum');
});

Route::prefix('pengeluaran')->group(function () {
    Route::apiResource('dosen', DosenTatapMukaController::class);
});
// Route::get('admin/pemasukan/mahasiswa/uas-susulann/excel', [UasSusulanController::class, 'excel']);
Route::get('/testing/{id}', [DosenTatapMukaController::class, 'printSlip']);
Route::get('/testingexcel', [DosenTatapMukaController::class, 'exportExcel']);
// Route::get('testing/{nim}', [MahasiswaController::class, 'cekPelanggaran']);
// Route::get('testing2', [CekTagihanController::class, 'excel']);
// Route::get('admin/pemasukan/mahasiswa/pembayaran/kwitansi/{id}', [PembayaranController::class, 'kwitansi'])->name('admin.pemasukan.mahasiswa.kwitansi.view');
Route::get('/krs-report', [DashboardController::class, 'krsReport'])->name('admin.dashboard.krs-report');
Route::get('/krs-report-detail', [DashboardController::class, 'krsReportDetail'])->name('admin.dashboard.krs-report-detail');
