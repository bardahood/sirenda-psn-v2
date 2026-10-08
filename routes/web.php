<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\KualitasDataController;
use App\Http\Controllers\Api\V1\ProyekController as ProyekApiController;
use App\Http\Controllers\Api\V1\ReferensiController;
use App\Http\Controllers\Api\V1\RisikoController;
use App\Http\Controllers\Api\V1\UsulanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\Pengaturan\CutoffController;
use App\Http\Controllers\Pengaturan\MasterDataController;
use App\Http\Controllers\Pengaturan\PenggunaController;
use App\Http\Controllers\PengisianController;
use App\Http\Controllers\PerencanaanController;
use App\Http\Controllers\PetaController;
use App\Http\Controllers\ProyekController;
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
    Route::get('/proyek', [ProyekController::class, 'index'])->middleware('can:portofolio.lihat')->name('proyek.index');
    Route::get('/proyek/{psn}', [ProyekController::class, 'show'])->middleware('can:detail.lihat')->whereNumber('psn')->name('proyek.show');
    Route::view('/kualitas-data', 'kualitas-data.index')->middleware('can:kualitas.lihat')->name('kualitas-data');
    Route::view('/risiko', 'risiko.index')->middleware('can:risiko.lihat')->name('risiko');
    Route::get('/peta', [PetaController::class, 'index'])->middleware('can:ringkasan.lihat')->name('peta');
    Route::get('/laporan', [LaporanController::class, 'index'])->middleware('can:laporan.lihat')->name('laporan.index');
    Route::get('/laporan/risiko.xlsx', [LaporanController::class, 'risikoXlsx'])->middleware('can:risiko.lihat')->name('laporan.risiko-xlsx');
    Route::get('/laporan/pengisian.xlsx', [LaporanController::class, 'pengisianXlsx'])->middleware('can:laporan.lihat')->name('laporan.pengisian-xlsx');
    Route::get('/laporan/ringkasan.pdf', [LaporanController::class, 'ringkasanPdf'])->middleware('can:ringkasan.lihat')->name('laporan.ringkasan-pdf');

    Route::view('/kamus-indikator', 'kamus.index')->name('kamus');

    // Pengisian & verifikasi pemutakhiran per cut-off; otorisasi rinci di PsnPolicy (update/verifikasi).
    Route::middleware('can:detail.input')->prefix('pengisian')->name('pengisian.')->controller(PengisianController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{periode}/{psn}', 'show')->where('periode', '\d{4}-\d{2}')->whereNumber('psn')->name('show');
        Route::post('/{periode}/{psn}', 'simpan')->where('periode', '\d{4}-\d{2}')->whereNumber('psn')->name('simpan');
        Route::post('/{periode}/{psn}/verifikasi', 'verifikasi')->where('periode', '\d{4}-\d{2}')->whereNumber('psn')->name('verifikasi');
    });

    // Pengaturan (Super Admin): pengguna & peran, cut-off & snapshot, master data.
    Route::middleware('can:pengaturan.kelola')->prefix('pengaturan')->name('pengaturan.')->group(function () {
        Route::redirect('/', '/pengaturan/pengguna')->name('index');
        Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
        Route::get('/pengguna/baru', [PenggunaController::class, 'create'])->name('pengguna.create');
        Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
        Route::get('/pengguna/{pengguna}', [PenggunaController::class, 'edit'])->whereNumber('pengguna')->name('pengguna.edit');
        Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])->whereNumber('pengguna')->name('pengguna.update');
        Route::post('/pengguna/{pengguna}/reset-sandi', [PenggunaController::class, 'resetSandi'])->whereNumber('pengguna')->name('pengguna.reset');
        Route::get('/cutoff', [CutoffController::class, 'index'])->name('cutoff');
        Route::post('/cutoff', [CutoffController::class, 'bangun'])->name('cutoff.bangun');
        Route::get('/master', [MasterDataController::class, 'index'])->name('master');
        Route::put('/master/klaster/{id}', [MasterDataController::class, 'ubahKlaster'])->whereNumber('id')->name('master.klaster');
        Route::put('/master/sub-klaster/{id}', [MasterDataController::class, 'ubahSubKlaster'])->whereNumber('id')->name('master.sub-klaster');
        Route::put('/master/unit/{id}', [MasterDataController::class, 'ubahUnit'])->whereNumber('id')->name('master.unit');
    });

    // Perencanaan & penilaian usulan; otorisasi rinci di UsulanPsnPolicy.
    Route::prefix('perencanaan')->name('perencanaan.')->controller(PerencanaanController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/baru', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{usulan}', 'show')->whereNumber('usulan')->name('show');
        Route::post('/{usulan}/penilaian', 'storePenilaian')->whereNumber('usulan')->name('penilaian.store');
        Route::put('/penilaian/{penilaian}/skor', 'simpanSkor')->whereNumber('penilaian')->name('penilaian.skor');
        Route::post('/penilaian/{penilaian}/final', 'finalisasi')->whereNumber('penilaian')->name('penilaian.final');
        Route::post('/penilaian/{penilaian}/buka', 'bukaKembali')->whereNumber('penilaian')->name('penilaian.buka');
    });

    // API JSON (sesi web yang sama, satu origin). Semua menerima filter global.
    Route::prefix('api/v1')->name('api.')->group(function () {
        Route::get('/filter-opsi', [ReferensiController::class, 'filterOpsi'])->name('filter-opsi');
        Route::get('/kamus-indikator', [ReferensiController::class, 'kamusIndikator'])->name('kamus-indikator');

        Route::get('/proyek', [ProyekApiController::class, 'index'])->middleware('can:portofolio.lihat')->name('proyek.index');
        Route::get('/proyek/{psn}', [ProyekApiController::class, 'show'])->middleware('can:detail.lihat')->whereNumber('psn')->name('proyek.show');
        Route::middleware('can:risiko.lihat')->prefix('risiko')->name('risiko.')->controller(RisikoController::class)->group(function () {
            Route::get('/ringkasan', 'ringkasan')->name('ringkasan');
            Route::get('/register', 'register')->name('register');
            Route::get('/isu', 'isu')->name('isu');
        });
        Route::get('/peta', [PetaController::class, 'data'])->middleware('can:ringkasan.lihat')->name('peta');
        Route::get('/usulan/{usulan}/skor', [UsulanController::class, 'skor'])->whereNumber('usulan')->name('usulan.skor');
        Route::get('/kualitas-data', [KualitasDataController::class, 'index'])->middleware('can:kualitas.lihat')->name('kualitas-data');

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
