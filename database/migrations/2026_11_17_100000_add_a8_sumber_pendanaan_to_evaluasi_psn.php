<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A8 Sumber pendanaan sesuai "Daftar Pilihan" kertas kerja (aditif). Kosong = turunan dari sumber dana profil. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluasi_psn', function (Blueprint $table) {
            $table->string('a8_sumber_pendanaan', 15)->nullable()->after('a6_tahun_penetapan')
                ->comment('APBN|APBD|BUMN|BADAN_USAHA|DANANTARA|LAINNYA|CAMPURAN; kosong = dari profil');
        });
    }

    public function down(): void
    {
        Schema::table('evaluasi_psn', fn (Blueprint $t) => $t->dropColumn('a8_sumber_pendanaan'));
    }
};
