<?php

namespace Tests\Feature;

use App\Models\EvaluasiPsn;
use App\Services\EvaluasiStatusService;
use App\Services\SnapshotService;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/** Evaluasi keberlanjutan status PSN (docs/evaluasi-status-psn.md). */
class EvaluasiStatusTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
    }

    protected function siapkan(): array
    {
        // Konstruksi, progres tertinggal 15 poin, isu jalur kritis terbuka dengan dukungan.
        $a = $this->buatPsn(['nama' => 'Bendungan Evaluasi A', 'kode_psn' => 'R.1', 'tahun_selesai' => 2027, 'sumber_input' => 1], ['07']);
        $cp = $this->buatKegiatan($a, [['periode' => 'BULANAN', 'periode_ke' => 10, 'target_persen' => 60, 'realisasi_persen' => 45, 'pagu_rp' => 100e9,
            'realisasi_anggaran_rp' => 30e9, 'dilaporkan_at' => '2026-10-20']], ['is_critical_path' => true]);
        DB::table('isu')->insert(['psn_id' => $a->id, 'kegiatan_id' => $cp->id, 'uraian' => 'Lahan belum bebas', 'status' => 'TERBUKA', 'kebutuhan_dukungan' => 'Konsinyasi']);
        // Kumulatif selesai: tanpa D1 sistem mengindikasikan dikeluarkan.
        $b = $this->buatPsn(['nama' => 'Jalan Tol Evaluasi B', 'kode_psn' => 'M.2', 'tahun_selesai' => 2025, 'sumber_input' => 2,
            'status_psn_id' => $this->ref('ref_status_psn', '1')], ['08']);
        app(SnapshotService::class)->buat('2026-10', terbit: true);

        return [$a, $b];
    }

    public function test_alur_isian_pengecekan_keputusan_dan_riwayat(): void
    {
        [$a, $b] = $this->siapkan();
        $layanan = app(EvaluasiStatusService::class);
        $baris = $layanan->daftar()->keyBy('psn_id');
        $this->assertSame('BELUM_DITERIMA', $baris[$a->id]['status_akhir']);
        $this->assertSame(-15.0, $baris[$a->id]['indikator']['deviasi_fisik']);
        $this->assertSame(30.0, $baris[$a->id]['indikator']['serapan']);
        $this->assertContains('D1 Kebutuhan status PSN', $baris[$a->id]['kurang']);
        $this->assertSame('BELUM_CUKUP_DATA', $baris[$a->id]['rekomendasi']);
        $this->assertSame('DIKELUARKAN', $baris[$b->id]['rekomendasi']);

        // Operator K/L unit pengampu: tandai form diterima dan isi D1-D3; tidak dapat menetapkan indikasi.
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->get(route('evaluasi.show', $b))->assertNotFound(); // di luar cakupan unit
        $this->post(route('evaluasi.terima', $a), ['form_diterima_at' => '2026-11-10'])->assertRedirect(route('evaluasi.show', $a));
        $this->put(route('evaluasi.update', $a), ['bagian_input' => 1, 'form_diterima_at' => '2026-11-10', 'a6_tahun_penetapan' => 2020, 'd1_kebutuhan' => 'MASIH',
            'd2_fasilitas' => ['LMAN', 'HUKUM', 'LMAN'], 'd3_tahapan' => 'BEROPERASI_SEBAGIAN', 'd3_target' => 'Progres 80%', 'd3_tahun_selesai' => 2029,
            'indikasi_status' => 'DIPERTAHANKAN', 'hasil_pengecekan' => 'LENGKAP_BENAR'])->assertRedirect();
        $e = EvaluasiPsn::where('psn_id', $a->id)->firstOrFail();
        $this->assertSame(['LMAN', 'HUKUM'], $e->d2_fasilitas);
        $this->assertNull($e->indikasi_status);
        $this->assertNull($e->hasil_pengecekan);
        $this->assertSame('PROSES_PENGECEKAN', $e->status_akhir);

        $h = $layanan->daftar()->firstWhere('psn_id', $a->id);
        $this->assertTrue($h['lengkap'], implode('; ', $h['kurang']));
        $this->assertSame('PERLU_PENDALAMAN', $h['rekomendasi']);
        $this->assertSame(2, $h['indikator']['mundur_tahun']);
        $this->assertContains('Progres fisik tertinggal 15 poin dari rencana', $h['sinyal']);
        $this->assertContains('1 isu terbuka menghambat jalur kritis', $h['sinyal']);

        // Direktorat Sektor: hasil pengecekan -> tindak lanjut otomatis.
        $this->actingAs($this->buatPengguna('Direktorat Sektor', '07'));
        $this->put(route('evaluasi.update', $a), ['hasil_pengecekan' => 'LENGKAP_ADA_SALAH', 'tindak_lanjut' => '', 'catatan_analisis' => 'Verifikasi tahun selesai'])->assertRedirect();
        $this->assertSame(['VERIFIKASI_PJ', 'VERIFIKASI_PJ'], [$e->fresh()->tindak_lanjut, $e->fresh()->status_akhir]);
        $this->assertSame(['LMAN', 'HUKUM'], $e->fresh()->d2_fasilitas); // isian D tidak terhapus oleh pembaruan sebagian

        // PMO: indikasi tanpa catatan ditolak; dengan catatan menjadi status akhir.
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->put(route('evaluasi.update', $a), ['catatan_analisis' => '', 'indikasi_status' => 'DIPERTAHANKAN'])->assertSessionHasErrors('indikasi_status');
        $this->put(route('evaluasi.update', $a), ['catatan_analisis' => 'Lahan butuh LMAN', 'indikasi_status' => 'DIPERTAHANKAN', 'keterangan_riwayat' => 'Rapat 14/11'])->assertRedirect();
        $this->assertSame('DIPERTAHANKAN', $e->fresh()->status_akhir);
        $this->assertSame(['PROSES_PENGECEKAN', 'VERIFIKASI_PJ', 'DIPERTAHANKAN'], $e->riwayat()->get()->sortBy('id')->pluck('status_akhir')->values()->all());
        $this->assertTrue(DB::table('audit_log')->where('tabel', 'evaluasi_psn')->where('aksi', 'UPDATE')->exists());

        // Halaman pemantauan & ekspor.
        $html = $this->get(route('evaluasi.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Alur Keberlanjutan PSN menuju RKP 2027', $html);
        $this->assertStringContainsString('Bendungan Evaluasi A', $html);
        $this->get(route('evaluasi.index', ['status' => 'DIPERTAHANKAN']))->assertSee('Bendungan Evaluasi A')->assertDontSee('Jalan Tol Evaluasi B');
        $this->get(route('evaluasi.ekspor'))->assertOk()->assertHeader('content-disposition');
        $alur = $layanan->alur($layanan->daftar());
        $this->assertSame([2, 1, 1, 1], [$alur['total'], $alur['diterima'], $alur['status']['DIPERTAHANKAN'], $alur['status']['BELUM_DITERIMA']]);
        $this->assertSame(2, $alur['proyeksi']);
    }

    public function test_d1_tidak_menjadikan_rekomendasi_dikeluarkan_dan_validasi_alasan(): void
    {
        [$a] = $this->siapkan();
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->put(route('evaluasi.update', $a), ['form_diterima_at' => '2026-11-10'])->assertStatus(422); // belum diterima
        $this->post(route('evaluasi.terima', $a));
        $this->put(route('evaluasi.update', $a), ['form_diterima_at' => '2026-11-10', 'd1_kebutuhan' => 'TIDAK'])->assertSessionHasErrors('d1_alasan');
        $this->put(route('evaluasi.update', $a), ['form_diterima_at' => '2026-11-10', 'd1_kebutuhan' => 'TIDAK', 'd1_alasan' => 'TANPA_FASILITAS'])->assertRedirect();
        $h = app(EvaluasiStatusService::class)->daftar()->firstWhere('psn_id', $a->id);
        $this->assertSame('DIKELUARKAN', $h['rekomendasi']);
        $this->assertStringContainsString('tidak lagi membutuhkan status PSN', $h['sinyal'][0]);
    }

    public function test_grafik_harian_memakai_status_terakhir_per_hari(): void
    {
        [$a, $b] = $this->siapkan();
        $layanan = app(EvaluasiStatusService::class);
        $this->travelTo('2026-11-10');
        $e = $layanan->simpan(new EvaluasiPsn(['tahun_rkp' => 2027, 'psn_id' => $a->id]), ['form_diterima_at' => '2026-11-10']);
        $this->travelTo('2026-11-12');
        $layanan->simpan($e, ['hasil_pengecekan' => 'BELUM_LENGKAP']);
        $this->travelTo('2026-11-12 15:00');
        $layanan->simpan(new EvaluasiPsn(['tahun_rkp' => 2027, 'psn_id' => $b->id]), ['form_diterima_at' => '2026-11-12']);

        $hari = collect($layanan->harian($layanan->daftar(), CarbonImmutable::parse('2026-11-13')))->keyBy('tanggal');
        $this->assertSame(14, $hari->count());
        $this->assertSame(0, array_sum($hari['2026-11-09']['status']));
        $this->assertSame(1, $hari['2026-11-11']['status']['PROSES_PENGECEKAN']);
        $this->assertSame([1, 1], [$hari['2026-11-13']['status']['PROSES_PENGECEKAN'], $hari['2026-11-13']['status']['BUTUH_DATA']]);
    }

    public function test_hak_akses_menu(): void
    {
        [$a] = $this->siapkan();
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get(route('evaluasi.index'))->assertOk();
        $this->post(route('evaluasi.terima', $a))->assertForbidden();
    }
}
