<?php

namespace Tests\Feature;

use App\Models\Penilaian;
use App\Models\UsulanPsi;
use App\Models\UsulanPsn;
use App\Services\SifSinkronService;
use App\Support\Integrasi\SifKripto;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuatDataPsn;
use Tests\Support\SifTiruan;
use Tests\TestCase;

/**
 * Integrasi SIRENDA (PMO) dengan API SIF Dit. PSI: SIRENDA mengambil usulan (GET daftar & detail,
 * terenkripsi RSA-OAEP + AES-256-GCM) dan memasukkannya ke menu Perencanaan.
 */
class SifIntegrasiTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase, SifTiruan;

    protected array $detail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $this->siapkanSif();
        $this->detail = json_decode(file_get_contents(base_path('docs/contoh/sif-usulan-detail.json')), true);
    }

    protected function ruteDasar(): array
    {
        return [
            'GET /' => fn ($b, $q) => [200, [['kode_usulan' => 'USL-20261012-0001', 'status' => 'submitted', 'sudah_dinilai' => false]], ['current_page' => 1, 'last_page' => 1]],
            'GET /USL-20261012-0001' => fn () => [200, $this->detail],
        ];
    }

    public function test_kripto_oaep_sha256_dan_aes_gcm_bolak_balik(): void
    {
        $k = SifKripto::kunciSesi();
        $this->assertSame($k, SifKripto::bukaKunci(SifKripto::bungkusKunci($k, file_get_contents(config('psn_dashboard.psi.sif.kunci_publik'))), $this->sifPrivat));
        $this->assertSame('{"a":"Usulan ✓"}', SifKripto::dekripsi(SifKripto::enkripsi('{"a":"Usulan ✓"}', $k), $k));
        $rusak = base64_encode(substr_replace(base64_decode(SifKripto::enkripsi('rahasia', $k)), 'X', 14, 1));
        $this->expectException(\RuntimeException::class);
        SifKripto::dekripsi($rusak, $k);
    }

    public function test_sinkron_memetakan_usulan_sif_ke_perencanaan(): void
    {
        $this->fakeSif($this->ruteDasar());
        $h = app(SifSinkronService::class)->sinkron();
        $this->assertSame(['baru' => 1, 'diperbarui' => 0, 'tetap' => 0, 'gagal' => 0], array_intersect_key($h, array_flip(['baru', 'diperbarui', 'tetap', 'gagal'])));

        // Header & enkripsi sesuai spesifikasi.
        $this->assertSame(['GET /', 'GET /USL-20261012-0001'], array_map(fn ($x) => "{$x['metode']} {$x['path']}", $this->sifDiterima));
        $this->assertSame('konsultan-psn-uji', $this->sifDiterima[0]['client']);

        $psi = UsulanPsi::where('psi_id', 'USL-20261012-0001')->firstOrFail();
        $u = UsulanPsn::withoutGlobalScopes()->findOrFail($psi->usulan_id);
        $this->assertSame('CONTOH SIF Jalur Kereta Api Ganda Lintas Barat', $u->nama);
        $this->assertSame(2027, (int) $u->tahun_rkp);
        $this->assertSame('KL', $u->jenis_pengusul);
        $this->assertTrue((bool) $u->is_infrastruktur);
        $this->assertSame('R.7', DB::table('ref_klaster')->where('id', $u->klaster_id)->value('kode'));
        $this->assertEquals(12_500_000_000_000, $u->nilai_investasi_rp);
        $this->assertEqualsCanonicalizing(['31', '32'], $u->lokasi()->pluck('provinsi_kode')->all());
        $this->assertSame(1, $psi->versi);
        $this->assertSame('UM.001/1/2/KEMENHUB-2026', $psi->surat->nomor);

        // Jalur kritis: pendahulu yang tidak ada (99) dibuang + peringatan; strategi masuk penjelasan.
        $jk = $u->jalurKritis()->orderBy('urutan')->get();
        $this->assertSame(['1', '2', '3'], $jk->pluck('kode')->all());
        $this->assertSame(['2'], $jk[2]->sebelumnya);
        $this->assertStringContainsString('Konsultan independen', $psi->penjelasan_jalur_kritis_html);
        $this->assertContains('Langkah jalur kritis 3 merujuk kegiatan sebelumnya yang tidak ada.', $psi->peringatan_impor);
        $this->assertSame(['Kriteria Utama (isian pengusul)', 'Kriteria Pendukung (isian pengusul)', 'Kriteria Kesiapan (isian pengusul)', 'Rincian Tambahan (CAPEX/OPEX/Pendapatan)'],
            array_column($psi->rincian_lain, 'judul'));
        $this->assertSame(['Struktur Kelembagaan', 'Manajemen Risiko', 'Dokumen Lampiran Kesiapan'], array_column($psi->kesiapan_lain, 'judul'));

        // Dokumen: PDF usulan + lampiran studi kelayakan.
        $this->assertSame(['PDF_USULAN', 'LAMPIRAN'], $u->dokumen()->orderBy('id')->pluck('jenis')->all());
    }

    public function test_sinkron_idempoten_dan_versi_naik_bila_isi_berubah(): void
    {
        $this->fakeSif($this->ruteDasar());
        $sinkron = app(SifSinkronService::class);
        $sinkron->sinkron();
        $this->assertSame(1, $sinkron->sinkron()['tetap']);

        // Status SIF berubah (under_review) bukan perubahan isi.
        $this->detail['status'] = 'under_review';
        $this->assertSame(1, $sinkron->sinkron()['tetap']);

        // Penilaian FINAL lalu pengusul mengubah isi -> versi 2 & perlu tinjau ulang.
        $psi = UsulanPsi::firstOrFail();
        Penilaian::create(['usulan_id' => $psi->usulan_id, 'forum' => 'Uji', 'tanggal' => '2026-10-20', 'status' => 'FINAL']);
        $this->detail['data_umum']['nama_usulan_psn'] = 'CONTOH SIF Jalur Kereta Api (revisi)';
        $this->assertSame(1, $sinkron->sinkron()['diperbarui']);
        $psi->refresh();
        $this->assertSame(2, $psi->versi);
        $this->assertTrue($psi->perlu_tinjau_ulang);
    }

    public function test_satu_detail_gagal_tidak_menghentikan_sinkron_dan_tercatat(): void
    {
        $this->fakeSif([
            'GET /' => fn () => [200, [['kode_usulan' => 'USL-20261012-0001'], ['kode_usulan' => 'USL-20261012-0002']], ['current_page' => 1, 'last_page' => 1]],
            'GET /USL-20261012-0001' => fn () => [200, $this->detail],
            'GET /USL-20261012-0002' => fn () => [404, 'Usulan tidak ditemukan'],
        ]);
        $h = app(SifSinkronService::class)->sinkron();
        $this->assertSame([1, 1], [$h['baru'], $h['gagal']]);
        $this->assertStringContainsString('HTTP 404', $h['galat'][0]);
        $this->assertTrue(DB::table('integrasi_psi_log')->where('aksi', 'AMBIL_USULAN')->where('psi_id', 'USL-20261012-0002')->where('berhasil', false)->exists());
    }

    public function test_tombol_ambil_usulan_dan_unduh_berkas_lewat_sirenda(): void
    {
        $rute = $this->ruteDasar() + ['GET /USL-20261012-0001/attachments/1' => fn () => Http::response('%PDF-1.7 isi', 200, ['Content-Type' => 'application/pdf'])];
        $this->fakeSif($rute);

        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->post(route('perencanaan.sinkron-psi'))->assertForbidden();

        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->post(route('perencanaan.sinkron-psi'))->assertSessionHasNoErrors()->assertSessionHas('status', 'Usulan dari aplikasi PSI: 1 baru, 0 diperbarui, 0 tidak berubah.');
        $u = UsulanPsn::withoutGlobalScopes()->where('nama', 'like', 'CONTOH SIF%')->firstOrFail();
        $dok = $u->dokumen()->where('jenis', 'LAMPIRAN')->firstOrFail();
        $this->get("/perencanaan/{$u->id}?tab=dokumen")->assertOk()->assertSee(route('perencanaan.dokumen', [$u, $dok]));
        $this->get(route('perencanaan.dokumen', [$u, $dok]))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-1.7 isi');
        $this->assertSame('/USL-20261012-0001/attachments/1', end($this->sifDiterima)['path']);

        // Operator di luar cakupan tidak dapat mengunduh.
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->get(route('perencanaan.dokumen', [$u, $dok]))->assertNotFound();
    }

    public function test_belum_dikonfigurasi_memberi_pesan_jelas(): void
    {
        config(['psn_dashboard.psi.sif.api_key' => null]);
        $this->artisan('psi:sinkron')->expectsOutputToContain('SIF_API_KEY')->assertFailed();
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->post(route('perencanaan.sinkron-psi'))->assertSessionHasErrors('sinkron');
    }
}
