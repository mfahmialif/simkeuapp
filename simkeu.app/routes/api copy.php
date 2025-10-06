<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\JenisPembayaranController;
use App\Http\Controllers\Api\Admin\ProfilController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\ThAkademikController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HelperController;
use Illuminate\Support\Facades\Route;

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

    Route::apiResource('jenis-pembayaran', JenisPembayaranController::class);

    Route::prefix('role')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/{id}', [RoleController::class, 'show'])->middleware('role:admin');
        Route::post('/', [RoleController::class, 'store'])->middleware('role:admin');
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('role:admin');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('role:admin');
    });

    Route::prefix('th_akademik')->group(function () {
        Route::get('/', [ThAkademikController::class, 'index']);
        Route::get('/{id}', [ThAkademikController::class, 'show']);
        Route::post('/', [ThAkademikController::class, 'store']);
        Route::put('/{id}', [ThAkademikController::class, 'update']);
        Route::delete('/{id}', [ThAkademikController::class, 'destroy']);
    });

    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });

    Route::prefix('profil')->group(function () {
        Route::get('/', [ProfilController::class, 'index']);
        Route::put('/', [ProfilController::class, 'update']);
    });
});

Route::prefix('helper')->middleware('auth:sanctum')->group(function () {
    Route::get('/get-enum-values', [HelperController::class, 'getEnumValues']);
});
