<?php

namespace Tests\Feature;

use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/** Cetak Form Pengendalian PSN per proyek: terisi dari data SIRENDA, PDF, tautan ID lama, dan hak akses. */
class FormPengendalianTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    public function test_form_terisi_dari_data_psn_dan_dapat_diunduh_pdf(): void
    {
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $psn = $this->buatPsn(['nama' => 'Bendungan Uji Pengendalian', 'kode_psn' => 'SDA.1-2026.1-01', 'legacy_id' => 777, 'tahun_selesai' => 2028,
            'nilai_investasi_rp' => 2_500_000_000_000, 'dasar_penetapan' => 'Perpres 12/2025', 'output' => 'Tampungan 50 juta m3'], ['19']);
        DB::table('psn_lokasi')->insert(['psn_id' => $psn->id, 'provinsi_kode' => '32']);
        DB::table('psn_kelembagaan')->insert(['psn_id' => $psn->id, 'peran' => 'PENANGGUNG_JAWAB', 'nama_teks' => 'Kementerian PU (Uji)']);
        $cp = $this->buatKegiatan($psn, [['periode' => 'BULANAN', 'periode_ke' => 9, 'target_persen' => 60, 'realisasi_persen' => 45, 'pagu_rp' => 120e9, 'realisasi_anggaran_rp' => 80e9, 'dilaporkan_at' => '2026-09-20']], ['is_critical_path' => true]);
        DB::table('isu')->insert([
            ['psn_id' => $psn->id, 'kegiatan_id' => null, 'uraian' => 'Isu biasa', 'status' => 'TERBUKA', 'tenggat' => '2026-10-01', 'kebutuhan_dukungan' => null, 'pic_nama' => null, 'tindak_lanjut' => null],
            ['psn_id' => $psn->id, 'kegiatan_id' => $cp->id, 'uraian' => 'Lahan genangan belum bebas', 'status' => 'TERBUKA', 'tenggat' => '2026-12-01',
                'kebutuhan_dukungan' => 'Percepatan konsinyasi', 'pic_nama' => 'Kanwil BPN', 'tindak_lanjut' => 'Rapat koordinasi'],
            ['psn_id' => $psn->id, 'kegiatan_id' => null, 'uraian' => 'Isu sudah selesai', 'status' => 'SELESAI', 'tenggat' => null, 'kebutuhan_dukungan' => null, 'pic_nama' => null, 'tindak_lanjut' => null],
        ]);
        DB::table('regulasi')->insert([['psn_id' => $psn->id, 'nama' => 'Perpres Penlok', 'tahap' => 'DITETAPKAN'], ['psn_id' => $psn->id, 'nama' => 'Permen Juknis', 'tahap' => 'PENYUSUNAN']]);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
        app(SnapshotService::class)->buat('2026-10', terbit: true);

        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get(route('proyek.show', $psn))->assertOk()->assertSee(route('proyek.form-pengendalian', $psn));

        // Bawaan: cut-off akhir triwulan terakhir (September = TW III), bukan Oktober.
        $html = $this->get(route('proyek.form-pengendalian', $psn))->assertOk()->getContent();
        foreach (['Bendungan Uji Pengendalian', 'SDA.1-2026.1-01', 'JAWA BARAT', 'Kementerian PU (Uji)', 'Perpres 12/2025', '2028', '2.500.000.000.000',
            'Tampungan 50 juta m3', 's.d. TW III 2026', 'posisi 30 September 2026', 'Selasa, 17 November 2026', 'Perpres Penlok' => false, 'Permen Juknis'] as $k => $v) {
            is_string($k) ? $this->assertStringNotContainsString($k, $html) : $this->assertStringContainsString($v, $html);
        }
        $this->assertMatchesRegularExpression('/☒ Konstruksi/', $html);               // status PSN kode 3
        $this->assertMatchesRegularExpression('/>\s*60,00\s*<.*?>\s*45,00\s*</s', $html); // progres rencana/realisasi
        // C2: hanya isu terbuka, jalur kritis didahulukan dan ditandai "Ya".
        $this->assertLessThan(strpos($html, 'Isu biasa'), strpos($html, 'Lahan genangan belum bebas'));
        $this->assertStringNotContainsString('Isu sudah selesai', $html);
        $this->assertStringContainsString('Percepatan konsinyasi (PIC: Kanwil BPN)', $html);
        $this->assertStringContainsString('pagu Rp 120,00 miliar, realisasi Rp 80,00 miliar', $html);

        // Jenis fisik: hanya B2.a/B3.a.
        $fisik = $this->get(route('proyek.form-pengendalian', [$psn, 'jenis' => 'fisik']))->assertOk()->getContent();
        $this->assertStringContainsString('B2.a PSN Fisik', $fisik);
        $this->assertStringNotContainsString('B2.b PSN Non-Fisik', $fisik);

        $pdf = $this->get(route('proyek.form-pengendalian', [$psn, 'periode' => '2026-10', 'format' => 'pdf']))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringContainsString('form-pengendalian_sda-1-2026-1-01_2026-10.pdf', $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        // Tautan dari aplikasi lama (psn.id lama) diteruskan ke PSN v2.
        $this->get(route('form-pengendalian.lama', ['id' => 777, 'jenis' => 'fisik']))->assertRedirect(route('proyek.form-pengendalian', [$psn, 'jenis' => 'fisik']));
        $this->get(route('form-pengendalian.lama', 999))->assertNotFound();
        $this->get(route('proyek.form-pengendalian', [$psn, 'periode' => '2025-01']))->assertNotFound();
        $this->get(route('proyek.form-pengendalian', [$psn, 'jenis' => 'x']))->assertSessionHasErrors('jenis');

        // Operator unit lain tidak dapat mencetak PSN di luar cakupannya.
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->get(route('proyek.form-pengendalian', $psn))->assertNotFound();
        $this->get(route('form-pengendalian.lama', 777))->assertNotFound();
    }
}
