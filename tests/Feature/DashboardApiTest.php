<?php

namespace Tests\Feature;

use App\Models\Psn;
use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Uji endpoint Ringkasan Eksekutif terhadap angka yang dihitung manual dari data uji.
 *
 * Data: 3 PSN aktif + 1 PSN keluar (status 6).
 *  A  klaster R.6, prov 31+32, unit 07, dana APBN, Rp 200 T, progres 60/30 (Terlambat)
 *  B  klaster R.6, prov 32,    unit 19, dana KPBU, Rp 100 T, progres 50/52 (On Track)
 *  C  klaster R.4, prov 35,    unit 19, dana APBN+KPBU, Rp 0, tanpa progres, risiko Tinggi, PKPN
 *  D  keluar PSN (tidak dihitung)
 */
class DashboardApiTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected array $psn = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);

        $a = $this->buatPsn(['nama' => 'A', 'nilai_investasi_rp' => 200e12], ['07']);
        $b = $this->buatPsn(['nama' => 'B', 'nilai_investasi_rp' => 100e12], ['19']);
        $c = $this->buatPsn(['nama' => 'C', 'klaster_id' => $this->ref('ref_klaster', 'R.4'), 'klaster_pkpn_id' => $this->ref('ref_klaster_pkpn', 'A')], ['19']);
        $d = $this->buatPsn(['nama' => 'D', 'status_psn_id' => $this->ref('ref_status_psn', '6'), 'nilai_investasi_rp' => 50e12], ['07']);
        $this->psn = compact('a', 'b', 'c', 'd');

        foreach ([[$a, ['31', '32'], ['A']], [$b, ['32'], ['D']], [$c, ['35'], ['A', 'D']], [$d, ['31'], ['A']]] as [$p, $prov, $dana]) {
            foreach ($prov as $k) {
                DB::table('psn_lokasi')->insert(['psn_id' => $p->id, 'provinsi_kode' => $k]);
            }
            foreach ($dana as $k) {
                DB::table('psn_sumber_dana')->insert(['psn_id' => $p->id, 'sumber_dana_id' => $this->ref('ref_sumber_dana', $k)]);
            }
        }

        $this->buatKegiatan($a, [['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 60, 'realisasi_persen' => 30, 'pagu_rp' => 100e9, 'realisasi_anggaran_rp' => 40e9, 'dilaporkan_at' => '2026-09-20']], ['is_critical_path' => true, 'nama' => 'RO kritis A']);
        $this->buatKegiatan($b, [['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 50, 'realisasi_persen' => 52, 'pagu_rp' => 100e9, 'realisasi_anggaran_rp' => 60e9, 'dilaporkan_at' => '2026-09-20']], ['is_critical_path' => true]);
        DB::table('risiko')->insert(['psn_id' => $c->id, 'uraian' => 'R', 'level_harapan' => 'Tinggi']);

        // Cut-off Agustus: hanya A & B (C dibuat sesudahnya disimulasikan dengan menghapus snapshot C).
        app(SnapshotService::class)->buat('2026-08', terbit: true);
        DB::table('snapshot_psn')->where('psn_id', $c->id)->delete();
        app(SnapshotService::class)->buat('2026-09', terbit: true);

        $this->actingAs($this->buatPengguna('Pimpinan'));
    }

    public function test_tamu_tidak_dapat_mengakses(): void
    {
        auth()->logout();
        $this->getJson('/api/v1/dashboard/kpi')->assertUnauthorized();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_kpi_dan_delta_terhadap_cutoff_sebelumnya(): void
    {
        $r = $this->getJson('/api/v1/dashboard/kpi')->assertOk()
            ->assertJsonPath('meta.cutoff.kode', '2026-09')
            ->assertJsonPath('meta.sebelumnya', '2026-08');

        $k = collect($r->json('data'))->keyBy('kode');
        $this->assertEquals(3, $k['K1']['nilai']);                      // D keluar PSN tidak dihitung
        $this->assertEquals(2, $k['K1']['sebelumnya']);
        $this->assertEquals(50.0, $k['K1']['delta']['nilai']);          // (3-2)/2
        $this->assertTrue($k['K1']['delta']['baik']);
        $this->assertEquals(300.0, $k['K2']['nilai']);                  // Rp triliun
        // K3 tertimbang investasi: (30x200 + 52x100)/300 = 37,33
        $this->assertEquals(37.3, $k['K3']['nilai']);
        $this->assertSame(2, $k['K3']['cakupan_psn']);
        $this->assertEquals(0.0, $k['K3']['delta']['nilai']);
        $this->assertSame('pp', $k['K3']['delta']['satuan']);
        // K4: A terlambat + C risiko Tinggi = 2. Agustus: A belum punya laporan (Tanpa data),
        // C belum ada -> 0; delta dari pembanding 0 tidak tersedia.
        $this->assertEquals(2, $k['K4']['nilai']);
        $this->assertEquals(0, $k['K4']['sebelumnya']);
        $this->assertNull($k['K4']['delta']['nilai']);
    }

    public function test_filter_global_diterapkan_dan_dikembalikan_di_meta(): void
    {
        $r = $this->getJson('/api/v1/dashboard/kpi?prov=32&dana=kpbu')->assertOk()->assertJsonPath('meta.filter', ['prov' => '32', 'dana' => 'kpbu']);
        $this->assertEquals(1, collect($r->json('data'))->firstWhere('kode', 'K1')['nilai']); // hanya B

        $r = $this->getJson('/api/v1/dashboard/kpi?kat=pkpn')->assertOk();
        $this->assertEquals(1, collect($r->json('data'))->firstWhere('kode', 'K1')['nilai']); // C

        $r = $this->getJson('/api/v1/dashboard/kpi?status=terlambat,on_track')->assertOk();
        $this->assertEquals(2, collect($r->json('data'))->firstWhere('kode', 'K1')['nilai']);

        $dit = $this->ref('ref_unit_kerja', '19');
        $r = $this->getJson("/api/v1/dashboard/kpi?dit={$dit}")->assertOk();
        $this->assertEquals(2, collect($r->json('data'))->firstWhere('kode', 'K1')['nilai']); // B, C
    }

    public function test_filter_tidak_valid_ditolak(): void
    {
        $this->getJson('/api/v1/dashboard/kpi?periode=2026-13')->assertUnprocessable()->assertJsonValidationErrors('periode');
        $this->getJson('/api/v1/dashboard/kpi?status=hilang')->assertUnprocessable();
        $this->getJson('/api/v1/dashboard/kpi?klaster=abc')->assertUnprocessable();
        $this->getJson('/api/v1/dashboard/distribusi?dim=apa')->assertUnprocessable();
        $this->getJson('/api/v1/dashboard/kpi?periode=2025-01')->assertNotFound();
    }

    public function test_distribusi_klaster_provinsi_dana_direktorat(): void
    {
        $kl = collect($this->getJson('/api/v1/dashboard/distribusi?dim=klaster')->json('data'))->pluck('jumlah', 'label');
        $this->assertSame(2, $kl['Konektivitas dan Infrastruktur Logistik Jalan']);
        $this->assertSame(1, $kl['Swasembada Air']);

        // Multi-lokasi dihitung di tiap provinsi: 31 (A), 32 (A,B), 35 (C)
        $prov = collect($this->getJson('/api/v1/dashboard/distribusi?dim=provinsi')->json('data'))->pluck('jumlah', 'kode');
        $this->assertEquals(['32' => 2, '31' => 1, '35' => 1], $prov->all());

        // APBN: A(200)+C(0); KPBU: B(100)+C(0)
        $dana = collect($this->getJson('/api/v1/dashboard/distribusi?dim=dana')->json('data'))->keyBy('kode');
        $this->assertEquals(200.0, $dana['apbn']['investasi_triliun']);
        $this->assertSame(2, $dana['apbn']['jumlah']);
        $this->assertEquals(100.0, $dana['kpbu']['investasi_triliun']);
        $this->assertSame(0, $dana['apbd']['jumlah']);

        $dir = collect($this->getJson('/api/v1/dashboard/distribusi?dim=direktorat')->json('data'))->pluck('jumlah', 'label');
        $this->assertSame(2, $dir['Direktorat Sumber Daya Air']);
    }

    public function test_progres_anggaran_ro_dan_tren(): void
    {
        $p = $this->getJson('/api/v1/dashboard/progres')->assertOk()->json('data');
        // rencana tertimbang (60x200+50x100)/300 = 56,67
        $this->assertEquals(56.7, $p['P2']['rencana_persen']);
        $this->assertEquals(37.3, $p['P2']['realisasi_persen']);
        $this->assertSame('BERISIKO', $p['P2']['status']);           // -19,3 pp
        $this->assertEquals(50.0, $p['P3']['persen']);                // (40+60)/(100+100)
        $this->assertSame(1, $p['P4']['tercapai']);
        $this->assertSame(2, $p['P4']['total']);

        $t = $this->getJson('/api/v1/dashboard/tren')->json('data');
        $this->assertSame(2026, $t['tahun']);
        $this->assertCount(12, $t['bulan']);
        $this->assertSame('2026-08', $t['bulan'][7]['cutoff']);
        $this->assertNull($t['bulan'][0]['realisasi_persen']);
    }

    public function test_ro_kritis_tahapan_status_data_timeline(): void
    {
        $ro = $this->getJson('/api/v1/dashboard/ro-kritis')->json('data');
        $this->assertCount(1, $ro, 'hanya RO berstatus Berisiko/Terlambat');
        $this->assertSame('RO kritis A', $ro[0]['kegiatan']);
        $this->assertEquals(-30.0, $ro[0]['deviasi_pp']);

        $tahap = collect($this->getJson('/api/v1/dashboard/tahapan')->json('data'))->pluck('jumlah', 'kode');
        $this->assertSame(3, $tahap['KONSTRUKSI']);

        $s = $this->getJson('/api/v1/dashboard/status-data')->json('data');
        $this->assertSame('2026-09-30', $s['tanggal_cutoff']);
        $this->assertSame(3, $s['belum_terverifikasi']);

        $this->getJson('/api/v1/dashboard/timeline-dp')->assertOk()->assertJsonPath('data.0.label', '2026');
        $this->getJson('/api/v1/dashboard/trisula')->assertOk()->assertJsonPath('data.metodologi_ditetapkan', false);
        $this->getJson('/api/v1/dashboard/aktivitas')->assertOk()->assertJsonStructure(['data', 'meta']);
    }

    public function test_operator_kl_hanya_melihat_angka_unitnya(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '19'));

        $k1 = collect($this->getJson('/api/v1/dashboard/kpi')->json('data'))->firstWhere('kode', 'K1');
        $this->assertEquals(2, $k1['nilai']); // B, C -- cache tidak bocor dari pengguna lain

        $ro = $this->getJson('/api/v1/dashboard/ro-kritis')->json('data');
        $this->assertSame([], $ro);
    }

    public function test_cache_dibatalkan_saat_snapshot_diterbitkan(): void
    {
        $this->getJson('/api/v1/dashboard/kpi')->assertOk();
        Psn::withoutGlobalScopes()->whereKey($this->psn['b']->id)->update(['nilai_investasi_rp' => 400e12]);
        app(SnapshotService::class)->buat('2026-09', terbit: true, paksa: true);

        $k2 = collect($this->getJson('/api/v1/dashboard/kpi')->json('data'))->firstWhere('kode', 'K2');
        $this->assertEquals(600.0, $k2['nilai']);
    }

    public function test_tanpa_cutoff_terbit_mengembalikan_pesan(): void
    {
        DB::table('periode_cutoff')->update(['status' => 'DRAFT']);
        $this->getJson('/api/v1/dashboard/kpi')->assertOk()->assertJsonPath('data', null)->assertJsonPath('meta.pesan', 'Belum ada cut-off yang diterbitkan.');
    }

    public function test_halaman_dashboard_dan_filter_opsi(): void
    {
        $this->get('/dashboard?klaster=1')->assertOk()->assertSee('Dashboard Monitoring &amp; Evaluasi PSN', false)->assertSee('Total Investasi')
            ->assertSee('Komposisi Sumber Pendanaan')->assertSee('Timeline PSN Klaster Direktif Presiden');
        $this->getJson('/api/v1/filter-opsi')->assertOk()->assertJsonCount(2, 'data.periode')->assertJsonCount(38, 'data.provinsi');
    }

    /** Data panel rancangan baru konsisten dengan K1/K2 dan antar-endpoint. */
    public function test_endpoint_panel_dashboard_konsisten(): void
    {
        $kpi = collect($this->getJson('/api/v1/dashboard/kpi')->json('data'))->keyBy('kode');

        $tren = $this->getJson('/api/v1/dashboard/kpi-tren')->assertOk()->json('data');
        $terakhir = end($tren);
        foreach (['K1', 'K2', 'K3', 'K4'] as $k) {
            $this->assertEquals($kpi[$k]['nilai'], $terakhir[$k], "kpi-tren {$k}");
        }

        // Komposisi dana: kelompok saling lepas -> jumlah PSN = K1, investasi = K2.
        $kom = collect($this->getJson('/api/v1/dashboard/distribusi?dim=komposisi_dana')->assertOk()->json('data'));
        $this->assertSame((int) $kpi['K1']['nilai'], $kom->sum('jumlah'));
        $this->assertEqualsWithDelta($kpi['K2']['nilai'], $kom->sum('investasi_triliun'), 0.2);

        // Pulau: PSN dihitung sekali per pulau; tidak melebihi K1 per pulau.
        $pulau = collect($this->getJson('/api/v1/dashboard/distribusi?dim=pulau')->assertOk()->json('data'));
        $this->assertSame(['Sumatera', 'Jawa', 'Bali & Nusa Tenggara', 'Kalimantan', 'Sulawesi', 'Maluku', 'Papua'], $pulau->pluck('label')->all());
        $this->assertTrue($pulau->every(fn ($p) => $p['jumlah'] <= $kpi['K1']['nilai']));

        $dp = $this->getJson('/api/v1/dashboard/dp-proyek')->assertOk()->json('data');
        $this->assertSame($dp['total'], $this->getJson('/api/v1/dashboard/progres')->json('data.DP.total'));
        $this->assertLessThanOrEqual(config('psn_dashboard.dashboard_top.dp_proyek'), count($dp['proyek']));
    }

    public function test_peran_tanpa_izin_ringkasan_ditolak(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan')->syncRoles([]));
        $this->getJson('/api/v1/dashboard/kpi')->assertForbidden();
    }
}
