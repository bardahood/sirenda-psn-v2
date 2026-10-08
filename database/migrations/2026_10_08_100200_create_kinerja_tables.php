<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indikator kinerja, penerima manfaat, kontribusi Trisula, dan KP/RO beserta
 * target-realisasinya. Tabel lama menyimpan target per tahun sebagai kolom
 * melebar (target_2026, realisasi_2026, ... target_persen_1..12); di sini
 * disimpan memanjang per periode agar dapat diagregasi dan di-snapshot.
 * Persentase capaian tidak disimpan -- dihitung dari target & realisasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indikator', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->text('uraian');
            $table->string('satuan', 100)->nullable();
            $table->string('baseline', 100)->nullable();
            $table->unsignedSmallInteger('baseline_tahun')->nullable();
            $table->decimal('target_akhir', 24, 4)->nullable();
            $table->text('capaian')->nullable();
            $table->jejak();
        });

        Schema::create('indikator_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indikator_id')->constrained('indikator')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->decimal('target', 24, 4)->nullable();
            $table->decimal('realisasi', 24, 4)->nullable();
            $table->jejak(false);
            $table->unique(['indikator_id', 'tahun']);
        });

        Schema::create('penerima_manfaat', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->text('uraian');
            $table->string('satuan', 100)->nullable();
            $table->decimal('baseline', 24, 4)->nullable();
            $table->unsignedSmallInteger('baseline_tahun')->nullable();
            $table->decimal('target_akhir', 24, 4)->nullable();
            $table->text('capaian')->nullable();
            $table->jejak();
        });

        Schema::create('penerima_manfaat_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerima_manfaat_id')->constrained('penerima_manfaat')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->decimal('target', 24, 4)->nullable();
            $table->decimal('realisasi', 24, 4)->nullable();
            $table->jejak(false);
            $table->unique(['penerima_manfaat_id', 'tahun']);
        });

        Schema::create('trisula', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode KTAC: Kemiskinan/Pertumbuhan Ekonomi/SDM');
            $table->text('indikator')->nullable();
            $table->text('kontribusi')->nullable();
            $table->string('satuan', 100)->nullable();
            $table->decimal('baseline', 24, 4)->nullable();
            $table->unsignedSmallInteger('baseline_tahun')->nullable();
            $table->decimal('target_akhir', 24, 4)->nullable();
            $table->text('capaian')->nullable();
            $table->jejak();
        });

        // periode_ke: 0 untuk TAHUNAN, 1-4 untuk TRIWULAN.
        Schema::create('trisula_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trisula_id')->constrained('trisula')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('periode', 10)->default('TAHUNAN')->comment('TAHUNAN|TRIWULAN');
            $table->unsignedTinyInteger('periode_ke')->default(0);
            $table->decimal('target', 24, 4)->nullable();
            $table->decimal('realisasi', 24, 4)->nullable();
            $table->string('status', 255)->nullable();
            $table->jejak(false);
            $table->unique(['trisula_id', 'tahun', 'periode', 'periode_ke']);
        });

        // KP/RO: Rincian Output, Proyek, Aktivitas, dan Critical Path (psn_kegiatan +
        // psn_kegiatan_cp lama). Critical path = baris turunan (parent_id) atau kunci.
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_ref', 40)->nullable()->unique()->comment('psn_kegiatan:{id} atau psn_kegiatan_cp:{id}');
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('jenis_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode TACT: RO/Proyek/No RO/RO Tambahan');
            $table->text('nama');
            $table->text('lokasi')->nullable();
            $table->string('pelaksana', 255)->nullable();
            $table->string('satuan_1', 100)->nullable();
            $table->string('satuan_2', 100)->nullable();
            $table->decimal('baseline_1', 24, 4)->nullable();
            $table->decimal('baseline_2', 24, 4)->nullable();
            $table->unsignedSmallInteger('baseline_tahun_1')->nullable();
            $table->unsignedSmallInteger('baseline_tahun_2')->nullable();
            $table->decimal('target_akhir_1', 24, 4)->nullable();
            $table->decimal('target_akhir_2', 24, 4)->nullable();
            $table->boolean('is_critical_path')->default(false);
            $table->foreignId('sumber_dana_id')->nullable()->constrained('ref_sumber_dana')->nullOnDelete();
            $table->string('status_teks', 100)->nullable()->comment('isian bebas lama, mis. "99,7 %" / "Selesai"');
            $table->text('keterangan')->nullable();
            $table->jejak();
            $table->index(['psn_id', 'is_critical_path']);
        });

        // Target & realisasi KP/RO per periode. periode_ke: 0 = TAHUNAN,
        // 1-4 = TRIWULAN, 1-12 = BULANAN. Nilai rupiah dalam Rupiah penuh.
        Schema::create('kegiatan_target', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('periode', 10)->comment('TAHUNAN|TRIWULAN|BULANAN');
            $table->unsignedTinyInteger('periode_ke')->default(0);
            $table->unsignedTinyInteger('metode')->nullable()->comment('psn_kegiatan_detil.metode lama');
            $table->decimal('target_1', 24, 4)->nullable()->comment('volume satuan_1');
            $table->decimal('target_2', 24, 4)->nullable()->comment('volume satuan_2');
            $table->decimal('target_persen', 7, 2)->nullable()->comment('rencana progres fisik kumulatif (%)');
            $table->decimal('target_fisik', 24, 4)->nullable();
            $table->decimal('pagu_rp', 24, 2)->nullable();
            $table->decimal('realisasi_1', 24, 4)->nullable();
            $table->decimal('realisasi_2', 24, 4)->nullable();
            $table->decimal('realisasi_persen', 7, 2)->nullable()->comment('realisasi progres fisik kumulatif (%)');
            $table->decimal('realisasi_fisik', 24, 4)->nullable();
            $table->decimal('realisasi_anggaran_rp', 24, 2)->nullable();
            $table->string('status', 50)->nullable();
            $table->text('permasalahan')->nullable();
            $table->string('bukti_path', 500)->nullable();
            $table->timestamp('dilaporkan_at')->nullable()->comment('waktu pembaruan realisasi -- dasar status "Tanpa data"');
            $table->jejak(false);
            $table->unique(['kegiatan_id', 'tahun', 'periode', 'periode_ke'], 'kegiatan_target_periode_unique');
            $table->index(['tahun', 'periode', 'periode_ke']);
        });
    }

    public function down(): void
    {
        foreach (['kegiatan_target', 'kegiatan', 'trisula_target', 'trisula', 'penerima_manfaat_target',
            'penerima_manfaat', 'indikator_target', 'indikator'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
