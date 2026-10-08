<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UsulanPsn;
use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Kontrak seluruh endpoint GET /api/v1/* dan matriks hak akses halaman/endpoint per peran.
 */
class KontrakEndpointTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected array $id = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $a = $this->buatPsn(['nama' => 'Milik Dit 07'], ['07']);
        $b = $this->buatPsn(['nama' => 'Milik Dit 19'], ['19']);
        DB::table('psn_lokasi')->insert(['psn_id' => $a->id, 'provinsi_kode' => '31']);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
        $u07 = UsulanPsn::withoutGlobalScopes()->create(['tahun_rkp' => 2027, 'nama' => 'Usulan 07', 'jenis_pengusul' => 'KL', 'unit_kerja_id' => $this->ref('ref_unit_kerja', '07')]);
        $u19 = UsulanPsn::withoutGlobalScopes()->create(['tahun_rkp' => 2027, 'nama' => 'Usulan 19', 'jenis_pengusul' => 'KL', 'unit_kerja_id' => $this->ref('ref_unit_kerja', '19')]);
        $this->id = ['psn07' => $a->id, 'psn19' => $b->id, 'usulan07' => $u07->id, 'usulan19' => $u19->id];
    }

    /** URL contoh per rute GET API (parameter wajib diisi). */
    protected function urlApi(): array
    {
        $isi = ['psn' => $this->id['psn07'], 'usulan' => $this->id['usulan07']];
        $tambahan = ['api/v1/dashboard/distribusi' => '?dim=klaster'];

        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/') && in_array('GET', $r->methods(), true))
            ->map(fn ($r) => '/'.preg_replace_callback('/\{(\w+)\}/', fn ($m) => $isi[$m[1]], $r->uri()).($tambahan[$r->uri()] ?? ''))
            ->values()->all();
    }

    public function test_semua_endpoint_api_menolak_tamu_dan_mengembalikan_data_meta(): void
    {
        $urls = $this->urlApi();
        $this->assertGreaterThanOrEqual(17, count($urls), 'Daftar endpoint tidak lengkap: '.implode(', ', $urls));

        foreach ($urls as $url) {
            $this->getJson($url)->assertUnauthorized();
        }

        $this->actingAs($this->buatPengguna('Super Admin'));
        foreach ($urls as $url) {
            $this->getJson($url)->assertOk()->assertJsonStructure(['data', 'meta'], null, $url);
        }
    }

    /**
     * Matriks: peran => [url => status yang diharapkan].
     * L = lihat semua; L¹/I¹/V¹ = terbatas unit (Operator K/L unit 07; Direktorat unit 19).
     */
    public function test_matriks_hak_akses_per_peran(): void
    {
        $p07 = "/proyek/{$this->id['psn07']}";
        $p19 = "/proyek/{$this->id['psn19']}";
        $u07 = "/perencanaan/{$this->id['usulan07']}";
        $u19 = "/perencanaan/{$this->id['usulan19']}";
        $api = fn ($p) => "/api/v1{$p}";

        $matriks = [
            // url => [Super Admin, Pimpinan, PMO, Direktorat(19), Operator(07)]
            '/dashboard' => [200, 200, 200, 200, 200],
            '/proyek' => [200, 200, 200, 200, 200],
            $p07 => [200, 200, 200, 200, 200],
            $p19 => [200, 200, 200, 200, 404],          // Operator: L¹
            '/perencanaan' => [200, 200, 200, 200, 200],
            $u07 => [200, 200, 200, 200, 200],
            $u19 => [200, 200, 200, 200, 404],          // Operator: I¹
            '/perencanaan/baru' => [200, 403, 200, 200, 200],
            '/risiko' => [200, 200, 200, 200, 200],
            '/peta' => [200, 200, 200, 200, 200],
            '/kualitas-data' => [200, 200, 200, 200, 200],
            '/laporan/ringkasan.pdf' => [200, 200, 200, 200, 200],
            $api('/dashboard/kpi') => [200, 200, 200, 200, 200],
            $api("/proyek/{$this->id['psn19']}") => [200, 200, 200, 200, 404],
            $api("/usulan/{$this->id['usulan19']}/skor") => [200, 200, 200, 200, 404],
        ];
        $peran = [['Super Admin', null], ['Pimpinan', null], ['Tim Koordinasi/PMO', null], ['Direktorat Sektor', '19'], ['Operator K/L', '07']];

        foreach ($peran as $i => [$nama, $unit]) {
            $this->actingAs($this->buatPengguna($nama, $unit));
            foreach ($matriks as $url => $harapan) {
                $status = $this->get($url)->getStatusCode();
                $this->assertSame($harapan[$i], $status, "{$nama} {$url}");
            }
        }
    }

    public function test_angka_operator_terbatas_pada_unitnya_di_semua_endpoint_agregat(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->assertSame(1, (int) collect($this->getJson('/api/v1/dashboard/kpi')->json('data'))->firstWhere('kode', 'K1')['nilai']);
        $this->assertSame(1, $this->getJson('/api/v1/proyek')->json('meta.total'));
        $this->assertSame(['31' => 1], collect($this->getJson('/api/v1/peta')->json('data'))->where('jumlah', '>', 0)->pluck('jumlah', 'kode')->all());
        $this->assertSame(['Milik Dit 07'], collect($this->getJson('/api/v1/kualitas-data')->json('data.field_kosong'))->pluck('nama')->all());
    }

    public function test_pengguna_tanpa_peran_ditolak_di_semua_halaman(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/dashboard', '/proyek', "/proyek/{$this->id['psn07']}", '/perencanaan', '/risiko', '/peta', '/kualitas-data', '/laporan/ringkasan.pdf', '/api/v1/dashboard/kpi', '/api/v1/proyek'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
