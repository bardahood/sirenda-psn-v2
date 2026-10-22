<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dimensi filter "Tahun target selesai" pada snapshot per cut-off (aditif).
 * Snapshot yang sudah ada diisi dari psn.tahun_selesai saat migrasi (nilai historis tidak tersedia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshot_psn', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_selesai')->nullable()->after('kategori')->comment('psn.tahun_selesai pada cut-off');
            $table->index(['periode_cutoff_id', 'tahun_selesai']);
        });

        DB::table('snapshot_psn')->join('psn', 'psn.id', '=', 'snapshot_psn.psn_id')
            ->update(['snapshot_psn.tahun_selesai' => DB::raw('psn.tahun_selesai')]);
    }

    public function down(): void
    {
        Schema::table('snapshot_psn', function (Blueprint $table) {
            $table->dropIndex(['periode_cutoff_id', 'tahun_selesai']);
            $table->dropColumn('tahun_selesai');
        });
    }
};
