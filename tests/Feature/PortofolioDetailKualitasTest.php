<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Fase 3: Portofolio, Detail Proyek, Kualitas Data.
 * Data sama dengan DashboardApiTest: A (Terlambat, unit 07), B (On Track, unit 19),
 * C (Tanpa data, risiko Tinggi, PKPN, unit 19), D (keluar PSN).
 */
class PortofolioDetailKualitasTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected array $psn = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);

        $a = $this->buatPsn(['nama' => 'Alfa Bendungan', 'kode_psn' => 'R.6-A', 'nilai_investasi_rp' => 200e12, 'deskripsi' => 'Ada'], ['07']);
        $b = $this->buatPsn(['nama' => 'Beta Jalan Tol', 'kode_psn' => 'R.6-B', 'nilai_investasi_rp' => 100e12], ['19']);
        $c = $this->buatPsn(['nama' => 'Charlie Pangan', 'klaster_id' => $this->ref('ref_klaster', 'R.4'), 'klaster_pkpn_id' => $this->ref('ref_klaster_pkpn', 'A')], ['19']);
        $d = $this->buatPsn(['nama' => 'Delta Keluar', 'status_psn_id' => $this->ref('ref_status_psn', '6')], ['07']);
        $this->psn = compact('a', 'b', 'c', 'd');

        foreach ([[$a, ['31', '32'], ['A']], [$b, ['32'], ['D']], [$c, ['35'], ['A', 'D']], [$d, ['31'], ['A']]] as [$p, $prov, $dana]) {
            foreach ($prov as $k) {
                DB::table('psn_lokasi')->insert(['psn_id' => $p->id, 'provinsi_kode' => $k]);
            }
            foreach ($dana as $k) {
                DB::table('psn_sumber_dana')->insert(['psn_id' => $p->id, 'sumber_dana_id' => $this->ref('ref_sumber_dana', $k)]);
            }
        }
        $ro = $this->buatKegiatan($a, [['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 60, 'realisasi_persen' => 30, 'pagu_rp' => 1e9, 'dilaporkan_at' => '2026-09-20', 'bukti_path' => 'https://contoh.go.id/bukti.pdf']], ['is_critical_path' => true, 'nama' => 'RO Alfa']);
        DB::table('kegiatan')->insert(['psn_id' => $a->id, 'parent_id' => $ro->id, 'nama' => 'CP turunan Alfa', 'is_critical_path' => true]);
        $this->buatKegiatan($b, [['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 50, 'realisasi_persen' => 52, 'pagu_rp' => 1e9, 'dilaporkan_at' => '2026-09-20']]);
        DB::table('risiko')->insert(['psn_id' => $c->id, 'uraian' => 'R', 'level_harapan' => 'Tinggi']);
        DB::table('isu')->insert(['psn_id' => $a->id, 'uraian' => 'Lahan belum bebas', 'pic_nama' => 'BPN', 'tenggat' => '2026-01-31', 'status' => 'TERBUKA']);
        DB::table('psn_profil_item')->insert(['psn_id' => $a->id, 'bagian_id' => DB::table('ref_kode')->where('tipe', 'TYIT')->where('kode', 'A')->value('id'), 'isi' => 'Urgensi Alfa']);

        app(SnapshotService::class)->buat('2026-08', terbit: true);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
        DB::table('pengisian_psn')->where('psn_id', $b->id)->where('periode_cutoff_id', DB::table('periode_cutoff')->where('kode', '2026-09')->value('id'))->update(['status' => 'DIAJUKAN', 'diajukan_at' => '2026-09-28 10:00:00']);

        $this->actingAs($this->buatPengguna('Pimpinan'));
    }

    // ------------------------------------------------------------ Portofolio

    public function test_jumlah_portofolio_identik_dengan_k1_untuk_setiap_filter(): void
    {
        $dit = $this->ref('ref_unit_kerja', '19');
        $kl = $this->ref('ref_klaster', 'R.6');
        foreach (['', 'prov=32', 'prov=31,35', 'dana=kpbu', 'dana=apbd', "dit={$dit}", "klaster={$kl}", 'kat=pkpn', 'status=terlambat,tanpa_data', "prov=32&dana=apbn&klaster={$kl}"] as $qs) {
            $k1 = collect($this->getJson("/api/v1/dashboard/kpi?{$qs}")->json('data'))->firstWhere('kode', 'K1')['nilai'];
            $this->assertEquals($k1, $this->getJson("/api/v1/proyek?{$qs}")->assertOk()->json('meta.total'), "filter: {$qs}");
        }
    }

    public function test_drilldown_k4_dan_opsi_tabel(): void
    {
        $k4 = collect($this->getJson('/api/v1/dashboard/kpi')->json('data'))->firstWhere('kode', 'K4')['nilai'];
        $this->assertEquals($k4, $this->getJson('/api/v1/proyek?kritis=1')->json('meta.total'));

        $this->assertSame(['Beta Jalan Tol'], collect($this->getJson('/api/v1/proyek?q=tol')->json('data'))->pluck('nama')->all());
        $this->assertSame(['Alfa Bendungan'], collect($this->getJson('/api/v1/proyek?q=R.6-A')->json('data'))->pluck('nama')->all());
        $this->assertSame(['Alfa Bendungan', 'Beta Jalan Tol', 'Charlie Pangan'], collect($this->getJson('/api/v1/proyek?urut=investasi&arah=desc')->json('data'))->pluck('nama')->all());
        // Deviasi naik: Alfa (-30), Beta (+2), Charlie tanpa data di akhir.
        $this->assertSame(['Alfa Bendungan', 'Beta Jalan Tol', 'Charlie Pangan'], collect($this->getJson('/api/v1/proyek?urut=deviasi')->json('data'))->pluck('nama')->all());
        $this->assertSame(4, $this->getJson('/api/v1/proyek?nonaktif=1')->json('meta.total'));
        $this->assertSame(['Charlie Pangan'], collect($this->getJson('/api/v1/proyek?tahap=KONSTRUKSI&kat=pkpn')->json('data'))->pluck('nama')->all());

        $baris = collect($this->getJson('/api/v1/proyek')->json('data'))->firstWhere('nama', 'Alfa Bendungan');
        $this->assertSame(['DKI JAKARTA', 'JAWA BARAT'], $baris['provinsi']);
        $this->assertSame('TERLAMBAT', $baris['status_progres']);
        $this->assertEquals(200, $baris['investasi_triliun']);
    }

    public function test_paginasi_dan_validasi(): void
    {
        $r = $this->getJson('/api/v1/proyek?per_halaman=10&page=1')->assertOk();
        $this->assertSame(1, $r->json('meta.halaman_terakhir'));
        $this->getJson('/api/v1/proyek?urut=sembarang')->assertUnprocessable();
        $this->getJson('/api/v1/proyek?per_halaman=1000')->assertUnprocessable();
        $this->getJson('/api/v1/proyek?tahap=X')->assertUnprocessable();
    }

    public function test_unduh_csv_berisi_semua_baris_terfilter(): void
    {
        $r = $this->get('/api/v1/proyek?format=csv&prov=32')->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $isi = $r->streamedContent();
        $this->assertSame(3, count(array_filter(explode("\n", trim($isi))))); // header + A + B
        $this->assertStringContainsString('Alfa Bendungan', $isi);
        $this->assertStringNotContainsString('Charlie', $isi);
    }

    public function test_operator_kl_hanya_melihat_proyek_unitnya(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->assertSame(['Alfa Bendungan'], collect($this->getJson('/api/v1/proyek')->json('data'))->pluck('nama')->all());
        $this->get("/proyek/{$this->psn['b']->id}")->assertNotFound();
        $this->getJson("/api/v1/proyek/{$this->psn['b']->id}")->assertNotFound();
        $this->get("/proyek/{$this->psn['a']->id}")->assertOk();
    }

    // ------------------------------------------------------------ Detail Proyek

    public function test_detail_tab_profil_kpro_progres_dokumen(): void
    {
        $id = $this->psn['a']->id;

        $this->get("/proyek/{$id}?klaster=1&q=alfa")->assertOk()
            ->assertSee('Alfa Bendungan')->assertSee('Urgensi Alfa')->assertSee('DKI JAKARTA')
            ->assertSee('Terlambat')
            // Kembali ke portofolio mempertahankan filter & pencarian.
            ->assertSee('/proyek?klaster=1&amp;q=alfa', false);

        $this->get("/proyek/{$id}?tab=kpro")->assertOk()->assertSee('RO Alfa')->assertSee('CP turunan Alfa')->assertSee('Critical path');

        $this->get("/proyek/{$id}?tab=progres")->assertOk()
            ->assertSee('Kurva S')->assertSee('Lahan belum bebas')->assertSee('lewat tenggat')
            ->assertSee('https://contoh.go.id/bukti.pdf', false);

        $this->get("/proyek/{$id}?tab=dokumen")->assertOk()->assertSee('Riwayat Perubahan');
        $this->get("/proyek/{$id}?tab=risiko")->assertOk()->assertSee('rilis v2');
        $this->get("/proyek/{$id}?tab=tidak-ada")->assertOk()->assertSee('Gambaran Umum');
        $this->get('/proyek/999999')->assertNotFound();
    }

    public function test_api_detail_proyek(): void
    {
        $r = $this->getJson("/api/v1/proyek/{$this->psn['a']->id}")->assertOk();
        $this->assertSame('TERLAMBAT', $r->json('data.header.status_progres'));
        $this->assertTrue($r->json('data.header.is_kritis'));
        $this->assertCount(1, $r->json('data.kegiatan'));
        $this->assertSame('CP turunan Alfa', $r->json('data.kegiatan.0.turunan.0.nama'));
        $this->assertEquals(30.0, $r->json('data.progres.kurva_s.8.realisasi_persen'));   // September
        $this->assertNull($r->json('data.progres.kurva_s.7.realisasi_persen'));            // Agustus: belum dilaporkan
        $this->assertTrue($r->json('data.progres.isu_terbuka.0.lewat_tenggat'));
    }

    public function test_perubahan_lewat_aplikasi_muncul_di_riwayat_detail(): void
    {
        $admin = $this->buatPengguna('Super Admin');
        $this->actingAs($admin);
        $this->psn['a']->update(['nama' => 'Alfa Bendungan Baru']);

        $this->get("/proyek/{$this->psn['a']->id}?tab=dokumen")->assertOk()
            ->assertSee($admin->username)->assertSee('mengubah profil PSN')->assertSee('Alfa Bendungan Baru');
    }

    // ------------------------------------------------------------ Kualitas Data

    public function test_kualitas_data_kpi_heatmap_dan_field_kosong(): void
    {
        $r = $this->getJson('/api/v1/kualitas-data')->assertOk()->assertJsonPath('meta.sebelumnya', '2026-08');
        $d = $r->json('data');

        $this->assertSame(1, (int) $d['kpi']['menunggu_verifikasi']['nilai']);
        $this->assertSame(2, $d['kpi']['sektor_tepat_waktu']['total_sektor']);     // Dit 07 & Dit 19
        $this->assertEquals(0, $d['kpi']['sektor_tepat_waktu']['nilai']);           // Dit 19: C belum diajukan
        $this->assertSame(3, $d['kpi']['belum_diperbarui']['total_psn']);

        $this->assertSame(['Direktorat Konektivitas dan Infastruktur Logistik', 'Direktorat Sumber Daya Air'], collect($d['heatmap']['sektor'])->pluck('label')->all());
        $this->assertCount(1 + 12 + 5, $d['heatmap']['bagian']);
        $this->assertCount(2 * 18, $d['heatmap']['sel']);
        $urgensi = collect($d['heatmap']['sel'])->firstWhere(fn ($s) => $s['bagian'] === 'item:A' && $s['sektor_id'] === $this->ref('ref_unit_kerja', '07'));
        $this->assertEquals(100.0, $urgensi['persen']);                              // A mengisi Urgensi

        $kosong = collect($d['field_kosong'])->firstWhere('nama', 'Charlie Pangan');
        $this->assertStringContainsString('Gambaran Umum (', $kosong['kosong'][0]);
        $this->assertContains('KP/RO', $kosong['kosong']);
        $this->assertNotContains('Risiko', $kosong['kosong']);
    }

    public function test_kualitas_data_hak_akses(): void
    {
        $this->actingAs(User::factory()->create(['username' => 'tanpa.peran']));
        $this->getJson('/api/v1/kualitas-data')->assertForbidden();
        $this->get('/kualitas-data')->assertForbidden();

        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->assertSame(['Alfa Bendungan'], collect($this->getJson('/api/v1/kualitas-data')->json('data.field_kosong'))->pluck('nama')->all());
    }
}
