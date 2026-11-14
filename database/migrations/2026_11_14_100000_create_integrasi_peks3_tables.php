<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkron SIRENDA PEKS3 -> dashboard (aditif). Lihat docs/integrasi-peks3.md.
 *  - integrasi_peks3_psn: versi & sidik paket terakhir per PSN (urutan & idempotensi).
 *  - integrasi_peks3_log: log setiap permintaan masuk dan setiap penerapan.
 *  - integrasi_peks3_status: satu baris status antrean penerapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrasi_peks3_psn', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_psn_id')->primary()->comment('psn.id di SIRENDA PEKS3');
            $table->unsignedBigInteger('versi')->comment('versi paket (mikrodetik epoch); paket lebih lama diabaikan');
            $table->char('sidik', 64)->comment('SHA-256 isi paket; isi sama = tidak ada perubahan');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->boolean('dihapus')->default(false);
            $table->timestamp('diterima_at')->nullable();
        });

        Schema::create('integrasi_peks3_log', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20)->comment('PSN|REFERENSI|REKONSILIASI|TERAPKAN');
            $table->unsignedBigInteger('legacy_psn_id')->nullable();
            $table->string('hasil', 20)->comment('DITERIMA|TETAP|VERSI_LAMA|BERHASIL|GAGAL');
            $table->text('pesan')->nullable();
            $table->json('ringkasan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index('created_at');
            $table->index(['legacy_psn_id', 'created_at']);
        });

        Schema::create('integrasi_peks3_status', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('tertunda_sejak')->nullable()->comment('perubahan pertama yang belum diterapkan; null = tidak ada antrean');
            $table->timestamp('perubahan_terakhir_at')->nullable();
            $table->timestamp('diterapkan_at')->nullable();
            $table->decimal('durasi_detik', 8, 1)->nullable();
            $table->json('hasil')->nullable();
            $table->text('galat')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrasi_peks3_status');
        Schema::dropIfExists('integrasi_peks3_log');
        Schema::dropIfExists('integrasi_peks3_psn');
    }
};
