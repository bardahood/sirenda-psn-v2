<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kertas kerja pengendalian (sheet "Format Ringkas") lengkap di basis data (aditif, dinormalisasi).
 * Kolom tunggal per PSN masuk ke evaluasi_psn; kelompok berulang dipecah ke tabel anak memanjang:
 *  - evaluasi_psn_capaian   : B2 per komponen (progres fisik, output utama, pembiayaan, perizinan, lahan, regulasi, sarana)
 *  - evaluasi_psn_milestone : B3.a/B3.b per jenis milestone
 *  - evaluasi_psn_isu       : C2 per isu (tipologi, jalur kritis, upaya, dukungan)
 *  - evaluasi_psn_lampiran  : daftar lampiran
 * Nilai kosong pada capaian berarti memakai data profil (snapshot cut-off). Lihat docs/evaluasi-status-psn.md §6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluasi_psn', function (Blueprint $table) {
            $table->text('b4_manfaat')->nullable()->after('tanpa_isu')->comment('B4 manfaat yang sudah terwujud s.d. akhir triwulan');
            $table->json('c1_tipologi')->nullable()->after('b4_manfaat')->comment('C1 kode tipologi isu 1-10 (config evaluasi_status.tipologi_isu)');
            $table->string('c1_lainnya', 255)->nullable()->after('c1_tipologi')->comment('C1 tipologi 11 Lainnya; "Tidak ada isu" bila tanpa isu');
            $table->text('info_tambahan')->nullable()->after('pic');
        });

        Schema::create('evaluasi_psn_capaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_psn_id')->constrained('evaluasi_psn')->cascadeOnDelete();
            $table->string('komponen', 20)->comment('PROGRES_FISIK|OUTPUT_UTAMA|PEMBIAYAAN|PERIZINAN|PENGADAAN_LAHAN|REGULASI|SARANA');
            $table->string('satuan', 100)->nullable();
            $table->decimal('target_akhir', 24, 4)->nullable()->comment('pembiayaan dalam Rp miliar, progres dalam %');
            $table->decimal('target_kumulatif', 24, 4)->nullable()->comment('s.d. akhir triwulan cut-off');
            $table->decimal('realisasi_kumulatif', 24, 4)->nullable();
            $table->text('keterangan')->nullable()->comment('keterangan / sumber data');
            $table->jejak(false);
            $table->unique(['evaluasi_psn_id', 'komponen']);
        });

        Schema::create('evaluasi_psn_milestone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_psn_id')->constrained('evaluasi_psn')->cascadeOnDelete();
            $table->string('kode', 30)->comment('kode milestone (config evaluasi_status.milestone_fisik / milestone_non_fisik)');
            $table->string('nama_lain', 255)->nullable()->comment('nama milestone untuk kode LAIN');
            $table->string('status', 10)->nullable()->comment('SELESAI|PROSES|BELUM|NA');
            $table->date('tanggal')->nullable()->comment('tanggal selesai / target');
            $table->text('keterangan')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->jejak(false);
            $table->index(['evaluasi_psn_id', 'urutan']);
        });

        Schema::create('evaluasi_psn_isu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_psn_id')->constrained('evaluasi_psn')->cascadeOnDelete();
            $table->unsignedBigInteger('isu_id')->nullable()->comment('isu profil asal (tanpa FK: isu dibangun ulang saat sinkron)');
            $table->text('uraian');
            $table->string('tipologi', 4)->nullable()->comment('kode C1 1-11');
            $table->boolean('jalur_kritis')->nullable()->comment('menghambat jalur kritis / capaian sasaran');
            $table->text('upaya')->nullable();
            $table->text('dukungan')->nullable()->comment('dukungan yang dibutuhkan (apa dan dari siapa)');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->jejak(false);
            $table->index(['evaluasi_psn_id', 'urutan']);
        });

        Schema::create('evaluasi_psn_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_psn_id')->constrained('evaluasi_psn')->cascadeOnDelete();
            $table->string('jenis', 10)->nullable()->comment('DOKUMEN|FOTO|VIDEO|LAINNYA');
            $table->string('judul', 255);
            $table->string('bentuk', 10)->nullable()->comment('TAUTAN|FILE');
            $table->string('tautan', 1000)->nullable()->comment('tautan (link) / nama file');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->jejak(false);
            $table->index(['evaluasi_psn_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluasi_psn_lampiran');
        Schema::dropIfExists('evaluasi_psn_isu');
        Schema::dropIfExists('evaluasi_psn_milestone');
        Schema::dropIfExists('evaluasi_psn_capaian');
        Schema::table('evaluasi_psn', fn (Blueprint $t) => $t->dropColumn(['b4_manfaat', 'c1_tipologi', 'c1_lainnya', 'info_tambahan']));
    }
};
