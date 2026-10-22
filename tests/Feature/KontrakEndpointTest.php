<?php

namespace Tests\Feature;

use App\Models\Psn;
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
        $this->assertGreaterThanOrEqual(20, count($urls), 'Daftar endpoint tidak lengkap: '.implode(', ', $urls));

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
            $api('/risiko/ringkasan') => [200, 200, 200, 200, 200],
            '/kamus-indikator' => [200, 200, 200, 200, 200],
            '/pengisian' => [200, 403, 403, 200, 200],
            '/laporan' => [200, 200, 200, 200, 403],
            '/pengaturan/pengguna' => [200, 403, 403, 403, 403],
            '/pengaturan/cutoff' => [200, 403, 403, 403, 403],
            '/pengaturan/master' => [200, 403, 403, 403, 403],
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
        $this->assertSame(0, $this->getJson('/api/v1/risiko/register')->json('meta.total'));
        $this->assertSame(['Milik Dit 07'], collect($this->getJson('/api/v1/kualitas-data')->json('data.field_kosong'))->pluck('nama')->all());
    }

    public function test_filter_psn_tertentu_dan_opsinya_mengikuti_cakupan(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $k1 = fn (string $qs) => (int) collect($this->getJson("/api/v1/dashboard/kpi?{$qs}")->assertOk()->json('data'))->firstWhere('kode', 'K1')['nilai'];
        $this->assertSame(2, $k1(''));
        $this->assertSame(1, $k1("psn={$this->id['psn19']}"));
        $this->assertSame(2, $k1("psn={$this->id['psn07']},{$this->id['psn19']}"));
        $this->assertSame(['Milik Dit 19'], collect($this->getJson("/api/v1/proyek?psn={$this->id['psn19']}")->json('data'))->pluck('nama')->all());
        $this->assertSame("{$this->id['psn19']}", $this->getJson("/api/v1/dashboard/kpi?psn={$this->id['psn19']}")->json('meta.filter.psn'));
        $this->getJson('/api/v1/dashboard/kpi?psn=abc')->assertUnprocessable();
        $this->assertEqualsCanonicalizing([$this->id['psn07'], $this->id['psn19']], collect($this->getJson('/api/v1/filter-opsi')->json('data.psn'))->pluck('nilai')->all());

        // Operator: opsi PSN hanya unitnya; memilih PSN unit lain tidak membuka datanya.
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->assertSame([$this->id['psn07']], collect($this->getJson('/api/v1/filter-opsi')->json('data.psn'))->pluck('nilai')->all());
        $this->assertSame(0, $k1("psn={$this->id['psn19']}"));
    }

    public function test_filter_kementerian_dan_tahun_selesai(): void
    {
        $unit = fn (string $kode, string $nama, string $jenis = 'KL') => DB::table('ref_unit_kerja')->insertGetId(['kode' => $kode, 'nama' => $nama, 'jenis' => $jenis]);
        $unit('UJ1', 'Menteri Perhubungan');
        $djka = $unit('UJ2', 'Direktorat Jenderal Perkeretaapian, Kementerian Perhubungan');
        $bbws = $unit('UJ3', 'BBWS Brantas');
        $dishub = $unit('UJ4', 'Dinas Perhubungan, DKI', 'LAINNYA');
        $pengampu = fn (Psn $p, int $u) => DB::table('psn_unit_pengampu')->insert(['psn_id' => $p->id, 'unit_kerja_id' => $u]);
        $pengampu($this->buatPsn(['nama' => 'Kereta Uji', 'tahun_selesai' => 2029], ['07']), $djka);
        $pengampu($this->buatPsn(['nama' => 'Bendungan Uji', 'tahun_selesai' => 2030], []), $bbws);
        $pengampu($this->buatPsn(['nama' => 'LRT Pemda Uji', 'tahun_selesai' => 2029], []), $dishub);
        app(SnapshotService::class)->buat('2026-10', terbit: true);

        $this->actingAs($this->buatPengguna('Pimpinan'));
        $k1 = fn (string $qs) => (int) collect($this->getJson("/api/v1/dashboard/kpi?periode=2026-10&{$qs}")->assertOk()->json('data'))->firstWhere('kode', 'K1')['nilai'];
        $this->assertSame(5, $k1(''));
        // Ditjen di bawah Kementerian Perhubungan ikut; dinas perhubungan pemda (bukan K/L) tidak.
        $this->assertSame(1, $k1('kl=kementerian-perhubungan'));
        $this->assertSame(1, $k1('kl=kementerian-pekerjaan-umum'));
        $this->assertSame(2, $k1('kl=kementerian-perhubungan,kementerian-pekerjaan-umum'));
        $this->assertSame(2, $k1('thn=2029'));
        $this->assertSame(3, $k1('thn=2029,2030'));
        $this->assertSame(0, $k1('kl=kementerian-perhubungan&thn=2030'));
        $this->assertSame(0, $k1('kl=tidak-dikenal'));
        $this->assertSame(['Kereta Uji'], collect($this->getJson('/api/v1/proyek?periode=2026-10&kl=kementerian-perhubungan')->json('data'))->pluck('nama')->all());
        $this->assertSame(['Bendungan Uji'], collect($this->getJson('/api/v1/proyek?periode=2026-10&thn=2030')->json('data'))->pluck('nama')->all());
        $this->assertSame('kementerian-perhubungan', $this->getJson('/api/v1/dashboard/kpi?kl=Kementerian-Perhubungan')->json('meta.filter.kl'));
        $this->getJson('/api/v1/dashboard/kpi?kl=bukan_slug!')->assertUnprocessable();
        $this->getJson('/api/v1/dashboard/kpi?thn=abc')->assertUnprocessable();
        $this->getJson('/api/v1/dashboard/kpi?thn=1800')->assertUnprocessable();

        // Opsi: hanya K/L yang mengampu PSN, satu entri per induk; tahun dari data PSN.
        $opsi = $this->getJson('/api/v1/filter-opsi')->json('data');
        $this->assertSame(['Kementerian Pekerjaan Umum', 'Kementerian Perhubungan'], collect($opsi['kementerian'])->pluck('label')->all());
        $this->assertSame(['2029', '2030'], collect($opsi['tahun'])->pluck('nilai')->all());

        // Operator: filter K/L tidak membuka PSN di luar unitnya.
        $this->actingAs($this->buatPengguna('Operator K/L', '19'));
        $this->assertSame(0, $k1('kl=kementerian-perhubungan'));
    }

    public function test_pengguna_tanpa_peran_ditolak_di_semua_halaman(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/dashboard', '/proyek', "/proyek/{$this->id['psn07']}", '/perencanaan', '/risiko', '/peta', '/kualitas-data', '/laporan/ringkasan.pdf', '/api/v1/dashboard/kpi', '/api/v1/proyek'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }
}
