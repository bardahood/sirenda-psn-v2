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
