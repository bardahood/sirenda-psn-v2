<?php

namespace Tests\Feature;

use App\Models\SnapshotPsn;
use App\Services\SnapshotService;
use App\Support\DashboardCache;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class SnapshotServiceTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
    }

    protected function snap(int $psnId, string $cutoff = '2026-09'): SnapshotPsn
    {
        return SnapshotPsn::withoutGlobalScopes()->whereHas('periodeCutoff', fn ($q) => $q->where('kode', $cutoff))->where('psn_id', $psnId)->firstOrFail();
    }

    public function test_progres_psn_tertimbang_pagu_dan_status_terlambat(): void
    {
        $psn = $this->buatPsn(['nilai_investasi_rp' => 100e9]);
        $this->buatKegiatan($psn, [
            ['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 60, 'realisasi_persen' => 30, 'pagu_rp' => 300, 'dilaporkan_at' => '2026-09-20'],
            // Periode sesudah cut-off diabaikan.
            ['periode' => 'BULANAN', 'periode_ke' => 10, 'target_persen' => 70, 'realisasi_persen' => 70, 'pagu_rp' => 0, 'dilaporkan_at' => '2026-10-20'],
        ]);
        $this->buatKegiatan($psn, [
            ['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 50, 'realisasi_persen' => 52, 'pagu_rp' => 100, 'dilaporkan_at' => '2026-09-25'],
        ]);

        app(SnapshotService::class)->buat('2026-09');
        $s = $this->snap($psn->id);

        // rencana (60x300 + 50x100)/400 = 57,5; realisasi (30x300 + 52x100)/400 = 35,5
        $this->assertEquals(57.5, $s->progres_rencana_persen);
        $this->assertEquals(35.5, $s->progres_realisasi_persen);
        $this->assertEquals(-22.0, $s->deviasi_pp);
        $this->assertSame('TERLAMBAT', $s->status_progres);
        $this->assertTrue($s->is_kritis);
        $this->assertSame(2, $s->jumlah_ro);
        $this->assertSame(1, $s->jumlah_ro_tercapai);
        $this->assertEquals(400, $s->pagu_rp);

        $ro = DB::table('snapshot_kegiatan')->orderBy('kegiatan_id')->pluck('status_progres')->all();
        $this->assertSame(['TERLAMBAT', 'ON_TRACK'], $ro);
    }

    public function test_target_volume_tahunan_memakai_rencana_linear(): void
    {
        $psn = $this->buatPsn();
        $this->buatKegiatan($psn, [['periode' => 'TAHUNAN', 'periode_ke' => 0, 'target_1' => 100, 'realisasi_1' => 50, 'dilaporkan_at' => '2026-09-01']]);

        app(SnapshotService::class)->buat('2026-09');
        $s = $this->snap($psn->id);

        $this->assertEquals(75.0, $s->progres_rencana_persen); // 9/12
        $this->assertEquals(50.0, $s->progres_realisasi_persen);
        $this->assertSame('TERLAMBAT', $s->status_progres);
    }

    public function test_tanpa_data_bila_pelaporan_lebih_dari_35_hari_atau_belum_dilaporkan(): void
    {
        $lama = $this->buatPsn();
        $this->buatKegiatan($lama, [['periode' => 'BULANAN', 'periode_ke' => 8, 'target_persen' => 50, 'realisasi_persen' => 50, 'dilaporkan_at' => '2026-08-25']]);
        $nol = $this->buatPsn();
        // Data lama menyimpan isian kosong sebagai 0 tanpa tanggal lapor: bukan realisasi 0%.
        $this->buatKegiatan($nol, [['periode' => 'TAHUNAN', 'periode_ke' => 0, 'target_1' => 10, 'realisasi_1' => 0]]);
        $tanpaRo = $this->buatPsn();

        app(SnapshotService::class)->buat('2026-09');

        $this->assertSame('TANPA_DATA', $this->snap($lama->id)->status_progres);    // 36 hari
        $this->assertSame('TANPA_DATA', $this->snap($nol->id)->status_progres);
        $this->assertNull($this->snap($nol->id)->progres_realisasi_persen);
        $this->assertSame('TANPA_DATA', $this->snap($tanpaRo->id)->status_progres);
    }

    public function test_risiko_residual_aktual_mengalahkan_harapan_dan_pemantauan_sesudah_cutoff_diabaikan(): void
    {
        $psn = $this->buatPsn(['status_psn_id' => $this->ref('ref_status_psn', '6')]);
        $rid = DB::table('risiko')->insertGetId(['psn_id' => $psn->id, 'uraian' => 'R', 'kemungkinan_harapan' => 4, 'dampak_harapan' => 4]);
        DB::table('risiko_pemantauan')->insert([
            ['risiko_id' => $rid, 'tanggal' => '2026-09-10', 'tahun' => 2026, 'kemungkinan_aktual' => 2, 'dampak_aktual' => 2],
            ['risiko_id' => $rid, 'tanggal' => '2026-10-05', 'tahun' => 2026, 'kemungkinan_aktual' => 5, 'dampak_aktual' => 5],
        ]);
        $hanyaHarapan = $this->buatPsn();
        DB::table('risiko')->insert(['psn_id' => $hanyaHarapan->id, 'uraian' => 'R2', 'level_harapan' => 'Tinggi']);

        app(SnapshotService::class)->buat('2026-09');

        $s = $this->snap($psn->id);
        $this->assertFalse($s->is_aktif, 'status 6 = keluar dari PSN');
        $this->assertSame('Rendah', $s->risiko_level_maks);
        $this->assertSame(4, $s->risiko_skor_maks);
        $this->assertFalse($s->is_kritis);
        $this->assertSame('Tinggi', DB::table('snapshot_risiko')->where('risiko_id', $rid)->value('level_harapan'));

        $h = $this->snap($hanyaHarapan->id);
        $this->assertSame('Tinggi', $h->risiko_level_maks);
        $this->assertTrue($h->is_kritis);
    }

    public function test_dimensi_kelengkapan_dan_psn_terhapus(): void
    {
        $psn = $this->buatPsn(['nilai_investasi_rp' => 5e12, 'klaster_pkpn_id' => $this->ref('ref_klaster_pkpn', 'A')], ['07', '19']);
        DB::table('psn_lokasi')->insert([['psn_id' => $psn->id, 'provinsi_kode' => '32'], ['psn_id' => $psn->id, 'provinsi_kode' => '31']]);
        $anomali = $this->buatPsn(['nilai_investasi_rp' => 805, 'investasi_anomali' => true]);
        $terhapus = $this->buatPsn();
        DB::table('psn')->where('id', $terhapus->id)->update(['deleted_at' => '2026-09-15']);

        app(SnapshotService::class)->buat('2026-09');
        $s = $this->snap($psn->id);

        $this->assertSame(['31', '32'], $s->provinsi_kode);
        $this->assertSame('PKPN', $s->kategori);
        $this->assertSame('KONSTRUKSI', $s->tahap);
        $this->assertCount(2, $s->unit_kerja_id);
        // gambaran_umum: nama, klaster, status, investasi terisi = 4/7; item 0/12; relasi lokasi+unit = 2/5
        $this->assertEquals(round(6 / 24 * 100, 2), $s->kelengkapan_persen);
        $this->assertNull($this->snap($anomali->id)->nilai_investasi_rp, 'investasi anomali dikecualikan dari K2');
        $this->assertSame(0, SnapshotPsn::withoutGlobalScopes()->where('psn_id', $terhapus->id)->count());
        $this->assertSame(2, DB::table('pengisian_psn')->count(), 'baris pengisian DRAFT untuk PSN aktif');
    }

    public function test_terbit_mencatat_audit_membatalkan_cache_dan_mengunci_snapshot(): void
    {
        $this->buatPsn();
        $versi = DashboardCache::versi();

        $hasil = app(SnapshotService::class)->buat('2026-09', terbit: true);

        $this->assertSame('TERBIT', $hasil['status']);
        $this->assertSame($versi + 1, DashboardCache::versi());
        $this->assertTrue(DB::table('audit_log')->where(['tabel' => 'periode_cutoff', 'aksi' => 'PUBLISH'])->exists());

        $this->expectException(RuntimeException::class);
        app(SnapshotService::class)->buat('2026-09');
    }

    public function test_paksa_membangun_ulang_tanpa_duplikasi(): void
    {
        $this->buatPsn();
        app(SnapshotService::class)->buat('2026-09', terbit: true);
        app(SnapshotService::class)->buat('2026-09', paksa: true);

        $this->assertSame(1, DB::table('snapshot_psn')->count());
        $this->assertSame(1, DB::table('pengisian_psn')->count());
    }

    public function test_command_menolak_format_cutoff_salah(): void
    {
        $this->artisan('psn:snapshot', ['cutoff' => '2026-13'])->assertFailed();
        $this->artisan('psn:snapshot', ['cutoff' => '2026-09'])->assertSuccessful();
    }
}
