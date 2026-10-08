<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ReferensiController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'akun.aktif'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/ganti-password', [LoginController::class, 'editPassword'])->name('password.edit');
    Route::put('/ganti-password', [LoginController::class, 'updatePassword'])->name('password.update');

    Route::redirect('/', '/dashboard');
    Route::view('/dashboard', 'dashboard.index')->middleware('can:ringkasan.lihat')->name('dashboard');
    // Portofolio (Fase 3): sementara menampilkan filter yang diteruskan dari drill-down.
    Route::view('/proyek', 'proyek.segera')->middleware('can:portofolio.lihat')->name('proyek.index');

    // API JSON (sesi web yang sama, satu origin). Semua menerima filter global.
    Route::prefix('api/v1')->name('api.')->group(function () {
        Route::get('/filter-opsi', [ReferensiController::class, 'filterOpsi'])->name('filter-opsi');
        Route::get('/kamus-indikator', [ReferensiController::class, 'kamusIndikator'])->name('kamus-indikator');

        Route::middleware('can:ringkasan.lihat')->prefix('dashboard')->name('dashboard.')->controller(DashboardController::class)->group(function () {
            Route::get('/kpi', 'kpi')->name('kpi');
            Route::get('/distribusi', 'distribusi')->name('distribusi');
            Route::get('/progres', 'progres')->name('progres');
            Route::get('/tren', 'tren')->name('tren');
            Route::get('/ro-kritis', 'roKritis')->name('ro-kritis');
            Route::get('/tahapan', 'tahapan')->name('tahapan');
            Route::get('/status-data', 'statusData')->name('status-data');
            Route::get('/trisula', 'trisula')->name('trisula');
            Route::get('/timeline-dp', 'timelineDp')->name('timeline-dp');
            Route::get('/aktivitas', 'aktivitas')->name('aktivitas');
        });
    });
});
