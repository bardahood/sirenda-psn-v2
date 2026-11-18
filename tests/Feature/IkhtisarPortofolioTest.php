<?php

namespace Tests\Feature;

use App\Services\IkhtisarPortofolioService;
use App\Services\SnapshotService;
use App\Support\DashboardCache;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Dashboard "Ikhtisar Portofolio" (gaya paparan Bahan Rapat 16 Nov 2026).
 *
 * Data (cut-off 2026-09):
 *  A  RKP, Jawa (31+32), APBN, Rp 200 T, PKPN, konstruksi, selesai 2028
 *  B  RKP, Sumatera (11) + Jawa (31) -> lintas, Badan Usaha, Rp 100 T, konstruksi, selesai 2031
 *  C  RKP, Kumulatif selesai, Sulawesi (73), APBN + BUMN, Rp 50 T, kebutuhan "Tidak memerlukan status PSN"
 *  D  RKP, Kumulatif selesai, tanpa lokasi -> lintas, tanpa dana, kebutuhan "Diusulkan Tetap dipertahankan dalam Status PSN."
 *  E  Permenko, Kumulatif selesai, Papua (91), APBD, Rp 10 T, kebutuhan "Pemantauan proses terminasi" -> konfirmasi
 *  F  RKP, keluar dari PSN
 */
class IkhtisarPortofolioTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected array $psn = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $selesai = $this->ref('ref_status_psn', '1');

        $p = [
            'a' => [['nama' => 'A', 'nilai_investasi_rp' => 200e12, 'klaster_pkpn_id' => $this->ref('ref_klaster_pkpn', 'A'), 'tahun_selesai' => 2028], ['31', '32'], ['A']],
            'b' => [['nama' => 'B', 'nilai_investasi_rp' => 100e12, 'tahun_selesai' => 2031], ['11', '31'], ['D']],
            'c' => [['nama' => 'C', 'nilai_investasi_rp' => 50e12, 'status_psn_id' => $selesai], ['73'], ['A', 'C']],
            'd' => [['nama' => 'D', 'status_psn_id' => $selesai], [], []],
            'e' => [['nama' => 'E', 'nilai_investasi_rp' => 10e12, 'status_psn_id' => $selesai, 'sumber_input' => 2], ['91'], ['B']],
            'f' => [['nama' => 'F', 'status_psn_id' => $this->ref('ref_status_psn', '6')], ['31'], ['A']],
        ];
        foreach ($p as $k => [$atribut, $prov, $dana]) {
            $this->psn[$k] = $this->buatPsn($atribut + ['sumber_input' => 1, 'legacy_id' => ord($k)]);
            foreach ($prov as $kode) {
                DB::table('psn_lokasi')->insert(['psn_id' => $this->psn[$k]->id, 'provinsi_kode' => $kode]);
            }
            foreach ($dana as $kode) {
                DB::table('psn_sumber_dana')->insert(['psn_id' => $this->psn[$k]->id, 'sumber_dana_id' => $this->ref('ref_sumber_dana', $kode)]);
            }
        }
        foreach (['c' => 'Tidak memerlukan status PSN', 'd' => 'Diusulkan Tetap dipertahankan dalam Status PSN.', 'e' => 'Pemantauan proses terminasi penyelesaian KPBU'] as $k => $teks) {
            DB::table('psn_evaluasi_status')->insert(['psn_id' => $this->psn[$k]->id, 'tahun' => 2026, 'kebutuhan_status' => $teks]);
        }

        app(SnapshotService::class)->buat('2026-09', terbit: true);
        $this->actingAs($this->buatPengguna('Pimpinan'));
    }

    public function test_halaman_ikhtisar_dan_pelaksanaan(): void
    {
        $this->get('/dashboard')->assertOk()->assertSee('Perjalanan Daftar PSN dalam RKP')->assertSee('Ikhtisar Portofolio')->assertSee('Pemantauan Pelaksanaan');
        $this->get('/dashboard/pelaksanaan')->assertOk()->assertSee('Peta Prioritas: Nilai Investasi vs Deviasi')->assertSee('Ikhtisar Portofolio');
    }

    public function test_pkpn_wilayah_dan_skema_tiga_kelompok(): void
    {
        $d = $this->getJson('/api/v1/dashboard/ikhtisar')->assertOk()->json('data');

        $this->assertSame(1, $d['pkpn']['jumlah']);
        $this->assertEquals(200.0, $d['pkpn']['investasi_triliun']);
        $this->assertEquals(round(200 / 360 * 100, 1), $d['pkpn']['persen_investasi']);

        $w = collect($d['wilayah']['kelompok'])->keyBy('label');
        $this->assertSame([1, 1, 1, 2], [$w['Jawa']['jumlah'], $w['Sulawesi']['jumlah'], $w['Maluku & Papua']['jumlah'], $w['Nasional & Lintas Pulau']['jumlah']]);
        $this->assertSame(0, $w['Sumatera']['jumlah']);              // B di Sumatera + Jawa -> lintas pulau
        $this->assertSame(5, collect($d['wilayah']['kelompok'])->sum('jumlah')); // setiap PSN aktif tepat sekali
        $this->assertSame(1, $w['Jawa']['pkpn']);

        $s = collect($d['skema'])->keyBy('kode');
        $this->assertSame([1, 1, 2, 1], [$s['non_apbn']['jumlah'], $s['campuran']['jumlah'], $s['apbn_apbd']['jumlah'], $s['tanpa']['jumlah']]);
        $this->assertEquals([100.0, 50.0, 210.0], [$s['non_apbn']['investasi_triliun'], $s['campuran']['investasi_triliun'], $s['apbn_apbd']['investasi_triliun']]);
        $this->assertEqualsWithDelta(100, collect($d['skema'])->sum('persen'), 0.2);

        // Rincian nominal per sumber dana (psn_pendanaan.nilai): C dirinci APBN 20 T + BUMN 30 T.
        DB::table('psn_sumber_dana')->where('psn_id', $this->psn['c']->id)->where('sumber_dana_id', $this->ref('ref_sumber_dana', 'A'))->update(['nilai_rp' => 20e12]);
        DB::table('psn_sumber_dana')->where('psn_id', $this->psn['c']->id)->where('sumber_dana_id', $this->ref('ref_sumber_dana', 'C'))->update(['nilai_rp' => 30e12]);
        // A menulis Rp 400 T untuk investasi Rp 200 T -> tidak konsisten, tidak dijumlahkan.
        DB::table('psn_sumber_dana')->where('psn_id', $this->psn['a']->id)->update(['nilai_rp' => 400e12]);
        DashboardCache::flush();
        $n = $this->getJson('/api/v1/dashboard/ikhtisar')->json('data.skema_nominal');
        $this->assertEquals([1, 1, 20.0, 30.0, 60.0], [$n['cakupan_psn'], $n['tidak_konsisten'], $n['apbn_apbd_triliun'], $n['non_apbn_triliun'], $n['persen_non_apbn']]);
        $this->get(route('proyek.show', $this->psn['a']))->assertSee('melebihi nilai investasi')->assertSee('Rp 400,00 triliun');
        $this->get(route('proyek.show', $this->psn['c']))->assertDontSee('melebihi nilai investasi')->assertSee('Rp 20,00 triliun');

        // Filter global berlaku (Sumber PSN = Permenko -> hanya E).
        $this->assertSame(1, collect($this->getJson('/api/v1/dashboard/ikhtisar?sumber=permenko')->json('data.wilayah.kelompok'))->sum('jumlah'));
    }

    public function test_psn_selesai_menurut_kebutuhan_status(): void
    {
        DB::table('evaluasi_psn')->insert(['psn_id' => $this->psn['e']->id, 'tahun_rkp' => 2027, 'd1_kebutuhan' => 'TIDAK']);
        $k = collect($this->getJson('/api/v1/dashboard/ikhtisar')->json('data.selesai.kategori'))->keyBy('kode');

        $this->assertSame(['C', 'E'], collect($k['TIDAK']['psn'])->pluck('nama')->all());   // urutan data SIRENDA
        $this->assertSame(['D'], collect($k['MASIH']['psn'])->pluck('nama')->all());
        // E: teks SIRENDA tidak tegas -> dipakai D1 Evaluasi (TIDAK).
        $e = collect($k['TIDAK']['psn'])->firstWhere('nama', 'E');
        $this->assertSame('EVALUASI', $e['sumber']);
        $this->assertSame(0, $k['KONFIRMASI']['jumlah']);

        $s = app(IkhtisarPortofolioService::class);
        foreach ([
            'Tidak membutuhkan status psn tahun selanjutnya' => 'TIDAK', 'Bisa dikeluarkan dari status PSN' => 'TIDAK', 'Tidak Perlu' => 'TIDAK',
            'Masih membutuhkan status PSN' => 'MASIH', 'Berlanjut kegiatan PSN periode selanjutnya' => 'MASIH', 'memerlukan status PSN' => 'MASIH',
            'Proyek KCJB sebagai PSN dimasa operasi' => 'MASIH', 'Perluasan cakupan Proyek Strategis Nasional' => 'KONFIRMASI', 'Kebutuhan Dukungan Utama PSN' => 'KONFIRMASI',
        ] as $teks => $harap) {
            $this->assertSame($harap, $s->kategoriKebutuhan($teks), $teks);
        }
    }

    public function test_status_target_dan_perjalanan_daftar_rkp(): void
    {
        $d = $this->getJson('/api/v1/dashboard/ikhtisar?sumber=permenko')->json('data');
        $st = $this->getJson('/api/v1/dashboard/ikhtisar')->json('data.status');

        $tahap = collect($st['tahap'])->pluck('jumlah', 'kode');
        $this->assertSame([2, 3], [$tahap['KONSTRUKSI'], $tahap['KUMULATIF_SELESAI']]);
        $this->assertSame(1, $st['keluar']);
        $this->assertSame([0, 1, 1, 0], collect($st['target'])->pluck('jumlah')->all()); // A 2028, B 2031

        // Perjalanan RKP tidak mengikuti filter: Perpres 68/2026 = PSN bersumber RKP (A-D, F) = 5; R-RKP 2027 = 5 - 1 keluar.
        $rkp = collect($d['rkp'])->keyBy('kode');
        $this->assertSame(228, $rkp['PERMENKO_12_2024']['jumlah']);
        $this->assertSame([5, 298, true], [$rkp['PERPRES_68_2026']['jumlah'], $rkp['PERPRES_68_2026']['dokumen'], $rkp['PERPRES_68_2026']['live']]);
        $this->assertSame(4, $rkp['R_RKP_2027']['jumlah']);
        $this->assertSame(['+', 0], [$rkp['R_RKP_2027']['perubahan'][0]['arah'], $rkp['R_RKP_2027']['perubahan'][0]['jumlah']]);
        $this->assertSame(1, $rkp['R_RKP_2027']['perubahan'][1]['jumlah']);
        $this->assertStringContainsString('F', $rkp['R_RKP_2027']['perubahan'][1]['rincian']);

        // Indikasi Dikeluarkan di Evaluasi ikut mengurangi proyeksi.
        DB::table('evaluasi_psn')->insert(['psn_id' => $this->psn['a']->id, 'tahun_rkp' => 2027, 'indikasi_status' => 'DIKELUARKAN']);
        DashboardCache::flush();
        $this->assertSame(3, collect($this->getJson('/api/v1/dashboard/ikhtisar')->json('data.rkp'))->firstWhere('kode', 'R_RKP_2027')['jumlah']);
    }
}
