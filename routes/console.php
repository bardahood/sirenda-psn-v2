<?php

use Illuminate\Support\Facades\Schedule;

// Snapshot bulanan: setiap tanggal 1 pukul 02.00 WIB membangun snapshot bulan lalu.
// Bawaan berstatus DRAFT (diverifikasi lalu diterbitkan manual dengan --terbit).
Schedule::command('psn:snapshot'.(config('psn_dashboard.snapshot.terbit_otomatis') ? ' --terbit' : ''))
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping();

// Ambil usulan PSN dari API SIF (Dit. PSI) setiap jam bila SIF_SINKRON_OTOMATIS=true.
Schedule::command('psi:sinkron')
    ->hourly()
    ->withoutOverlapping()
    ->when(fn () => config('psn_dashboard.psi.sif.sinkron_otomatis'));

// Terapkan kiriman SIRENDA PEKS3 ke dashboard: dicek setiap menit, berjalan bila ada antrean yang sudah tenang
// (PEKS3_JEDA_DETIK) atau sudah menunggu terlalu lama (PEKS3_TUNGGU_MAKS_DETIK).
Schedule::command('peks3:terapkan')
    ->everyMinute()
    ->withoutOverlapping(30)
    ->when(fn () => config('psn_dashboard.peks3.aktif'));

// Arah balik: isian menu Pengisian dikirim ke SIRENDA PEKS3 (docs/integrasi-peks3.md §9).
Schedule::command('peks3:kirim-balik')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->when(fn () => config('psn_dashboard.peks3.balik.aktif'));
