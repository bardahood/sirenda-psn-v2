<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Register risiko (harapan vs aktual, skor kemungkinan x dampak), kebutuhan
 * regulasi dengan tahap pipeline, serta isu & debottlenecking dengan PIC/tenggat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risiko', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('kegiatan_id')->nullable()->constrained('kegiatan')->nullOnDelete();
            $table->text('uraian');
            $table->foreignId('kategori_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode RISK');

            // Level dalam label (data lama) dan skala 1-5 (baru). Bila kemungkinan
            // & dampak terisi, level diturunkan dari skor oleh StatusResolver.
            $table->string('level_awal', 20)->nullable()->comment('Rendah|Sedang|Tinggi|Sangat Tinggi');
            $table->unsignedTinyInteger('kemungkinan_awal')->nullable();
            $table->unsignedTinyInteger('dampak_awal')->nullable();
            $table->string('level_harapan', 20)->nullable()->comment('risiko residual harapan');
            $table->unsignedTinyInteger('kemungkinan_harapan')->nullable();
            $table->unsignedTinyInteger('dampak_harapan')->nullable();

            $table->text('rencana_perlakuan')->nullable();
            $table->text('penanggung_jawab')->nullable();
            $table->boolean('is_titik_kritis')->default(false);
            $table->text('catatan')->nullable();
            $table->jejak();
            $table->index(['psn_id', 'level_harapan']);
        });

        // Tahun rencana pelaksanaan perlakuan (cek_2026..cek_2029 lama).
        Schema::create('risiko_tahun_perlakuan', function (Blueprint $table) {
            $table->foreignId('risiko_id')->constrained('risiko')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->primary(['risiko_id', 'tahun']);
        });

        // Rincian perlakuan & penanggung jawabnya (psn_pj_perlakuan lama).
        Schema::create('risiko_perlakuan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risiko_id')->constrained('risiko')->cascadeOnDelete();
            $table->string('uraian', 255)->nullable();
            $table->foreignId('instansi_id')->nullable()->constrained('ref_instansi')->nullOnDelete()->comment('pelaksana perlakuan (kode INST lama)');
            $table->string('keterangan', 255)->nullable();
            $table->jejak(false);
        });

        // Pemantauan risiko residual aktual per periode (pelaporan rutin atau hasil monev).
        Schema::create('risiko_pemantauan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risiko_id')->constrained('risiko')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('triwulan')->nullable();
            $table->string('level_aktual', 20)->nullable();
            $table->unsignedTinyInteger('kemungkinan_aktual')->nullable();
            $table->unsignedTinyInteger('dampak_aktual')->nullable();
            $table->unsignedTinyInteger('progres_persen')->nullable();
            $table->string('status_perlakuan', 50)->nullable()->comment('BELUM|BERJALAN|SELESAI');
            $table->string('sumber', 20)->default('PELAPORAN')->comment('PELAPORAN|MONEV');
            $table->foreignId('monev_risiko_id')->nullable()->comment('diisi bila sumber = MONEV');
            $table->text('catatan')->nullable();
            $table->jejak(false);
            $table->index(['risiko_id', 'tanggal']);
        });

        Schema::create('regulasi', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->text('nama');
            $table->text('justifikasi')->nullable();
            $table->text('penanggung_jawab')->nullable();
            $table->string('tahap', 20)->default('IDENTIFIKASI')->comment('IDENTIFIKASI|PENYUSUNAN|HARMONISASI|DITETAPKAN');
            $table->string('nomor_penetapan', 255)->nullable();
            $table->date('tanggal_penetapan')->nullable();
            $table->text('catatan')->nullable();
            $table->jejak();
            $table->index(['psn_id', 'tahap']);
        });

        // Target tahun penyelesaian regulasi (cek_2026..cek_2029 lama).
        Schema::create('regulasi_target_tahun', function (Blueprint $table) {
            $table->foreignId('regulasi_id')->constrained('regulasi')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->primary(['regulasi_id', 'tahun']);
        });

        // Isu/permasalahan dan kebutuhan dukungan (psn_kebutuhan jenis A lama),
        // diperluas dengan PIC, tenggat, dan status untuk debottlenecking.
        Schema::create('isu', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('kegiatan_id')->nullable()->constrained('kegiatan')->nullOnDelete();
            $table->text('uraian');
            $table->text('kebutuhan_dukungan')->nullable();
            $table->foreignId('pic_unit_kerja_id')->nullable()->constrained('ref_unit_kerja')->nullOnDelete();
            $table->string('pic_nama', 255)->nullable();
            $table->date('tenggat')->nullable();
            $table->string('status', 20)->default('TERBUKA')->comment('TERBUKA|PROSES|SELESAI');
            $table->date('tanggal_selesai')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->string('file_bukti', 500)->nullable();
            $table->jejak();
            $table->index(['status', 'tenggat']);
        });
    }

    public function down(): void
    {
        foreach (['isu', 'regulasi_target_tahun', 'regulasi', 'risiko_pemantauan', 'risiko_perlakuan',
            'risiko_tahun_perlakuan', 'risiko'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
