<?php

use Database\Seeders\PeranSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Evaluasi keberlanjutan status PSN per tahun RKP (aditif). Lihat docs/evaluasi-status-psn.md.
 *  - evaluasi_psn: satu baris per PSN per tahun RKP; isian D1-D3 Form Pengendalian, hasil pengecekan,
 *    tindak lanjut, catatan analisis, dan indikasi status. Data A-C dibaca langsung dari profil PSN.
 *  - evaluasi_psn_riwayat: setiap perubahan Status Akhir (dasar grafik pergerakan status harian).
 * Izin evaluasi.* diberikan ke peran sesuai PeranSeeder::MATRIKS tanpa mengubah izin lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluasi_psn', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_rkp')->comment('RKP yang diputuskan, mis. 2027');
            $table->foreignId('psn_id')->constrained('psn')->comment('ID PSN stabil antar-impor; tanpa cascade agar evaluasi tidak ikut terhapus saat impor ulang');
            $table->date('form_diterima_at')->nullable()->comment('tanggal Form Pengendalian diterima dari PJ');

            $table->unsignedSmallInteger('a6_tahun_penetapan')->nullable()->comment('A6 tahun pertama kali ditetapkan sebagai PSN');
            $table->string('jenis_psn', 10)->nullable()->comment('A12 FISIK|NON_FISIK');
            $table->boolean('tanpa_isu')->default(false)->comment('C1 Lainnya: "Tidak ada isu"');

            $table->string('d1_kebutuhan', 10)->nullable()->comment('D1 MASIH|TIDAK');
            $table->string('d1_alasan', 20)->nullable()->comment('SELESAI|BATAL|TANPA_FASILITAS|LAINNYA');
            $table->text('d1_alasan_lain')->nullable();
            $table->json('d2_fasilitas')->nullable()->comment('kode fasilitas PP 42/2021 (config evaluasi_status.d2_fasilitas)');
            $table->text('d2_justifikasi')->nullable();
            $table->string('d3_tahapan', 30)->nullable()->comment('tahapan target akhir tahun RKP');
            $table->text('d3_target')->nullable();
            $table->unsignedSmallInteger('d3_tahun_selesai')->nullable()->comment('proyeksi tahun selesai berdasarkan capaian aktual');
            $table->json('pic')->nullable()->comment('nama, jabatan, unit, kontak, tanggal');

            $table->string('hasil_pengecekan', 20)->nullable()->comment('LENGKAP_BENAR|LENGKAP_ADA_SALAH|BELUM_LENGKAP|PROSES');
            $table->string('tindak_lanjut', 20)->nullable()->comment('SIAP_DIANALISIS|VERIFIKASI_PJ|BUTUH_DATA');
            $table->text('catatan_analisis')->nullable();
            $table->string('indikasi_status', 20)->nullable()->comment('DIPERTAHANKAN|DIKELUARKAN');
            $table->string('status_akhir', 20)->default('PROSES_PENGECEKAN')->comment('turunan: indikasi > tindak lanjut > PROSES_PENGECEKAN');
            $table->jejak();

            $table->unique(['tahun_rkp', 'psn_id']);
            $table->index(['tahun_rkp', 'status_akhir']);
        });

        Schema::create('evaluasi_psn_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluasi_psn_id')->constrained('evaluasi_psn')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status_akhir', 20);
            $table->string('keterangan', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->index(['evaluasi_psn_id', 'tanggal']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (PeranSeeder::TINGKAT as $aksi) {
            Permission::findOrCreate("evaluasi.{$aksi}");
        }
        foreach (PeranSeeder::MATRIKS as $peran => $hak) {
            $tingkat = $hak['evaluasi'] ?? $hak['*'] ?? null;
            if ($tingkat && ($role = Role::where('name', $peran)->first())) {
                $role->givePermissionTo(array_map(fn ($a) => "evaluasi.{$a}", array_slice(PeranSeeder::TINGKAT, 0, array_search($tingkat, PeranSeeder::TINGKAT) + 1)));
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluasi_psn_riwayat');
        Schema::dropIfExists('evaluasi_psn');
        Permission::where('name', 'like', 'evaluasi.%')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
