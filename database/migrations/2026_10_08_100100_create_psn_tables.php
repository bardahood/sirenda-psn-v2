<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil PSN dan relasi satu-ke-banyaknya (Gambaran Umum, lokasi, pendanaan,
 * kelembagaan, item narasi profil, dokumen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psn', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique()->comment('psn.id pada basis data lama');
            $table->uuid('uuid')->unique();
            $table->string('kode_psn', 50)->nullable()->unique()->comment('mis. DP.1-2026.1-01');
            $table->string('kode_krisna', 50)->nullable()->index();
            $table->string('kode_rkp', 50)->nullable();
            $table->string('kode_sub', 50)->nullable();
            $table->unsignedSmallInteger('rkp_tahun')->nullable();
            $table->foreignId('kategori_rkp_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode KTGR');
            $table->foreignId('program_id')->nullable()->constrained('ref_program')->nullOnDelete();
            $table->foreignId('klaster_id')->nullable()->constrained('ref_klaster')->nullOnDelete();
            $table->foreignId('sub_klaster_id')->nullable()->constrained('ref_sub_klaster')->nullOnDelete();
            $table->foreignId('klaster_pkpn_id')->nullable()->constrained('ref_klaster_pkpn')->nullOnDelete();

            $table->text('nama');
            $table->text('sub_proyek')->nullable();
            $table->longText('deskripsi')->nullable();
            $table->longText('output')->nullable();
            $table->longText('dampak')->nullable();
            $table->longText('dasar_penetapan')->nullable();
            $table->longText('kpu')->nullable()->comment('Kerangka Pendanaan/KPU');

            $table->foreignId('status_psn_id')->nullable()->constrained('ref_status_psn')->nullOnDelete();
            $table->string('ket_status', 255)->nullable();
            $table->foreignId('ketersediaan_info_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode SKIF; kolom psn.status lama');
            $table->unsignedSmallInteger('tahun_selesai')->nullable();
            $table->string('ket_tahun_selesai', 255)->nullable();

            // Nilai dalam Rupiah penuh. `investasi_anomali` ditandai ETL bila nilai
            // berada di luar rentang wajar (lihat config psn_dashboard.investasi).
            $table->decimal('rencana_investasi_rp', 24, 2)->nullable();
            $table->decimal('nilai_investasi_rp', 24, 2)->nullable();
            $table->boolean('investasi_anomali')->default(false);

            $table->unsignedTinyInteger('sumber_input')->nullable()->comment('psn.sumber lama (1/2) -- arti kode perlu dikonfirmasi');
            $table->string('pic_nama', 255)->nullable()->comment('PIC Bappenas (teks bebas pada data lama)');
            $table->string('catatan', 500)->nullable();
            $table->string('file_kerangka', 500)->nullable();
            $table->string('file_gambar', 500)->nullable();
            $table->string('file_visualisasi', 500)->nullable();
            $table->jejak();

            $table->index(['klaster_id', 'status_psn_id']);
        });

        // Kelembagaan per PSN: menormalkan kolom pengusul/penanggung_jawab/pelaksana/
        // pelaksana_2/pengelola/kontraktor/supervisi serta tabel psn_penanggung_jawab.
        Schema::create('psn_kelembagaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->string('peran', 30)->comment('PENGUSUL|PENANGGUNG_JAWAB|PELAKSANA|PENGELOLA|KONTRAKTOR|SUPERVISI');
            $table->foreignId('penanggung_jawab_id')->nullable()->constrained('ref_penanggung_jawab')->nullOnDelete();
            $table->foreignId('instansi_id')->nullable()->constrained('ref_instansi')->nullOnDelete();
            $table->text('nama_teks')->nullable()->comment('nama bebas bila tidak ada di referensi');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->jejak(false);
            $table->index(['psn_id', 'peran']);
        });

        // Unit pengampu (direktorat Bappenas / unit) per PSN -- dasar filter Direktorat dan scope RBAC.
        Schema::create('psn_unit_pengampu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('ref_unit_kerja')->cascadeOnDelete();
            $table->string('keterangan', 255)->nullable();
            $table->jejak(false);
            $table->unique(['psn_id', 'unit_kerja_id']);
            $table->index('unit_kerja_id');
        });

        // Lokasi (multi-provinsi/kabupaten). P7 menghitung PSN di tiap provinsi.
        Schema::create('psn_lokasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->string('provinsi_kode', 13);
            $table->string('kabupaten_kode', 13)->nullable();
            $table->string('keterangan', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable()->comment('titik lokasi (v2)');
            $table->decimal('lng', 10, 7)->nullable();
            $table->jejak();
            $table->foreign('provinsi_kode')->references('kode')->on('ref_wilayah');
            $table->foreign('kabupaten_kode')->references('kode')->on('ref_wilayah');
            $table->index(['provinsi_kode', 'psn_id']);
        });

        // Indikasi sumber pendanaan per PSN (menggantikan psn.pendanaan JSON & psn_pendanaan).
        Schema::create('psn_sumber_dana', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('sumber_dana_id')->constrained('ref_sumber_dana');
            $table->decimal('nilai_rp', 24, 2)->nullable()->comment('porsi investasi bila diketahui; null = belum dirinci');
            $table->string('keterangan', 255)->nullable();
            $table->jejak(false);
            $table->unique(['psn_id', 'sumber_dana_id']);
        });

        Schema::create('psn_sdgs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('sdgs_id')->constrained('ref_kode')->comment('ref_kode SDGS');
            $table->string('keterangan', 255)->nullable();
            $table->jejak(false);
            $table->unique(['psn_id', 'sdgs_id']);
        });

        // Item narasi Project Profile per bagian (master TYIT: Urgensi, Dasar Hukum,
        // Tujuan Utama, ... Output). Dasar penghitungan kelengkapan per bagian.
        Schema::create('psn_profil_item', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('bagian_id')->constrained('ref_kode')->comment('ref_kode TYIT');
            $table->longText('isi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('file_bukti', 500)->nullable();
            $table->jejak();
            $table->index(['psn_id', 'bagian_id']);
        });

        // Target capaian PSN per tahun dalam bentuk narasi (target_psn lama).
        Schema::create('psn_target_tahunan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->text('uraian');
            $table->jejak();
            $table->index(['psn_id', 'tahun']);
        });

        Schema::create('psn_dukungan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->text('uraian');
            $table->jejak();
        });

        // Fasilitas kemudahan yang diterima (master FAST).
        Schema::create('psn_fasilitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->foreignId('fasilitas_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode FAST');
            $table->text('keterangan')->nullable();
            $table->jejak();
        });

        // Kebutuhan status PSN tahun berikutnya + justifikasi (psn_kebutuhan jenis B).
        Schema::create('psn_evaluasi_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->text('kebutuhan_status');
            $table->text('justifikasi')->nullable();
            $table->string('file_bukti', 500)->nullable();
            $table->jejak();
        });

        Schema::create('psn_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode KATD');
            $table->text('judul');
            $table->text('deskripsi')->nullable();
            $table->string('path', 500)->nullable();
            $table->jejak();
        });

        // Pemetaan pemangku kepentingan & kerangka kelembagaan (4 level).
        Schema::create('psn_stakeholder', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->string('jenis', 5)->nullable()->comment('A=Stakeholder Mapping, B=Kerangka Kelembagaan (kode lama)');
            $table->foreignId('peran_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode PRAN');
            $table->unsignedTinyInteger('level')->nullable()->comment('1-4: Kebijakan, Fasilitator Wilayah, Operator/Investor, Partisipan');
            $table->text('peran')->nullable();
            $table->text('instansi_utama')->nullable();
            $table->text('instansi_pendukung')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('catatan')->nullable();
            $table->string('file_bukti', 500)->nullable();
            $table->jejak();
        });
    }

    public function down(): void
    {
        foreach (['psn_stakeholder', 'psn_dokumen', 'psn_evaluasi_status', 'psn_fasilitas', 'psn_dukungan',
            'psn_target_tahunan', 'psn_profil_item', 'psn_sdgs', 'psn_sumber_dana', 'psn_lokasi',
            'psn_unit_pengampu', 'psn_kelembagaan', 'psn'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
