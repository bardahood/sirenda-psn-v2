<?php

namespace Tests\Feature;

use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Penjaga N+1: jumlah query per permintaan tidak boleh bertambah seiring jumlah data.
 */
class KinerjaQueryTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
    }

    /** Jumlah query satu permintaan tanpa cache; pemanasan dulu agar memo statis & cache izin tidak ikut terhitung. */
    protected function hitungQuery(string $url): int
    {
        $this->get($url)->assertOk();
        Cache::flush();
        app(PermissionRegistrar::class)->getPermissions(); // cache izin dimuat ulang di luar hitungan
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    protected function tambahPsn(int $jumlah, int $kegiatanPerPsn = 2): array
    {
        $ids = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $p = $this->buatPsn(['nama' => "PSN {$i}", 'nilai_investasi_rp' => 1e12], ['07']);
            DB::table('psn_lokasi')->insert([['psn_id' => $p->id, 'provinsi_kode' => '31'], ['psn_id' => $p->id, 'provinsi_kode' => '32']]);
            DB::table('psn_sumber_dana')->insert(['psn_id' => $p->id, 'sumber_dana_id' => $this->ref('ref_sumber_dana', 'A')]);
            for ($k = 0; $k < $kegiatanPerPsn; $k++) {
                $this->buatKegiatan($p, [['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 50, 'realisasi_persen' => 40, 'pagu_rp' => 1e9, 'dilaporkan_at' => '2026-09-20']], ['is_critical_path' => true]);
            }
            DB::table('isu')->insert(['psn_id' => $p->id, 'uraian' => 'Isu', 'status' => 'TERBUKA']);
            $ids[] = $p->id;
        }

        return $ids;
    }

    public function test_jumlah_query_endpoint_tidak_bergantung_jumlah_psn(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $endpoint = ['/api/v1/dashboard/kpi', '/api/v1/dashboard/distribusi?dim=provinsi', '/api/v1/dashboard/progres', '/api/v1/dashboard/ro-kritis',
            '/api/v1/proyek', '/api/v1/kualitas-data', '/api/v1/peta'];

        $this->tambahPsn(3);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
        $sedikit = array_map(fn ($u) => $this->hitungQuery($u), $endpoint);

        $this->tambahPsn(25);
        app(SnapshotService::class)->buat('2026-09', terbit: true, paksa: true);
        $banyak = array_map(fn ($u) => $this->hitungQuery($u), $endpoint);

        $this->assertSame(array_combine($endpoint, $sedikit), array_combine($endpoint, $banyak));
    }

    public function test_jumlah_query_detail_proyek_tidak_bergantung_jumlah_kegiatan(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        [$kecil] = $this->tambahPsn(1, 2);
        [$besar] = $this->tambahPsn(1, 30);
        app(SnapshotService::class)->buat('2026-09', terbit: true);

        foreach (['profil', 'kpro', 'progres', 'dokumen'] as $tab) {
            $this->assertSame($this->hitungQuery("/proyek/{$kecil}?tab={$tab}"), $this->hitungQuery("/proyek/{$besar}?tab={$tab}"), "tab {$tab}");
        }
        $this->assertSame($this->hitungQuery("/api/v1/proyek/{$kecil}"), $this->hitungQuery("/api/v1/proyek/{$besar}"));
    }
}
