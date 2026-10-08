<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tata kelola data: periode cut-off, alur pengisian & verifikasi per PSN,
 * jejak audit, log login, dan snapshot nilai dashboard per cut-off.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Periode pelaporan. Snapshot diterbitkan per cut-off; pembanding
        // "vs cut-off sebelumnya" dan tren bulanan membaca snapshot ini.
        Schema::create('periode_cutoff', function (Blueprint $table) {
            $table->id();
            $table->char('kode', 7)->unique()->comment('YYYY-MM');
            $table->date('tanggal_cutoff');
            $table->date('batas_pengisian')->nullable();
            $table->string('status', 10)->default('DRAFT')->comment('DRAFT|TERBIT');
            $table->timestamp('diterbitkan_at')->nullable();
            $table->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        // Alur persetujuan pemutakhiran profil per PSN per cut-off:
        // DRAFT -> DIAJUKAN -> DIVERIFIKASI / DIKEMBALIKAN.
        // Juga dasar indikator Kualitas Data (tepat waktu, menunggu verifikasi).
        Schema::create('pengisian_psn', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_cutoff_id')->constrained('periode_cutoff')->cascadeOnDelete();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->string('status', 15)->default('DRAFT')->comment('DRAFT|DIAJUKAN|DIVERIFIKASI|DIKEMBALIKAN');
            $table->timestamp('diajukan_at')->nullable();
            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_verifikator')->nullable();
            $table->timestamps();
            $table->unique(['periode_cutoff_id', 'psn_id']);
            $table->index(['periode_cutoff_id', 'status']);
        });

        Schema::create('pengisian_psn_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengisian_psn_id')->constrained('pengisian_psn')->cascadeOnDelete();
            $table->string('dari_status', 15)->nullable();
            $table->string('ke_status', 15);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Jejak audit per baris: siapa, kapan, nilai lama, nilai baru.
        // Menggantikan tabel *_log (salinan baris + kolom action) pada basis data lama.
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_label', 255)->nullable()->comment('username/email saat kejadian, termasuk riwayat impor');
            $table->string('tabel', 64);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->foreignId('psn_id')->nullable()->comment('untuk "Aktivitas Terbaru" & riwayat per proyek');
            $table->string('aksi', 20)->comment('CREATE|UPDATE|DELETE|RESTORE|SUBMIT|VERIFY|RETURN|PUBLISH');
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('sumber', 20)->default('APLIKASI')->comment('APLIKASI|IMPOR_LEGACY');
            $table->timestamp('created_at')->nullable()->useCurrent()->comment('null = riwayat sistem lama tanpa stempel waktu');
            $table->index(['tabel', 'record_id']);
            $table->index(['psn_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('login_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 255);
            $table->string('aktivitas', 20)->comment('LOGIN|LOGOUT|GAGAL');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['username', 'created_at']);
        });

        // ---- Snapshot per cut-off (dibekukan oleh `php artisan psn:snapshot`) ----

        Schema::create('snapshot_psn', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_cutoff_id')->constrained('periode_cutoff')->cascadeOnDelete();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            // Dimensi filter dibekukan agar angka historis tidak berubah bila profil diubah.
            $table->foreignId('klaster_id')->nullable();
            $table->foreignId('status_psn_id')->nullable();
            $table->string('tahap', 20)->nullable();
            $table->string('kategori', 10)->nullable()->comment('PSN|PKPN');
            $table->json('provinsi_kode')->nullable();
            $table->json('unit_kerja_id')->nullable();
            $table->json('sumber_dana_id')->nullable();
            $table->boolean('is_aktif')->default(true);
            // Nilai indikator.
            $table->decimal('nilai_investasi_rp', 24, 2)->nullable();
            $table->decimal('progres_rencana_persen', 7, 2)->nullable();
            $table->decimal('progres_realisasi_persen', 7, 2)->nullable();
            $table->decimal('deviasi_pp', 7, 2)->nullable();
            $table->string('status_progres', 15)->comment('ON_TRACK|BERISIKO|TERLAMBAT|TANPA_DATA');
            $table->decimal('pagu_rp', 24, 2)->nullable();
            $table->decimal('realisasi_anggaran_rp', 24, 2)->nullable();
            $table->unsignedInteger('jumlah_ro')->default(0);
            $table->unsignedInteger('jumlah_ro_tercapai')->default(0);
            $table->unsignedTinyInteger('risiko_skor_maks')->nullable();
            $table->string('risiko_level_maks', 20)->nullable();
            $table->boolean('is_kritis')->default(false)->comment('K4: Terlambat ATAU risiko residual >= Tinggi');
            $table->decimal('kelengkapan_persen', 5, 2)->nullable();
            $table->timestamp('pembaruan_terakhir_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['periode_cutoff_id', 'psn_id']);
            $table->index(['periode_cutoff_id', 'klaster_id']);
            $table->index(['periode_cutoff_id', 'status_progres']);
        });

        Schema::create('snapshot_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_cutoff_id')->constrained('periode_cutoff')->cascadeOnDelete();
            $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->boolean('is_critical_path')->default(false);
            $table->decimal('target_persen', 7, 2)->nullable();
            $table->decimal('realisasi_persen', 7, 2)->nullable();
            $table->decimal('deviasi_pp', 7, 2)->nullable();
            $table->string('status_progres', 15);
            $table->decimal('pagu_rp', 24, 2)->nullable();
            $table->decimal('realisasi_anggaran_rp', 24, 2)->nullable();
            $table->boolean('is_tercapai')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['periode_cutoff_id', 'kegiatan_id']);
            $table->index(['periode_cutoff_id', 'is_critical_path', 'deviasi_pp'], 'snapshot_kegiatan_cp_deviasi_index');
        });

        Schema::create('snapshot_risiko', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_cutoff_id')->constrained('periode_cutoff')->cascadeOnDelete();
            $table->foreignId('risiko_id')->constrained('risiko')->cascadeOnDelete();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->foreignId('kategori_id')->nullable();
            $table->unsignedTinyInteger('kemungkinan_harapan')->nullable();
            $table->unsignedTinyInteger('dampak_harapan')->nullable();
            $table->string('level_harapan', 20)->nullable();
            $table->unsignedTinyInteger('kemungkinan_aktual')->nullable();
            $table->unsignedTinyInteger('dampak_aktual')->nullable();
            $table->string('level_aktual', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['periode_cutoff_id', 'risiko_id']);
        });

        // Kelengkapan per PSN x bagian profil per cut-off (heatmap Kualitas Data).
        Schema::create('snapshot_kelengkapan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_cutoff_id')->constrained('periode_cutoff')->cascadeOnDelete();
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->string('bagian', 30);
            $table->unsignedSmallInteger('field_wajib');
            $table->unsignedSmallInteger('field_terisi');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['periode_cutoff_id', 'psn_id', 'bagian']);
        });
    }

    public function down(): void
    {
        foreach (['snapshot_kelengkapan', 'snapshot_risiko', 'snapshot_kegiatan', 'snapshot_psn', 'login_log',
            'audit_log', 'pengisian_psn_riwayat', 'pengisian_psn', 'periode_cutoff'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
