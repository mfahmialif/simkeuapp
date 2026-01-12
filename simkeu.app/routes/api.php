<?php

use App\Http\Controllers\Api\Admin\AbsensiController;
use App\Models\FormSchadule;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HelperController;
use App\Http\Controllers\Api\Admin\RefController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\DosenController;
use App\Http\Controllers\Api\Admin\ProdiController;
use App\Http\Controllers\Api\Admin\JadwalController;
use App\Http\Controllers\Api\Admin\ProfilController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\MahasiswaController;
use App\Http\Controllers\Api\Admin\ThAkademikController;
use App\Http\Controllers\Api\Admin\PengeluaranController;
use App\Http\Controllers\Api\Admin\FormSchaduleController;
use App\Http\Controllers\Api\Admin\Saldo\KategoriController;
use App\Http\Controllers\Api\Admin\Pemasukan\Pemasukan\TambahController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\LaporanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\SetoranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\TagihanController;
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
use App\Http\Controllers\Api\Admin\Pengeluaran\DosenController as PengeluaranDosenController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'getUser']);
    });
});

Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin,pimpinan,keuangan,kabag,staff,rumahtangga,barokahdosen'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/widget', [DashboardController::class, 'widget'])->name('admin.dashboard.widget');
        Route::get('/finance-overview', [DashboardController::class, 'financeOverview'])->name('admin.dashboard.finance-overview');
        Route::get('/statistic', [DashboardController::class, 'statistic'])->name('admin.dashboard.statistic');
    });

    Route::prefix('saldo')->group(function () {
        Route::apiResource('kategori', KategoriController::class);
    });

    Route::prefix('pemasukan')->group(function () {
        Route::prefix('mahasiswa')->group(function () {
            Route::apiResource('jenis-pembayaran', JenisPembayaranController::class);
            Route::apiResource('tagihan', TagihanController::class);

            Route::get('cek-tagihan/pdf', [CekTagihanController::class, 'pdf']);
            Route::get('cek-tagihan/excel', [CekTagihanController::class, 'excel']);
            Route::apiResource('cek-tagihan', CekTagihanController::class);

            Route::get('pembayaran/kwitansi/{id}', [PembayaranController::class, 'kwitansi'])->name('admin.pemasukan.mahasiswa.pembayaran.kwitansi');
            Route::get('pembayaran/kwitansi/{id}/view', [PembayaranController::class, 'kwitansiPreview'])->name('admin.pemasukan.mahasiswa.pembayaran.kwitansi.view');
            Route::apiResource('pembayaran', PembayaranController::class);

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

            Route::prefix('laporan')->group(function () {
                Route::get('harian', [LaporanController::class, 'harian']);
                Route::get('bulanan', [LaporanController::class, 'bulanan']);
                Route::get('tahunan', [LaporanController::class, 'tahunan']);
                Route::get('rekap', [LaporanController::class, 'rekap']);
                Route::get('rekap-tahunan', [LaporanController::class, 'rekapTahunan']);
                Route::get('jumlah-mahasiswa-bayar', [LaporanController::class, 'jumlahMahasiswaBayar']);
            });
        });

        Route::prefix('pemasukan')->group(function () {
            Route::apiResource('tambah', TambahController::class);
        });
    });

    Route::prefix('pengeluaran')->group(function () {
        Route::get('/dosen/print-slip/{id}', [PengeluaranDosenController::class, 'printSlip']);
        Route::get('/dosen/export-excel', [PengeluaranDosenController::class, 'exportExcel']);
        Route::apiResource('dosen', PengeluaranDosenController::class);
    });
    Route::apiResource('pengeluaran', PengeluaranController::class);

    Route::apiResource('users', UserController::class);
    Route::apiResource('role', RoleController::class);
    Route::apiResource('th-akademik', ThAkademikController::class);
    Route::apiResource('prodi', ProdiController::class);
    Route::apiResource('ref', RefController::class);

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
    Route::get('cek-pembayaran', [HelperController::class, 'cekPembayaran']);
    Route::get('cek-pembayaran-uas', [HelperController::class, 'cekPembayaranUas']);
});

Route::prefix('pengeluaran')->group(function () {
    Route::apiResource('dosen', PengeluaranDosenController::class);
});
// Route::get('admin/pemasukan/mahasiswa/uas-susulann/excel', [UasSusulanController::class, 'excel']);
Route::get('/testing/{id}', [PengeluaranDosenController::class, 'printSlip']);
Route::get('/testingexcel', [PengeluaranDosenController::class, 'exportExcel']);
// Route::get('testing/{nim}', [MahasiswaController::class, 'cekPelanggaran']);
// Route::get('testing2', [CekTagihanController::class, 'excel']);
// Route::get('admin/pemasukan/mahasiswa/pembayaran/kwitansi/{id}', [PembayaranController::class, 'kwitansi'])->name('admin.pemasukan.mahasiswa.kwitansi.view');