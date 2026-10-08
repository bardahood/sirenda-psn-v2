<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monev/kunjungan lapangan (monev_* lama) dan penilaian usulan PSN
 * berdasarkan 14 kriteria Permen PPN/Bappenas No. 4/2025 (tabel `pertanyaan` lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monev', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->string('jenis', 20)->comment('PENGENDALIAN|PERENCANAAN (monev_header.jenis 1/2 lama)');
            $table->date('tanggal');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_akhir')->nullable();
            $table->text('pelaksana')->nullable();
            $table->string('kepatuhan_pelaporan', 50)->nullable();
            $table->decimal('skor', 6, 2)->nullable();
            $table->string('nilai_rekomendasi', 50)->nullable();
            $table->text('kesimpulan')->nullable();
            $table->text('rekomendasi_lanjutan')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('dokumen_path', 500)->nullable();
            $table->jejak();
        });

        Schema::create('monev_kelembagaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monev_id')->constrained('monev')->cascadeOnDelete();
            $table->string('peran', 50);
            $table->string('instansi_tercatat', 255)->nullable();
            $table->string('instansi_aktual', 255)->nullable();
            $table->string('kesesuaian', 20)->nullable()->comment('Ya|Sebagian|Tidak');
            $table->text('catatan')->nullable();
            $table->jejak(false);
        });

        Schema::create('monev_capaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monev_id')->constrained('monev')->cascadeOnDelete();
            $table->foreignId('kegiatan_id')->nullable()->constrained('kegiatan')->nullOnDelete();
            $table->text('uraian')->nullable();
            $table->string('satuan', 50)->nullable();
            $table->decimal('target', 24, 4)->nullable();
            $table->decimal('realisasi_klaim', 24, 4)->nullable();
            $table->decimal('realisasi_aktual', 24, 4)->nullable();
            $table->string('kesesuaian', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->string('file_bukti', 500)->nullable();
            $table->jejak(false);
        });

        Schema::create('monev_anggaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monev_id')->constrained('monev')->cascadeOnDelete();
            $table->foreignId('kegiatan_id')->nullable()->constrained('kegiatan')->nullOnDelete();
            $table->text('uraian')->nullable();
            $table->string('sumber', 200)->nullable();
            $table->decimal('rencana_rp', 24, 2)->nullable();
            $table->decimal('realisasi_klaim_rp', 24, 2)->nullable();
            $table->decimal('realisasi_aktual_rp', 24, 2)->nullable();
            $table->string('kesesuaian', 20)->nullable();
            $table->string('bukti_tersedia', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->string('file_bukti', 500)->nullable();
            $table->jejak(false);
        });

        Schema::create('monev_risiko', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monev_id')->constrained('monev')->cascadeOnDelete();
            $table->foreignId('risiko_id')->nullable()->constrained('risiko')->nullOnDelete();
            $table->text('uraian')->nullable();
            $table->string('kategori', 50)->nullable();
            $table->string('level_awal', 20)->nullable();
            $table->string('level_harapan', 20)->nullable();
            $table->string('level_aktual', 20)->nullable();
            $table->text('rencana')->nullable();
            $table->unsignedTinyInteger('progres_persen')->nullable();
            $table->string('status', 50)->nullable();
            $table->string('hasil_evaluasi', 50)->nullable()->comment('Sesuai/Lebih Baik | Memburuk');
            $table->text('catatan')->nullable();
            $table->jejak(false);
        });

        Schema::create('monev_regulasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monev_id')->constrained('monev')->cascadeOnDelete();
            $table->foreignId('regulasi_id')->nullable()->constrained('regulasi')->nullOnDelete();
            $table->text('uraian')->nullable();
            $table->text('justifikasi')->nullable();
            $table->unsignedSmallInteger('target_tahun')->nullable();
            $table->string('status_klaim', 50)->nullable();
            $table->string('status_aktual', 50)->nullable();
            $table->text('penanggung_jawab')->nullable();
            $table->string('bukti_tersedia', 20)->nullable();
            $table->string('kesesuaian', 20)->nullable();
            $table->text('catatan')->nullable();
            $table->jejak(false);
        });

        Schema::create('monev_dokumentasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monev_id')->constrained('monev')->cascadeOnDelete();
            $table->foreignId('kategori_id')->nullable()->constrained('ref_kode')->nullOnDelete()->comment('ref_kode KATD');
            $table->text('judul')->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('path', 500)->nullable();
            $table->jejak(false);
        });

        Schema::create('monev_evaluasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monev_id')->constrained('monev')->cascadeOnDelete();
            $table->text('hasil_evaluasi')->nullable();
            $table->text('isu_tantangan')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->text('status_pengendalian')->nullable();
            $table->jejak(false);
        });

        // Kriteria penilaian usulan. UTAMA = gate Ya/Tidak (KU1-KU3);
        // PENDUKUNG (6), KESIAPAN (5), LOKASI, TRISULA = skor 0-3 per sub-kriteria.
        Schema::create('ref_kriteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique()->comment('pertanyaan.id lama');
            $table->string('kelompok', 20)->comment('UTAMA|PENDUKUNG|KESIAPAN|LOKASI|TRISULA');
            $table->string('kode', 10)->unique();
            $table->foreignId('induk_id')->nullable()->constrained('ref_kriteria')->nullOnDelete()->comment('sub-kriteria');
            $table->text('uraian');
            $table->text('rubrik')->nullable();
            $table->string('tipe_nilai', 15)->comment('YA_TIDAK|SKOR_0_3');
            $table->string('kondisional', 50)->nullable()->comment('mis. PENGUSUL_KL, PENGUSUL_PEMDA, PENGUSUL_BU, INFRASTRUKTUR');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
        });

        // Usulan PSN baru (mis. Pemutakhiran RKP 2027; psn_2027 lama).
        Schema::create('usulan_psn', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->unsignedSmallInteger('tahun_rkp');
            $table->text('nama');
            $table->foreignId('klaster_id')->nullable()->constrained('ref_klaster')->nullOnDelete();
            $table->foreignId('pengusul_instansi_id')->nullable()->constrained('ref_instansi')->nullOnDelete();
            $table->string('pengusul_teks', 255)->nullable();
            $table->string('jenis_pengusul', 20)->nullable()->comment('KL|PEMDA|BUMN_SWASTA');
            $table->boolean('is_infrastruktur')->default(false);
            $table->decimal('nilai_investasi_rp', 24, 2)->nullable();
            $table->foreignId('psn_id')->nullable()->constrained('psn')->nullOnDelete()->comment('PSN eksisting terkait / hasil penetapan');
            $table->string('status', 20)->default('DIAJUKAN')->comment('DIAJUKAN|DINILAI|DITETAPKAN|DITOLAK');
            $table->jejak();
        });

        Schema::create('usulan_lokasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->constrained('usulan_psn')->cascadeOnDelete();
            $table->string('provinsi_kode', 13);
            $table->string('kabupaten_kode', 13)->nullable();
            $table->foreign('provinsi_kode')->references('kode')->on('ref_wilayah');
            $table->foreign('kabupaten_kode')->references('kode')->on('ref_wilayah');
        });

        // Satu sesi penilaian (mis. Rapat Pleno II). Kolom skor adalah cache hasil
        // ScoringService -- sumber kebenaran tetap penilaian_skor.
        Schema::create('penilaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->constrained('usulan_psn')->cascadeOnDelete();
            $table->foreignId('monev_id')->nullable()->constrained('monev')->nullOnDelete()->comment('kunjungan verifikasi lapangan terkait');
            $table->string('forum', 100)->nullable()->comment('mis. Rapat Pleno II Pemutakhiran RKP 2027');
            $table->date('tanggal')->nullable();
            $table->foreignId('penilai_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT|FINAL');
            $table->boolean('gate_lulus')->nullable();
            $table->decimal('skor_pendukung', 5, 2)->nullable();
            $table->decimal('skor_kesiapan', 5, 2)->nullable();
            $table->decimal('skor_lokasi', 5, 2)->nullable();
            $table->decimal('skor_trisula', 5, 2)->nullable();
            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('rekomendasi', 30)->nullable()->comment('DIREKOMENDASIKAN|DIPERTIMBANGKAN|TIDAK_DIREKOMENDASIKAN|DITOLAK');
            $table->text('catatan')->nullable();
            $table->jejak();
        });

        Schema::create('penilaian_skor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penilaian_id')->constrained('penilaian')->cascadeOnDelete();
            $table->foreignId('kriteria_id')->constrained('ref_kriteria');
            $table->unsignedTinyInteger('nilai')->nullable()->comment('YA_TIDAK: 1/0; SKOR_0_3: 0-3; null = tidak berlaku');
            $table->text('temuan')->nullable();
            $table->string('bukti_path', 500)->nullable();
            $table->jejak(false);
            $table->unique(['penilaian_id', 'kriteria_id']);
        });
    }

    public function down(): void
    {
        foreach (['penilaian_skor', 'penilaian', 'usulan_lokasi', 'usulan_psn', 'ref_kriteria',
            'monev_evaluasi', 'monev_dokumentasi', 'monev_regulasi', 'monev_risiko', 'monev_anggaran',
            'monev_capaian', 'monev_kelembagaan', 'monev'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
