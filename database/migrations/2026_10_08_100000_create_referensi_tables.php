<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel referensi. Menggantikan tabel serba-guna `master` (tipe_tabel/kode_tabel)
 * pada basis data lama. Kode lama dipertahankan di kolom `kode` agar ETL dan
 * pelaporan lintas sistem tetap dapat ditelusuri.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Wilayah administrasi (master PROV, KABP, KCMT, KLRH). Kode Kemendagri
        // dipakai sebagai primary key karena stabil dan dipakai lintas sistem.
        Schema::create('ref_wilayah', function (Blueprint $table) {
            $table->string('kode', 13)->primary();
            $table->string('nama', 150);
            $table->unsignedTinyInteger('level')->comment('0=nasional, 1=provinsi, 2=kab/kota, 3=kecamatan, 4=kel/desa');
            $table->string('induk_kode', 13)->nullable()->index();
            $table->string('hc_key', 20)->nullable()->comment('kunci peta Highcharts dari tabel lama `peta` (provinsi)');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->index(['level', 'kode']);
        });

        // Klaster PSN (master KLST).
        Schema::create('ref_klaster', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 150);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_aktif')->default(true);
        });

        // Sub klaster PSN (master SKLT).
        Schema::create('ref_sub_klaster', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 150);
            $table->foreignId('klaster_id')->nullable()->constrained('ref_klaster')->nullOnDelete();
        });

        // Klaster PKPN (master KSPK).
        Schema::create('ref_klaster_pkpn', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 150);
        });

        // Program PSN (master PROG).
        Schema::create('ref_program', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 255);
        });

        // Status/tahapan PSN (master STAT) + pemetaan ke 4 tahap dashboard.
        Schema::create('ref_status_psn', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 150);
            $table->string('tahap', 20)->nullable()->comment('PERENCANAAN|TRANSAKSI|KONSTRUKSI|OPERASI -- pemetaan Tahapan Status dashboard');
            $table->boolean('is_aktif')->default(true)->comment('false = keluar dari PSN, tidak dihitung pada K1');
            $table->unsignedSmallInteger('urutan')->default(0);
        });

        // Sumber/indikasi pendanaan (master DANA) + pengelompokan skema dashboard (P6).
        Schema::create('ref_sumber_dana', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 150);
            $table->string('skema', 20)->nullable()->comment('APBN|APBD|KPBU|LAINNYA -- pengelompokan P6, lihat config psn_dashboard');
        });

        // Unit kerja (master UNIT): direktorat Bappenas, unit K/L, pemda, dsb.
        // Dipakai sebagai unit pengampu PSN dan cakupan akses pengguna.
        Schema::create('ref_unit_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 255);
            $table->string('jenis', 20)->default('LAINNYA')->comment('DIREKTORAT|KL|PEMDA|BU|LAINNYA (heuristik nama, perlu kurasi)');
            $table->foreignId('induk_id')->nullable()->constrained('ref_unit_kerja')->nullOnDelete();
            $table->boolean('is_aktif')->default(true);
            $table->index('jenis');
        });

        // Instansi/K/L (master INST, kode Bagian Anggaran).
        Schema::create('ref_instansi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 255);
        });

        // Pejabat penanggung jawab PSN (master PJWB), mis. "Menteri Pekerjaan Umum".
        Schema::create('ref_penanggung_jawab', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();
            $table->string('nama', 255);
            $table->foreignId('instansi_id')->nullable()->constrained('ref_instansi')->nullOnDelete();
        });

        // Kode-kode kecil lainnya: TYIT (bagian profil), KATD (kategori dokumen),
        // RISK (kategori risiko), FAST (fasilitas), KTAC (kategori Trisula),
        // SDGS, ASCI (Asta Cita), PRAN (peran kelembagaan), TACT (jenis kegiatan),
        // SKIF (ketersediaan informasi), KTGR (kategori RKP), PAUT (bahan pangan).
        Schema::create('ref_kode', function (Blueprint $table) {
            $table->id();
            $table->string('tipe', 10);
            $table->string('kode', 20);
            $table->string('nama', 500);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->unique(['tipe', 'kode']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('unit_kerja_id')->references('id')->on('ref_unit_kerja')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_kerja_id']);
        });

        foreach (['ref_kode', 'ref_penanggung_jawab', 'ref_instansi', 'ref_unit_kerja', 'ref_sumber_dana',
            'ref_status_psn', 'ref_program', 'ref_klaster_pkpn', 'ref_sub_klaster', 'ref_klaster', 'ref_wilayah'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
