<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrasi usulan PSN dengan aplikasi permohonan Dit. PSI (Fase 8). Seluruhnya tabel baru;
 * usulan_psn tetap menjadi entitas inti (nama, tahun RKP, klaster, pengusul, lokasi) dan
 * rincian khas PSI disimpan di tabel pendamping 1:1 / 1:n.
 *
 * Alur: PSI -> (API masuk) usulan -> penilaian (sudah ada) -> pleno -> rekomendasi -> (API keluar) PSI.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Surat pengusulan (satu surat dapat memuat beberapa usulan / "surat jamak").
        Schema::create('usulan_surat', function (Blueprint $table) {
            $table->id();
            $table->string('psi_surat_id', 64)->unique()->comment('ID surat di aplikasi PSI');
            $table->string('nomor', 150)->nullable();
            $table->string('sifat', 50)->nullable();
            $table->string('lampiran', 150)->nullable();
            $table->string('tempat', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->text('ditujukan_kepada')->nullable();
            $table->text('perihal')->nullable();
            $table->string('instansi_pengusul', 255)->nullable();
            $table->jejak();
        });

        // Amplop integrasi + isian khas formulir PSI, 1:1 dengan usulan_psn.
        Schema::create('usulan_psi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->unique()->constrained('usulan_psn')->cascadeOnDelete();
            $table->string('psi_id', 64)->unique()->comment('ID usulan di aplikasi PSI');
            $table->foreignId('surat_id')->nullable()->constrained('usulan_surat')->nullOnDelete();
            $table->unsignedInteger('versi')->default(1)->comment('versi isian PSI terakhir yang diterima');
            $table->string('status_psi', 20)->default('DIAJUKAN')->comment('DIAJUKAN|DIREVISI|DITARIK (status di aplikasi PSI)');
            $table->timestamp('diajukan_at')->nullable()->comment('waktu pengajuan di PSI');
            $table->timestamp('diterima_at')->nullable()->comment('pertama kali diterima SIRENDA');
            $table->timestamp('diperbarui_at')->nullable()->comment('versi terakhir diterima SIRENDA');
            $table->boolean('perlu_tinjau_ulang')->default(false)->comment('PSI mengirim versi baru setelah penilaian FINAL');
            // Data umum
            $table->string('instansi_pengusul', 255)->nullable();
            $table->string('jenis_instansi', 100)->nullable();
            $table->string('sektor', 255)->nullable();
            $table->string('klaster_teks', 255)->nullable()->comment('klaster sebagaimana dikirim PSI');
            $table->string('jenis_psn', 100)->nullable();
            $table->json('kontak')->nullable();
            // Rincian usulan & kesiapan (HTML sudah disaring HtmlAman)
            $table->text('tujuan')->nullable();
            $table->text('sasaran')->nullable();
            $table->longText('lingkup_pekerjaan_html')->nullable();
            $table->json('rincian_lain')->nullable()->comment('[{judul, isi_html}] bagian rincian lain dari PSI');
            $table->longText('penjelasan_jalur_kritis_html')->nullable();
            $table->string('diagram_jalur_kritis_url', 1000)->nullable();
            $table->json('kesiapan_lain')->nullable()->comment('[{judul, isi_html}]');
            $table->json('peringatan_impor')->nullable()->comment('nilai yang tidak dapat dipetakan ke referensi SIRENDA');
            $table->json('payload_terakhir')->nullable()->comment('payload mentah terakhir (audit)');
            // Pengiriman hasil ke PSI
            $table->string('kirim_status', 20)->default('BELUM')->comment('BELUM|TERKIRIM|GAGAL');
            $table->timestamp('kirim_terakhir_at')->nullable();
            $table->unsignedInteger('kirim_percobaan')->default(0);
            $table->jejak();
        });

        Schema::create('usulan_jalur_kritis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->constrained('usulan_psn')->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('kode', 30)->comment('ID langkah dari PSI');
            $table->text('kegiatan');
            $table->string('target_selesai', 100)->nullable()->comment('teks bebas PSI, mis. "Jul–Ags 2025"');
            $table->json('sebelumnya')->nullable()->comment('kode langkah pendahulu');
            $table->boolean('is_jalur_kritis')->default(false);
            $table->jejak(false);
            $table->index(['usulan_id', 'urutan']);
        });

        Schema::create('usulan_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->constrained('usulan_psn')->cascadeOnDelete();
            $table->string('jenis', 30)->comment('PDF_USULAN|SURAT_PENGUSULAN|LAMPIRAN');
            $table->string('judul', 255)->nullable();
            $table->string('nama_berkas', 255)->nullable();
            $table->string('url', 1000)->nullable()->comment('lokasi berkas di PSI');
            $table->unsignedBigInteger('ukuran')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->jejak(false);
            $table->index(['usulan_id', 'jenis']);
        });

        // Sidang pleno pembahasan hasil penilaian.
        Schema::create('pleno', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255)->comment('mis. Rapat Pleno II Pemutakhiran RKP 2027');
            $table->date('tanggal');
            $table->string('tempat', 255)->nullable();
            $table->string('pimpinan', 255)->nullable();
            $table->unsignedSmallInteger('tahun_rkp')->nullable();
            $table->string('status', 20)->default('TERJADWAL')->comment('TERJADWAL|SELESAI');
            $table->text('catatan_umum')->nullable();
            $table->jejak();
        });

        Schema::create('pleno_usulan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pleno_id')->constrained('pleno')->cascadeOnDelete();
            $table->foreignId('usulan_id')->constrained('usulan_psn')->cascadeOnDelete();
            $table->foreignId('penilaian_id')->nullable()->constrained('penilaian')->nullOnDelete()->comment('penilaian FINAL yang dibahas');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->string('keputusan', 30)->nullable()->comment('DISETUJUI|DISETUJUI_CATATAN|DITUNDA|DITOLAK');
            $table->text('catatan')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->jejak(false);
            $table->unique(['pleno_id', 'usulan_id']);
        });

        // Rekomendasi resmi pasca pleno (dapat berbeda dari rekomendasi hitungan ScoringService).
        Schema::create('usulan_rekomendasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->constrained('usulan_psn')->cascadeOnDelete();
            $table->foreignId('penilaian_id')->nullable()->constrained('penilaian')->nullOnDelete();
            $table->foreignId('pleno_usulan_id')->nullable()->constrained('pleno_usulan')->nullOnDelete();
            $table->string('rekomendasi', 30)->comment('DIREKOMENDASIKAN|DIPERTIMBANGKAN|TIDAK_DIREKOMENDASIKAN|DITOLAK');
            $table->text('catatan')->nullable();
            $table->string('nomor_dokumen', 150)->nullable();
            $table->date('tanggal');
            $table->string('status', 20)->default('DRAFT')->comment('DRAFT|FINAL');
            $table->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ditetapkan_at')->nullable();
            $table->jejak();
            $table->index(['usulan_id', 'status']);
        });

        // Log setiap pertukaran data dengan PSI (masuk & keluar).
        Schema::create('integrasi_psi_log', function (Blueprint $table) {
            $table->id();
            $table->string('arah', 10)->comment('MASUK|KELUAR');
            $table->string('aksi', 30)->comment('USULAN|TARIK|AMBIL_HASIL|KIRIM_HASIL');
            $table->foreignId('usulan_id')->nullable()->constrained('usulan_psn')->nullOnDelete();
            $table->string('psi_id', 64)->nullable();
            $table->unsignedSmallInteger('status_http')->nullable();
            $table->boolean('berhasil')->default(false);
            $table->text('pesan')->nullable();
            $table->json('payload')->nullable();
            $table->text('respons')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['psi_id', 'created_at']);
            $table->index(['arah', 'berhasil']);
        });
    }

    public function down(): void
    {
        foreach (['integrasi_psi_log', 'usulan_rekomendasi', 'pleno_usulan', 'pleno', 'usulan_dokumen', 'usulan_jalur_kritis', 'usulan_psi', 'usulan_surat'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
