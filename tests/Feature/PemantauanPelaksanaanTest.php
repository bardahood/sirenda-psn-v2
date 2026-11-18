<?php

namespace Tests\Feature;

use App\Models\PeriodeCutoff;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/** Dashboard > Pemantauan Pelaksanaan (docs/kamus-indikator.md, bagian Pemantauan Pelaksanaan). */
class PemantauanPelaksanaanTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    public function test_delapan_panel_pelaksanaan_dari_snapshot(): void
    {
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $unit = (int) DB::table('ref_unit_kerja')->where('jenis', 'KL')->orderBy('id')->value('id');   // unit K/L (panel kepatuhan)
        $a = $this->buatPsn(['nama' => 'Bendungan A']);
        $b = $this->buatPsn(['nama' => 'Tol B']);
        $c = $this->buatPsn(['nama' => 'Kawasan C']);
        $pj = DB::table('ref_penanggung_jawab')->insertGetId(['kode' => 'UJI', 'nama' => 'Menteri Uji']);
        DB::table('psn_kelembagaan')->insert(['psn_id' => $a->id, 'peran' => 'PENANGGUNG_JAWAB', 'penanggung_jawab_id' => $pj, 'urutan' => 1]);
        DB::table('isu')->insert([
            ['psn_id' => $a->id, 'uraian' => 'Pembebasan lahan 12 bidang', 'status' => 'TERBUKA'],
            ['psn_id' => $a->id, 'uraian' => 'Persetujuan AMDAL belum terbit', 'status' => 'PROSES'],
            ['psn_id' => $b->id, 'uraian' => 'Kepastian pendanaan investor', 'status' => 'TERBUKA'],
            ['psn_id' => $b->id, 'uraian' => 'Sudah beres', 'status' => 'SELESAI'],
        ]);

        $lalu = PeriodeCutoff::create(['kode' => '2026-09', 'tanggal_cutoff' => '2026-09-30', 'status' => 'TERBIT', 'diterbitkan_at' => now()->subMonth()]);
        $kini = PeriodeCutoff::create(['kode' => '2026-10', 'tanggal_cutoff' => '2026-10-31', 'status' => 'TERBIT', 'diterbitkan_at' => now()]);
        $snap = fn ($c, $psn, array $x) => DB::table('snapshot_psn')->insert($x + [
            'periode_cutoff_id' => $c->id, 'psn_id' => $psn->id, 'klaster_id' => $psn->klaster_id, 'is_aktif' => true,
            'unit_kerja_id' => json_encode([$unit]), 'provinsi_kode' => json_encode([]), 'sumber_dana_id' => json_encode([]),
        ]);
        // Cut-off lalu: A on track, B berisiko, C on track.
        $snap($lalu, $a, ['nilai_investasi_rp' => 50e12, 'progres_rencana_persen' => 30, 'progres_realisasi_persen' => 29, 'deviasi_pp' => -1, 'status_progres' => 'ON_TRACK']);
        $snap($lalu, $b, ['nilai_investasi_rp' => 10e12, 'progres_rencana_persen' => 30, 'progres_realisasi_persen' => 20, 'deviasi_pp' => -10, 'status_progres' => 'BERISIKO']);
        $snap($lalu, $c, ['nilai_investasi_rp' => 1e12, 'progres_rencana_persen' => 30, 'progres_realisasi_persen' => 30, 'deviasi_pp' => 0, 'status_progres' => 'ON_TRACK']);
        // Cut-off kini: A terlambat (memburuk), B on track (membaik), C tanpa data (berhenti melapor).
        $snap($kini, $a, ['nilai_investasi_rp' => 50e12, 'progres_rencana_persen' => 50, 'progres_realisasi_persen' => 25, 'deviasi_pp' => -25, 'status_progres' => 'TERLAMBAT',
            'is_kritis' => true, 'risiko_level_maks' => 'Sangat Tinggi', 'pagu_rp' => 4e12, 'realisasi_anggaran_rp' => 1e12]);
        $snap($kini, $b, ['nilai_investasi_rp' => 10e12, 'progres_rencana_persen' => 40, 'progres_realisasi_persen' => 40, 'deviasi_pp' => 0, 'status_progres' => 'ON_TRACK']);
        $snap($kini, $c, ['nilai_investasi_rp' => 1e12, 'status_progres' => 'TANPA_DATA']);

        $this->actingAs($this->buatPengguna('Pimpinan'));
        $d = $this->getJson('/api/v1/dashboard/pelaksanaan')->assertOk()->assertJsonPath('meta.cutoff.kode', '2026-10')->json('data');

        // 1. KPI
        $this->assertSame(3, (int) $d['kpi']['nilai']['aktif']);
        $this->assertSame(2, $d['kpi']['nilai']['melapor']);
        $this->assertEqualsWithDelta(66.7, $d['kpi']['nilai']['cakupan'], 0.01);
        $this->assertEqualsWithDelta(-33.3, $d['kpi']['delta']['cakupan']['nilai'], 0.01);
        $this->assertSame(25.0, (float) $d['kpi']['nilai']['serapan']);
        $this->assertSame(1, (int) $d['kpi']['nilai']['kritis']);
        $this->assertCount(2, $d['kpi']['tren']);

        // 2. Kurva: dua cut-off -> proyeksi Desember dari laju per bulan, maks. 100.
        $this->assertNotNull($d['kurva']['proyeksi']);
        $this->assertSame(10, $d['kurva']['proyeksi']['bulan_awal']);

        // 3. Kuadran: hanya PSN berdata deviasi; A di area besar dan tertinggal.
        $this->assertCount(2, $d['kuadran']['titik']);
        $this->assertSame(1, $d['kuadran']['tanpa_data']);
        $this->assertSame(1, $d['kuadran']['besar_terlambat']);

        // 4. Perhatian: A teratas (terlambat + risiko sangat tinggi + investasi), PJ ikut.
        $this->assertSame('Bendungan A', $d['perhatian'][0]['nama']);
        $this->assertSame('Menteri Uji', $d['perhatian'][0]['pj']);
        $this->assertContains('Risiko Sangat Tinggi', array_column($d['perhatian'][0]['alasan'], 'label'));
        $this->assertNotContains('Tol B', array_column($d['perhatian'], 'nama'));   // on track tanpa alasan lain

        // 5. Matriks
        $this->assertSame(['ON_TRACK' => 1, 'BERISIKO' => 0, 'TERLAMBAT' => 1, 'TANPA_DATA' => 1], $d['matriks']['total']);

        // 6. Kepatuhan: satu K/L, 2 dari 3 melapor.
        $this->assertSame(1, $d['kepatuhan']['jumlah_kl']);
        $this->assertSame(2, $d['kepatuhan']['kl'][0]['melapor']);

        // 7. Hambatan: isu selesai tidak dihitung; kategori dari kata kunci; isu A pada PSN kritis.
        $kat = collect($d['hambatan']['kategori'])->keyBy('kode');
        $this->assertSame(3, $d['hambatan']['total']);
        $this->assertSame(1, $kat['LAHAN']['psn_kritis']);
        $this->assertSame(1, $kat['PERIZINAN']['jumlah']);
        $this->assertSame(1, $kat['PENDANAAN']['psn_lain']);

        // 8. Pergerakan
        $g = collect($d['pergerakan']['kelompok'])->pluck('jumlah', 'kode');
        $this->assertSame([1, 1, 1], [$g['MEMBAIK'], $g['MEMBURUK'], $g['BERHENTI']]);
        $this->assertSame('Bendungan A', $d['pergerakan']['sorot'][0]['nama']);

        // Filter global berlaku: hanya PSN on track.
        $this->getJson('/api/v1/dashboard/pelaksanaan?status=on_track')->assertOk()->assertJsonPath('data.kpi.nilai.aktif', 1);
    }
}
