<?php

namespace Tests\Feature;

use App\Support\Legacy\LegacyImporter;
use Database\Seeders\PeranSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji akurasi ETL terhadap basis data lama. Hanya berjalan bila dump lama
 * tersedia di koneksi `legacy` dan LEGACY_TEST=1.
 */
class LegacyImportAkurasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! env('LEGACY_TEST')) {
            $this->markTestSkipped('Set LEGACY_TEST=1 dan koneksi legacy untuk menjalankan uji akurasi ETL.');
        }
        $this->seed(PeranSeeder::class);
        app(LegacyImporter::class)->jalankan();
    }

    public function test_jumlah_baris_inti_identik_dengan_basis_data_lama(): void
    {
        $legacy = DB::connection('legacy');

        $this->assertSame($legacy->table('psn')->count(), DB::table('psn')->count());
        $this->assertSame($legacy->table('lokasi_psn')->count(), DB::table('psn_lokasi')->count());
        $this->assertSame($legacy->table('psn_item')->count(), DB::table('psn_profil_item')->count());
        $this->assertSame($legacy->table('psn_regulasi')->count(), DB::table('regulasi')->count());
        $this->assertSame(
            $legacy->table('psn_kegiatan')->count() + $legacy->table('psn_kegiatan_cp')->count(),
            DB::table('kegiatan')->count()
        );
        // Risiko tanpa psn_id pada data lama tidak dapat diimpor (dicatat di laporan ETL).
        $this->assertSame($legacy->table('psn_risiko')->whereNotNull('psn_id')->count(), DB::table('risiko')->count());
    }

    public function test_agregat_provinsi_dan_klaster_identik(): void
    {
        $legacy = DB::connection('legacy');

        $lama = $legacy->table('lokasi_psn')->groupBy('provinsi')->pluck(DB::raw('count(distinct psn_id)'), 'provinsi')->map(fn ($n) => (int) $n)->sortKeys()->all();
        $baru = DB::table('psn_lokasi')->groupBy('provinsi_kode')->pluck(DB::raw('count(distinct psn_id)'), 'provinsi_kode')->map(fn ($n) => (int) $n)->sortKeys()->all();
        $this->assertSame($lama, $baru);

        $lama = $legacy->table('psn')->where('klaster', '<>', '')->groupBy('klaster')->pluck(DB::raw('count(*)'), 'klaster')->map(fn ($n) => (int) $n)->sortKeys()->all();
        $baru = DB::table('v_psn_ringkas')->whereNotNull('klaster_kode')->groupBy('klaster_kode')->pluck(DB::raw('count(*)'), 'klaster_kode')->map(fn ($n) => (int) $n)->sortKeys()->all();
        $this->assertSame($lama, $baru);
    }

    public function test_nilai_investasi_tidak_berubah(): void
    {
        $this->assertEquals(
            (float) DB::connection('legacy')->table('psn')->sum('nilai_investasi'),
            (float) DB::table('psn')->sum('nilai_investasi_rp')
        );
    }
}
