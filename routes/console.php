<?php

use Illuminate\Support\Facades\Schedule;

// Snapshot bulanan: setiap tanggal 1 pukul 02.00 WIB membangun snapshot bulan lalu.
// Bawaan berstatus DRAFT (diverifikasi lalu diterbitkan manual dengan --terbit).
Schedule::command('psn:snapshot'.(config('psn_dashboard.snapshot.terbit_otomatis') ? ' --terbit' : ''))
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping();
