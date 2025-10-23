<?php

use App\Models\FormSchadule;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HelperController;
use App\Http\Controllers\Api\Admin\RefController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ProdiController;
use App\Http\Controllers\Api\Admin\ProfilController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\DispensasiController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\DispensasiTagihanController;
use App\Http\Controllers\Api\Admin\MahasiswaController;
use App\Http\Controllers\Api\Admin\ThAkademikController;
use App\Http\Controllers\Api\Admin\FormSchaduleController;
use App\Http\Controllers\Api\Admin\Saldo\KategoriController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\SetoranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\TagihanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\CekTagihanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\PembayaranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\CatatanDepositController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\JenisPembayaranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\LaporanController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\PemasukanPengeluaranController;
use App\Http\Controllers\Api\Admin\Pemasukan\Mahasiswa\DispensasiUasController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'getUser']);
    });
});

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/tableOverview', [DashboardController::class, 'tableOverview'])->name('dashboard.tableOverview');
        Route::get('/getWidget', [DashboardController::class, 'getWidget'])->name('dashboard.getWidget');
    });

    Route::prefix('saldo')->group(function () {
        Route::apiResource('kategori', KategoriController::class);
    });

    Route::prefix('pemasukan')->group(function () {
        Route::prefix('mahasiswa')->group(function () {
            Route::apiResource('jenis-pembayaran', JenisPembayaranController::class);
            Route::apiResource('tagihan', TagihanController::class);
            Route::apiResource('cek-tagihan', CekTagihanController::class);

            Route::get('pembayaran/kwitansi/{id}', [PembayaranController::class, 'kwitansi'])->name('admin.pemasukan.mahasiswa.kwitansi');
            Route::get('pembayaran/kwitansi/{id}/view', [PembayaranController::class, 'kwitansiPreview'])->name('admin.pemasukan.mahasiswa.kwitansi.view');
            Route::apiResource('pembayaran', PembayaranController::class);

            Route::apiResource('setoran', SetoranController::class);
            Route::put('setoran/{id}/validasi', [SetoranController::class, 'validasi'])->name('admin.pemasukan.mahasiswa.setoran.validasi');

            Route::get('catatan-deposit/nim/{nim}', [CatatanDepositController::class, 'nim'])->name('admin.pemasukan.mahasiswa.catatan-deposit.nim');
            Route::apiResource('catatan-deposit', CatatanDepositController::class);

            Route::apiResource('pemasukan-pengeluaran', PemasukanPengeluaranController::class);

            Route::get('dispensasi/auto-complete/{search}', [DispensasiController::class, 'autoComplete']);
            Route::apiResource('dispensasi', DispensasiController::class);

            Route::get('dispensasi-tagihan/auto-complete/{search}', [DispensasiTagihanController::class, 'autoComplete']);
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
    });

    Route::apiResource('users', UserController::class);
    Route::apiResource('role', RoleController::class);
    Route::apiResource('th-akademik', ThAkademikController::class);
    Route::apiResource('prodi', ProdiController::class);
    Route::apiResource('ref', RefController::class);

    Route::get('/mahasiswa/search/{search}', [MahasiswaController::class, 'search']);
    Route::get('/mahasiswa/nim', [MahasiswaController::class, 'nim']);
    Route::apiResource('mahasiswa', MahasiswaController::class);

    Route::apiResource('form-schadule', FormSchaduleController::class);

    Route::prefix('profil')->group(function () {
        Route::get('/', [ProfilController::class, 'index']);
        Route::put('/', [ProfilController::class, 'update']);
    });
});

Route::prefix('helper')->middleware('auth:sanctum')->group(function () {
    Route::get('/get-enum-values', [HelperController::class, 'getEnumValues']);
});
// Route::get('admin/pemasukan/mahasiswa/pembayaran/kwitansi/{id}', [PembayaranController::class, 'kwitansi'])->name('admin.pemasukan.mahasiswa.kwitansi.view');