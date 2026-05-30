<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestingController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('testing', [TestingController::class, 'index']);
Route::get('tes-pmb', [TestingController::class, 'tesPembayaranPmb']);
Route::get('tes-input-wisuda', [TestingController::class, 'tesInputWisuda']);
