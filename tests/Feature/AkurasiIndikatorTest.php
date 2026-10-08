<?php

namespace Tests\Feature;

use App\Models\PeriodeCutoff;
use App\Services\DashboardService;
use App\Services\SnapshotService;
use App\Support\Akurasi\UjiAkurasi;
use App\Support\Dashboard\FilterGlobal;
use App\Support\Legacy\LegacyImporter;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Kriteria selesai v1: angka dashboard K1-K4 & P1-P7 identik dengan query SQL acuan
 * (App\Support\Akurasi\KueriAcuan) untuk berbagai kombinasi filter.
 */
class AkurasiIndikatorTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    /** Data uji dengan kasus tepi: seri di batas 8 teratas, multi-lokasi/dana, anomali, PSN keluar & terhapus. */
    protected function buatDataUji(): void
    {
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $klaster = DB::table('ref_klaster')->orderBy('id')->pluck('id')->all();
        $prov = ['31', '32', '33', '35', '51', '64', '73', '94'];
        $dana = ['A', 'B', 'C', 'D', 'E'];
        $unit = ['07', '19', '09', '101'];
        $status = ['1', '2', '3', '4', '5'];

        for ($i = 0; $i < 30; $i++) {
            $p = $this->buatPsn([
                'nama' => sprintf('PSN Uji %02d', $i),
                // Klaster 0..9 dengan jumlah bertingkat sehingga terjadi seri di peringkat ke-8.
                'klaster_id' => $klaster[[0, 0, 0, 0, 1, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 8, 9, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9][$i]],
                'klaster_pkpn_id' => $i % 4 === 0 ? $this->ref('ref_klaster_pkpn', 'A') : null,
                'status_psn_id' => $this->ref('ref_status_psn', $i === 29 ? '6' : $status[$i % 5]),
                'nilai_investasi_rp' => $i === 7 ? 805 : ($i % 6 === 5 ? null : ($i + 1) * 1.5e12),
                'investasi_anomali' => $i === 7,
            ], [$unit[$i % 4], ...($i % 5 === 0 ? [$unit[($i + 1) % 4]] : [])]);

            foreach (array_unique([$prov[$i % 8], $prov[($i * 3) % 8]]) as $k) {
                DB::table('psn_lokasi')->insert(['psn_id' => $p->id, 'provinsi_kode' => $k]);
            }
            foreach (array_unique([$dana[$i % 5], $dana[($i * 2) % 5]]) as $k) {
                DB::table('psn_sumber_dana')->insert(['psn_id' => $p->id, 'sumber_dana_id' => $this->ref('ref_sumber_dana', $k)]);
            }
            if ($i % 3 === 0) {
                $this->buatKegiatan($p, [
                    ['periode' => 'BULANAN', 'periode_ke' => 8, 'target_persen' => 40 + $i, 'realisasi_persen' => 30 + $i, 'pagu_rp' => 2e9, 'realisasi_anggaran_rp' => 1e9, 'dilaporkan_at' => '2026-08-25'],
                    ['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 50 + $i, 'realisasi_persen' => 20 + $i * 2, 'pagu_rp' => 2e9, 'realisasi_anggaran_rp' => 1.4e9, 'dilaporkan_at' => '2026-09-25'],
                ], ['is_critical_path' => $i % 2 === 0]);
                $this->buatKegiatan($p, [['periode' => 'TAHUNAN', 'periode_ke' => 0, 'target_1' => 100, 'realisasi_1' => 60, 'pagu_rp' => 5e9, 'realisasi_anggaran_rp' => 2e9, 'dilaporkan_at' => '2026-09-01']]);
            }
            if ($i % 4 === 1) {
                DB::table('risiko')->insert(['psn_id' => $p->id, 'uraian' => 'R', 'kemungkinan_harapan' => 1 + $i % 5, 'dampak_harapan' => 5]);
            }
            if ($i === 13) {
                DB::table('psn')->where('id', $p->id)->update(['deleted_at' => '2026-07-01']);
            }
        }

        app(SnapshotService::class)->buat('2026-08', terbit: true);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
    }

    protected function periksa(PeriodeCutoff $c, array $kombinasiTambahan = []): void
    {
        $uji = app(UjiAkurasi::class);
        $hasil = $uji->jalankan($c, [...$uji->kombinasiBawaan($c), ...$kombinasiTambahan]);
        $selisih = array_values(array_filter($hasil, fn ($r) => ! $r['cocok']));

        $this->assertGreaterThanOrEqual(100, count($hasil));
        $this->assertSame([], $selisih, 'Selisih aplikasi vs acuan: '.json_encode($selisih, JSON_UNESCAPED_UNICODE));
    }

    public function test_k1_k4_p1_p7_identik_dengan_acuan_pada_data_uji(): void
    {
        $this->buatDataUji();
        $unit = $this->ref('ref_unit_kerja', '19');
        $this->periksa(PeriodeCutoff::where('kode', '2026-09')->first(), ['prov=94', 'prov=31,35&kat=psn', "dit={$unit}&status=terlambat", 'dana=apbd', 'status=on_track,berisiko']);
        $this->periksa(PeriodeCutoff::where('kode', '2026-08')->first());
    }

    public function test_urutan_seri_deterministik_untuk_url_yang_sama(): void
    {
        $this->buatDataUji();
        $c = PeriodeCutoff::terbaru();
        $f = new FilterGlobal;
        $p1 = collect(app(DashboardService::class)->distribusi($c, $f, 'klaster'))->pluck('jumlah', 'label');

        // Jumlah menurun; nilai seri diurutkan menurut nama.
        $baris = $p1->except('Lainnya');
        $this->assertSame($baris->all(), DashboardService::urutDeterministik($baris->map(fn ($n, $l) => ['label' => $l, 'jumlah' => $n])->values())->pluck('jumlah', 'label')->all());
        $this->assertSame(config('psn_dashboard.p1_top_n') + 1, $p1->count());
    }

    public function test_perintah_uji_akurasi(): void
    {
        $this->buatDataUji();
        $this->artisan('psn:uji-akurasi', ['--hanya-selisih' => true])->expectsOutputToContain('0 selisih')->assertSuccessful();
        $this->artisan('psn:uji-akurasi', ['periode' => '2025-01'])->assertFailed();
    }

    /** Uji pada data riil hasil impor basis data lama (LEGACY_TEST=1). */
    public function test_akurasi_pada_data_riil(): void
    {
        if (! env('LEGACY_TEST')) {
            $this->markTestSkipped('Set LEGACY_TEST=1 dan koneksi legacy untuk uji akurasi data riil.');
        }
        $this->seed(PeranSeeder::class);
        app(LegacyImporter::class)->jalankan();
        app(SnapshotService::class)->buat('2026-08', terbit: true);
        app(SnapshotService::class)->buat('2026-09', terbit: true);

        $this->periksa(PeriodeCutoff::where('kode', '2026-09')->first(), ['prov=32&dana=kpbu', 'kat=pkpn&status=tanpa_data']);
    }
}
