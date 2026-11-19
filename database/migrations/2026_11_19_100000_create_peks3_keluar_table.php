<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrean kiriman balik Dashboard -> SIRENDA PEKS3 (docs/integrasi-peks3.md §9). Satu baris = satu perubahan
 * isian menu Pengisian. Tabel milik dashboard: tidak dikosongkan saat impor ulang dari cermin, dan isinya
 * dipakai untuk memulihkan isian yang belum kembali dari PEKS3 setelah penerapan sinkron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peks3_keluar', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30)->comment('RO_BULAN|RISIKO_PANTAU|RISIKO_BARU|ISU_UBAH|ISU_BARU');
            $table->foreignId('psn_id')->constrained('psn')->cascadeOnDelete();
            $table->string('kunci', 191)->comment('sasaran perubahan, untuk menggabungkan isian berurutan yang belum terkirim');
            $table->json('kirim')->comment('operasi untuk PEKS3: aksi, tabel, baris_id, dasar, nilai');
            $table->json('lokal')->comment('pemulihan isian dashboard setelah impor ulang');
            $table->foreignId('bergantung_id')->nullable()->comment('operasi tambah yang harus diterima PEKS3 lebih dulu');
            $table->unsignedBigInteger('v2_id')->nullable()->comment('baris dashboard yang dibuat operasi tambah (diperbarui saat dipulihkan)');
            $table->string('status', 15)->default('ANTRE')->comment('ANTRE|DITERIMA|SELESAI|KONFLIK|DITOLAK|GAGAL|DIABAIKAN');
            $table->unsignedSmallInteger('percobaan')->default(0);
            $table->timestamp('berikutnya_at')->nullable();
            $table->text('pesan')->nullable();
            $table->unsignedBigInteger('peks3_id')->nullable()->comment('id baris di PEKS3 (hasil tambah atau sasaran ubah)');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dikirim_at')->nullable();
            $table->timestamp('diterima_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'berikutnya_at']);
            $table->index(['kunci', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peks3_keluar');
    }
};
