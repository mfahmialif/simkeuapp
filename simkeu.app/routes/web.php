<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestingController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('testing', [TestingController::class, 'index']);
// Route::get('tes-pmb', [TestingController::class, 'tesPembayaranPmb']);
// Route::get('tes-input-wisuda', [TestingController::class, 'tesInputWisuda']);
// Route::get('list-pembayaran-sp', [TestingController::class, 'listPembayaranSP']);
// Route::get('sp-belum-terinput', [TestingController::class, 'spBelumTerinput']);
// Route::get('tagihan-sp-belum-dibayar', [TestingController::class, 'listTagihanSpBelumDibayar']);
// Route::get('input-sp-belum-terinput', [TestingController::class, 'inputSpBelumTerinput']);
// Route::get('rollback-input-sp-belum-terinput', [TestingController::class, 'rollbackInputSpBelumTerinput']);
