<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\KualitasDataController;
use App\Http\Controllers\Api\V1\ProyekController as ProyekApiController;
use App\Http\Controllers\Api\V1\ReferensiController;
use App\Http\Controllers\Api\V1\RisikoController;
use App\Http\Controllers\Api\V1\UsulanController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\EvaluasiStatusController;
use App\Http\Controllers\FormPengendalianController;
use App\Http\Controllers\IntegrasiSifController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\Pengaturan\CutoffController;
use App\Http\Controllers\Pengaturan\IntegrasiController;
use App\Http\Controllers\Pengaturan\MasterDataController;
use App\Http\Controllers\Pengaturan\PenggunaController;
use App\Http\Controllers\Pengaturan\PeranController;
use App\Http\Controllers\PengisianController;
use App\Http\Controllers\PerencanaanController;
use App\Http\Controllers\PetaController;
use App\Http\Controllers\PlenoController;
use App\Http\Controllers\ProyekController;
use App\Http\Controllers\RekomendasiController;
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
    Route::view('/dashboard', 'dashboard.ikhtisar')->middleware('can:ringkasan.lihat')->name('dashboard');
    Route::view('/dashboard/pelaksanaan', 'dashboard.index')->middleware('can:ringkasan.lihat')->name('dashboard.pelaksanaan');
    Route::get('/proyek', [ProyekController::class, 'index'])->middleware('can:portofolio.lihat')->name('proyek.index');
    Route::get('/proyek/{psn}', [ProyekController::class, 'show'])->middleware('can:detail.lihat')->whereNumber('psn')->name('proyek.show');
    Route::get('/proyek/{psn}/form-pengendalian', [FormPengendalianController::class, 'show'])->middleware('can:detail.lihat')->whereNumber('psn')->name('proyek.form-pengendalian');
    // Evaluasi keberlanjutan status PSN (docs/evaluasi-status-psn.md).
    Route::get('/evaluasi-status', [EvaluasiStatusController::class, 'index'])->middleware('can:evaluasi.lihat')->name('evaluasi.index');
    Route::get('/evaluasi-status/ekspor.xlsx', [EvaluasiStatusController::class, 'ekspor'])->middleware(['can:evaluasi.lihat', 'proses.berat'])->name('evaluasi.ekspor');
    Route::get('/evaluasi-status/kertas-kerja', [EvaluasiStatusController::class, 'kertasKerja'])->middleware('can:evaluasi.lihat')->name('evaluasi.kertas-kerja');
    Route::get('/evaluasi-status/kertas-kerja.xlsx', [EvaluasiStatusController::class, 'kertasKerjaXlsx'])->middleware(['can:evaluasi.lihat', 'proses.berat'])->name('evaluasi.kertas-kerja-xlsx');
    Route::get('/evaluasi-status/unggah', [EvaluasiStatusController::class, 'unggah'])->middleware('can:evaluasi.input')->name('evaluasi.unggah');
    Route::post('/evaluasi-status/unggah', [EvaluasiStatusController::class, 'unggahBaca'])->middleware(['can:evaluasi.input', 'throttle:20,1', 'proses.berat'])->name('evaluasi.unggah.baca');
    Route::get('/evaluasi-status/unggah/pratinjau', [EvaluasiStatusController::class, 'unggahPratinjau'])->middleware('can:evaluasi.input')->name('evaluasi.unggah.pratinjau');
    Route::post('/evaluasi-status/unggah/terapkan', [EvaluasiStatusController::class, 'unggahTerapkan'])->middleware(['can:evaluasi.input', 'proses.berat'])->name('evaluasi.unggah.terapkan');
    Route::get('/evaluasi-status/grafik', [EvaluasiStatusController::class, 'grafik'])->middleware('can:evaluasi.lihat')->name('evaluasi.grafik');
    Route::get('/evaluasi-status/{psn}', [EvaluasiStatusController::class, 'show'])->middleware('can:evaluasi.lihat')->whereNumber('psn')->name('evaluasi.show');
    Route::post('/evaluasi-status/{psn}/terima', [EvaluasiStatusController::class, 'terima'])->middleware('can:evaluasi.input')->whereNumber('psn')->name('evaluasi.terima');
    Route::patch('/evaluasi-status/{psn}/sel', [EvaluasiStatusController::class, 'sel'])->middleware('can:evaluasi.input')->whereNumber('psn')->name('evaluasi.sel');
    Route::put('/evaluasi-status/{psn}', [EvaluasiStatusController::class, 'update'])->middleware('can:evaluasi.input')->whereNumber('psn')->name('evaluasi.update');
    Route::get('/form-pengendalian/lama/{id}', [FormPengendalianController::class, 'dariIdLama'])->middleware('can:detail.lihat')->whereNumber('id')->name('form-pengendalian.lama');
    Route::view('/kualitas-data', 'kualitas-data.index')->middleware('can:kualitas.lihat')->name('kualitas-data');
    Route::view('/risiko', 'risiko.index')->middleware('can:risiko.lihat')->name('risiko');
    Route::get('/peta', [PetaController::class, 'index'])->middleware('can:ringkasan.lihat')->name('peta');
    Route::get('/laporan', [LaporanController::class, 'index'])->middleware('can:laporan.lihat')->name('laporan.index');
    Route::get('/laporan/risiko.xlsx', [LaporanController::class, 'risikoXlsx'])->middleware(['can:risiko.lihat', 'proses.berat'])->name('laporan.risiko-xlsx');
    Route::get('/laporan/pengisian.xlsx', [LaporanController::class, 'pengisianXlsx'])->middleware(['can:laporan.lihat', 'proses.berat'])->name('laporan.pengisian-xlsx');
    Route::get('/laporan/ringkasan.pdf', [LaporanController::class, 'ringkasanPdf'])->middleware(['can:ringkasan.lihat', 'proses.berat'])->name('laporan.ringkasan-pdf');

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
        Route::get('/peran', [PeranController::class, 'index'])->name('peran.index');
        Route::post('/peran', [PeranController::class, 'store'])->name('peran.store');
        Route::put('/peran/{peran}', [PeranController::class, 'update'])->whereNumber('peran')->name('peran.update');
        Route::delete('/peran/{peran}', [PeranController::class, 'destroy'])->whereNumber('peran')->name('peran.destroy');
        Route::get('/cutoff', [CutoffController::class, 'index'])->name('cutoff');
        Route::post('/cutoff', [CutoffController::class, 'bangun'])->name('cutoff.bangun');
        Route::get('/integrasi', [IntegrasiController::class, 'index'])->name('integrasi');
        Route::post('/integrasi/terapkan', [IntegrasiController::class, 'terapkan'])->middleware('throttle:6,1')->name('integrasi.terapkan');
        Route::get('/master', [MasterDataController::class, 'index'])->name('master');
        Route::put('/master/klaster/{id}', [MasterDataController::class, 'ubahKlaster'])->whereNumber('id')->name('master.klaster');
        Route::put('/master/sub-klaster/{id}', [MasterDataController::class, 'ubahSubKlaster'])->whereNumber('id')->name('master.sub-klaster');
        Route::put('/master/unit/{id}', [MasterDataController::class, 'ubahUnit'])->whereNumber('id')->name('master.unit');
    });

    // Perencanaan & penilaian usulan; otorisasi rinci di UsulanPsnPolicy.
    // Sidang pleno & rekomendasi resmi (integrasi alur usulan PSI).
    Route::prefix('perencanaan/pleno')->name('perencanaan.pleno.')->controller(PlenoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{pleno}', 'show')->whereNumber('pleno')->name('show');
        Route::put('/{pleno}', 'update')->whereNumber('pleno')->name('update');
        Route::post('/{pleno}/agenda', 'tambahAgenda')->whereNumber('pleno')->name('agenda');
        Route::delete('/{pleno}/agenda/{agenda}', 'hapusAgenda')->whereNumber(['pleno', 'agenda'])->name('agenda.hapus');
        Route::put('/{pleno}/keputusan', 'keputusan')->whereNumber('pleno')->name('keputusan');
        Route::post('/{pleno}/selesai', 'selesai')->whereNumber('pleno')->name('selesai');
    });
    Route::post('/perencanaan/{usulan}/rekomendasi', [RekomendasiController::class, 'simpan'])->whereNumber('usulan')->name('perencanaan.rekomendasi');
    Route::post('/perencanaan/{usulan}/kirim-psi', [RekomendasiController::class, 'kirim'])->whereNumber('usulan')->name('perencanaan.kirim-psi');
    Route::post('/perencanaan/sinkron-psi', [IntegrasiSifController::class, 'sinkron'])->name('perencanaan.sinkron-psi');
    Route::get('/perencanaan/{usulan}/dokumen/{dokumen}', [IntegrasiSifController::class, 'unduh'])->whereNumber(['usulan', 'dokumen'])->name('perencanaan.dokumen');
    Route::put('/perencanaan/{usulan}/pemetaan', [PerencanaanController::class, 'ubahPemetaan'])->whereNumber('usulan')->name('perencanaan.pemetaan');

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
            Route::get('/kpi-tren', 'kpiTren')->name('kpi-tren');
            Route::get('/dp-proyek', 'dpProyek')->name('dp-proyek');
            Route::get('/distribusi', 'distribusi')->name('distribusi');
            Route::get('/progres', 'progres')->name('progres');
            Route::get('/tren', 'tren')->name('tren');
            Route::get('/ro-kritis', 'roKritis')->name('ro-kritis');
            Route::get('/tahapan', 'tahapan')->name('tahapan');
            Route::get('/status-data', 'statusData')->name('status-data');
            Route::get('/trisula', 'trisula')->name('trisula');
            Route::get('/timeline-dp', 'timelineDp')->name('timeline-dp');
            Route::get('/aktivitas', 'aktivitas')->name('aktivitas');
            Route::get('/ikhtisar', 'ikhtisar')->name('ikhtisar');
            Route::get('/pelaksanaan', 'pelaksanaan')->name('pelaksanaan');
        });
    });
});
