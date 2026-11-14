<?php

use App\Http\Controllers\Api\Integrasi\Peks3Controller;
use App\Http\Controllers\Api\Integrasi\PsiController;
use App\Http\Middleware\TandaTanganPeks3;
use App\Http\Middleware\TokenIntegrasiPsi;
use Illuminate\Support\Facades\Route;

// Integrasi mesin-ke-mesin dengan aplikasi permohonan Dit. PSI (tanpa sesi, tanpa CSRF; token Bearer).
Route::prefix('integrasi/psi/v1')->name('integrasi.psi.')->middleware([TokenIntegrasiPsi::class, 'throttle:120,1'])->group(function () {
    Route::get('/referensi', [PsiController::class, 'referensi'])->name('referensi');
    Route::post('/usulan', [PsiController::class, 'terima'])->name('terima');
    Route::post('/usulan/{psiId}/tarik', [PsiController::class, 'tarik'])->where('psiId', '[A-Za-z0-9._\-]+')->name('tarik');
    Route::get('/usulan/{psiId}/hasil', [PsiController::class, 'ambilHasil'])->where('psiId', '[A-Za-z0-9._\-]+')->name('hasil');
});

// Sinkron dari SIRENDA PEKS3 (aplikasi input profil PSN): tanda tangan HMAC-SHA256. docs/integrasi-peks3.md
Route::prefix('integrasi/peks3/v1')->name('integrasi.peks3.')->middleware([TandaTanganPeks3::class, 'throttle:240,1'])->group(function () {
    Route::post('/psn', [Peks3Controller::class, 'psn'])->name('psn');
    Route::post('/referensi', [Peks3Controller::class, 'referensi'])->name('referensi');
    Route::post('/rekonsiliasi', [Peks3Controller::class, 'rekonsiliasi'])->name('rekonsiliasi');
    Route::get('/status', [Peks3Controller::class, 'status'])->name('status');
});
