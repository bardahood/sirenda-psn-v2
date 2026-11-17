<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * psn.tahun_terbit: tahun penetapan PSN dari SIRENDA PEKS3 (psn.tahun_terbit), diisi LegacyImporter.
 * Aditif. Bila kolom sudah dibuat manual (mis. NOT NULL tanpa default, yang menggagalkan impor),
 * kolom diseragamkan menjadi SMALLINT UNSIGNED NULL tanpa membuang isinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $komentar = 'tahun penetapan PSN (SIRENDA PEKS3 psn.tahun_terbit)';
        if (! Schema::hasColumn('psn', 'tahun_terbit')) {
            Schema::table('psn', fn (Blueprint $t) => $t->unsignedSmallInteger('tahun_terbit')->nullable()->after('ket_tahun_selesai')->comment($komentar));

            return;
        }
        DB::statement("ALTER TABLE psn MODIFY tahun_terbit SMALLINT UNSIGNED NULL COMMENT '{$komentar}'");
    }

    public function down(): void
    {
        Schema::table('psn', fn (Blueprint $t) => $t->dropColumn('tahun_terbit'));
    }
};
