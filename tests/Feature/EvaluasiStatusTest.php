<?php

namespace Tests\Feature;

use App\Models\EvaluasiPsn;
use App\Services\EvaluasiStatusService;
use App\Services\SnapshotService;
use App\Support\Evaluasi\FormatRingkas;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        $a = $this->buatPsn(['nama' => 'Bendungan Evaluasi A', 'kode_psn' => 'R.1', 'tahun_selesai' => 2027, 'sumber_input' => 1, 'nilai_investasi_rp' => 500e9], ['07']);
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
        $this->put(route('evaluasi.update', $a), ['bagian_input' => 1, 'c1_tipologi' => ['1'], 'form_diterima_at' => '2026-11-10', 'a6_tahun_penetapan' => 2020, 'd1_kebutuhan' => 'MASIH',
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

    public function test_kertas_kerja_rincian_ekspor_dan_grafik(): void
    {
        [$a, $b] = $this->siapkan();
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->post(route('evaluasi.terima', $a));

        // Isian rincian: capaian menimpa sebagian profil, milestone, isu (menggantikan isu profil), lampiran.
        $this->put(route('evaluasi.update', $a), ['bagian_input' => 1, 'bagian_rincian' => 1, 'jenis_psn' => 'FISIK', 'a6_tahun_penetapan' => 2021,
            'c1_tipologi' => ['1', '3'], 'c1_lainnya' => '', 'b4_manfaat' => 'Belum ada', 'info_tambahan' => 'Catatan PJ',
            'capaian' => ['PROGRES_FISIK' => ['target_kumulatif' => '50', 'keterangan' => 'Kurva S Sep 2026'], 'OUTPUT_UTAMA' => ['satuan' => 'km', 'target_akhir' => '64', 'target_kumulatif' => '40', 'realisasi_kumulatif' => '36.2'],
                'PERIZINAN' => ['target_akhir' => '', 'keterangan' => '']],
            'milestone' => [['kode' => 'FS', 'status' => 'SELESAI', 'tanggal' => '2023-06-30', 'keterangan' => 'FS disetujui'], ['kode' => 'KKPR', 'status' => '', 'tanggal' => '', 'keterangan' => ''],
                ['kode' => 'LAIN', 'nama_lain' => 'Izin pinjam pakai', 'status' => 'PROSES']],
            'isu' => [['uraian' => '62 bidang belum bebas', 'tipologi' => '1', 'jalur_kritis' => '1', 'upaya' => 'Konsinyasi', 'dukungan' => 'Percepatan putusan PN'],
                ['uraian' => '', 'tipologi' => '', 'jalur_kritis' => '', 'upaya' => '', 'dukungan' => '']],
            'lampiran' => [['jenis' => 'DOKUMEN', 'judul' => 'Kurva S', 'bentuk' => 'FILE', 'tautan' => 'kurva.xlsx'], ['jenis' => '', 'judul' => '', 'bentuk' => '', 'tautan' => '']],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $e = EvaluasiPsn::where('psn_id', $a->id)->firstOrFail();
        $this->assertSame(['OUTPUT_UTAMA', 'PROGRES_FISIK'], $e->capaian()->orderBy('komponen')->pluck('komponen')->all());
        $this->assertSame(['FS', 'LAIN'], $e->milestone()->pluck('kode')->all());
        $this->assertSame(1, $e->isu()->count());
        $this->assertSame(1, $e->lampiran()->count());

        $layanan = app(EvaluasiStatusService::class);
        $h = $layanan->satu($a);
        $this->assertSame(50.0, $h['capaian']['PROGRES_FISIK']['target_kumulatif']);          // isian
        $this->assertSame(45.0, $h['capaian']['PROGRES_FISIK']['realisasi_kumulatif']);       // profil
        $this->assertSame(['target_akhir', 'realisasi_kumulatif'], array_values(array_diff($h['capaian']['PROGRES_FISIK']['dari_profil'], ['satuan'])));
        $this->assertSame(-5.0, $h['indikator']['deviasi_fisik']);
        $this->assertSame(['62 bidang belum bebas'], array_column($h['isu'], 'uraian'));

        // Isian sebagian berikutnya: capaian dikosongkan seluruhnya kembali ke profil; baris isu bertambah memakai posisi.
        $idIsu = $e->isu()->value('id');
        $this->put(route('evaluasi.update', $a), ['bagian_input' => 1, 'bagian_rincian' => 1, 'c1_tipologi' => ['1'],
            'capaian' => ['PROGRES_FISIK' => ['target_kumulatif' => '', 'keterangan' => '']],
            'isu' => [['uraian' => '62 bidang belum bebas (diperbarui)', 'dukungan' => 'PN'], ['uraian' => 'Izin hutan', 'dukungan' => 'Kemenhut']]])->assertRedirect();
        $this->assertSame(['OUTPUT_UTAMA'], $e->capaian()->pluck('komponen')->all());
        $this->assertSame($idIsu, $e->isu()->value('id'));
        $this->assertSame(2, $e->isu()->count());

        $this->get(route('evaluasi.show', $a))->assertOk()->assertSee('B dan C. Capaian')->assertSee('Izin pinjam pakai', false)->assertSee('62 bidang belum bebas (diperbarui)');
        $this->get(route('evaluasi.show', $b))->assertOk()->assertSee('Tandai form diterima');

        // Kertas kerja (Format Ringkas) dan ekspor Excel 92 kolom dengan header bertingkat.
        $html = $this->get(route('evaluasi.kertas-kerja'))->assertOk()->getContent();
        foreach (['Bendungan Evaluasi A', 'B2.a Fisik – Output utama', 'Izin pinjam pakai', '62 bidang belum bebas (diperbarui)', 'Kertas Kerja (Format Ringkas)'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        $this->get(route('evaluasi.kertas-kerja', ['status' => 'BELUM_DITERIMA']))->assertSee('Jalan Tol Evaluasi B')->assertDontSee('Bendungan Evaluasi A');
        $xlsx = $this->get(route('evaluasi.kertas-kerja-xlsx'))->assertOk();
        $berkas = tempnam(sys_get_temp_dir(), 'kk').'.xlsx';
        copy($xlsx->baseResponse->getFile()->getPathname(), $berkas);
        $berkasSalin = $berkas.'.salin.xlsx';
        copy($berkas, $berkasSalin);
        $ws = IOFactory::load($berkas)->getActiveSheet();
        @unlink($berkas);
        $this->assertSame('CN', $ws->getHighestColumn());
        $this->assertSame('Kode PSN', $ws->getCell('B4')->getValue());
        $this->assertSame('B2.a Fisik – Progres fisik', $ws->getCell('P3')->getValue());
        $this->assertContains('P3:S3', array_keys($ws->getMergeCells()));
        $baris = collect(range(5, 6))->first(fn ($r) => $ws->getCell("C{$r}")->getValue() === 'Bendungan Evaluasi A');
        $this->assertSame('Proses Pengecekan', $ws->getCell("CN{$baris}")->getValue());
        $this->assertSame('km', $ws->getCell("T{$baris}")->getValue());
        $this->assertSame(45, (int) $ws->getCell("R{$baris}")->getValue()); // realisasi dari profil
        $this->assertStringContainsString('1. Studi kelayakan', (string) $ws->getCell("BD{$baris}")->getValue());
        // Daftar Pilihan ikut diekspor dan dipakai sebagai dropdown (sama dengan kertas kerja).
        $buku = IOFactory::load($berkasSalin);
        @unlink($berkasSalin);
        $dp = $buku->getSheetByName('Daftar Pilihan');
        $this->assertSame(['APBN', 'APBD', 'BUMN', 'Badan Usaha-Swasta', 'Danantara', 'Lainnya', 'Campuran'], array_map(fn ($r) => $dp->getCell("C{$r}")->getValue(), range(4, 10)));
        $this->assertSame('1. Perizinan usaha', $dp->getCell('R4')->getValue());
        $dv = $buku->getSheet(0)->getDataValidation('J5');
        $this->assertSame("'Daftar Pilihan'!\$C\$4:\$C\$10", $dv->getFormula1());
        $this->assertSame(['2000', '2100'], [$buku->getSheet(0)->getDataValidation('H5')->getFormula1(), $buku->getSheet(0)->getDataValidation('H5')->getFormula2()]);

        // Grafik status harian: tabel 14 hari, posisi saat ini cocok dengan riwayat.
        $this->get(route('evaluasi.grafik'))->assertOk()->assertSee('Tabel Status Harian')->assertSee('Cocok');
        $this->get(route('evaluasi.grafik', ['mulai' => 'salah']))->assertSessionHasErrors('mulai');
    }

    public function test_isian_langsung_sel_kertas_kerja(): void
    {
        [$a, $b] = $this->siapkan();
        $sel = fn ($psn, array $isi) => $this->patchJson(route('evaluasi.sel', $psn), $isi);

        // Operator K/L: isian pertama sekaligus menandai form diterima; kolom verifikasi/keputusan ditolak.
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $r = $sel($a, ['kunci' => 'a6', 'nilai' => '2020', 'no' => 3])->assertOk();
        $this->assertStringContainsString('data-no="3"', $r->json('html'));
        $e = EvaluasiPsn::where('psn_id', $a->id)->firstOrFail();
        $this->assertSame([2020, 'PROSES_PENGECEKAN'], [$e->a6_tahun_penetapan, $e->status_akhir]);
        $this->assertSame('Form diterima (isian kertas kerja)', $e->riwayat()->value('keterangan'));
        $sel($a, ['kunci' => 'hasil', 'nilai' => 'LENGKAP_BENAR'])->assertForbidden();
        $sel($a, ['kunci' => 'a1', 'nilai' => 'Ubah nama'])->assertStatus(422);            // kolom profil
        $sel($a, ['kunci' => 'c2_uraian', 'nilai' => 'x'])->assertStatus(422);             // diisi lewat formulir
        $sel($b, ['kunci' => 'a6', 'nilai' => '2020'])->assertNotFound();                   // di luar cakupan unit
        $sel($a, ['kunci' => 'a6', 'nilai' => '1990'])->assertStatus(422)->assertJsonValidationErrors('nilai');

        // Pilihan majemuk dan capaian B2 (menimpa profil; kosong = kembali ke profil; jenis harus sesuai).
        $sel($a, ['kunci' => 'c1', 'nilai' => ['1', '3'], 'nilai_lain' => 'Cuaca'])->assertOk();
        $sel($a, ['kunci' => 'd2', 'nilai' => ['LMAN', 'XX']])->assertStatus(422);
        $sel($a, ['kunci' => 'd2', 'nilai' => ['LMAN']])->assertOk();
        $sel($a, ['kunci' => 'pic_nama', 'nilai' => 'Ir. Uji'])->assertOk();
        $sel($a, ['kunci' => 'a8', 'nilai' => 'Badan Usaha-Swasta'])->assertStatus(422);        // kode, bukan label
        $this->assertStringContainsString('Danantara', $sel($a, ['kunci' => 'a8', 'nilai' => 'DANANTARA'])->assertOk()->json('html'));
        $sel($a, ['kunci' => 'fisik_progres_fisik_realisasi_kumulatif', 'nilai' => '50'])->assertOk()->assertJsonPath('message', fn ($m) => str_contains($m, 'Tersimpan'));
        $sel($a, ['kunci' => 'non_fisik_output_utama_satuan', 'nilai' => 'unit'])->assertStatus(422);
        $e->refresh();
        $this->assertSame([['1', '3'], 'Cuaca', ['LMAN'], ['nama' => 'Ir. Uji']], [$e->c1_tipologi, $e->c1_lainnya, $e->d2_fasilitas, $e->pic]);
        $h = app(EvaluasiStatusService::class)->satu($a);
        $this->assertSame([60.0, 50.0], [$h['capaian']['PROGRES_FISIK']['target_kumulatif'], $h['capaian']['PROGRES_FISIK']['realisasi_kumulatif']]);
        $sel($a, ['kunci' => 'fisik_progres_fisik_realisasi_kumulatif', 'nilai' => ''])->assertOk();
        $this->assertSame(0, $e->capaian()->count());

        // Direktorat Sektor: hasil pengecekan; PMO: indikasi butuh catatan.
        $this->actingAs($this->buatPengguna('Direktorat Sektor', '07'));
        $sel($a, ['kunci' => 'hasil', 'nilai' => 'BELUM_LENGKAP'])->assertOk()->assertJsonPath('message', 'Tersimpan. Status akhir: Butuh Data Tambahan.');
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $sel($a, ['kunci' => 'indikasi', 'nilai' => 'DIPERTAHANKAN'])->assertStatus(422);
        $sel($a, ['kunci' => 'catatan', 'nilai' => 'Lahan kritis'])->assertOk();
        $html = $sel($a, ['kunci' => 'indikasi', 'nilai' => 'DIPERTAHANKAN'])->assertOk()->json('html');
        $this->assertStringContainsString('badge-EV_DIPERTAHANKAN', $html);
        $this->assertSame(['PROSES_PENGECEKAN', 'BUTUH_DATA', 'DIPERTAHANKAN'], $e->riwayat()->get()->sortBy('id')->pluck('status_akhir')->values()->all());

        // Halaman kertas kerja memuat penanda sel yang dapat diisi; Pimpinan hanya melihat.
        $this->get(route('evaluasi.kertas-kerja'))->assertOk()->assertSee('data-edit="1"', false);
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get(route('evaluasi.kertas-kerja'))->assertOk()->assertDontSee('data-edit="1"', false);
        $sel($a, ['kunci' => 'a6', 'nilai' => '2020'])->assertForbidden();
    }

    public function test_a8_sumber_pendanaan_mengikuti_daftar_pilihan(): void
    {
        [$a] = $this->siapkan();
        $f = new FormatRingkas;
        $this->assertNull($f->sumberPendanaan([]));
        $this->assertSame('BADAN_USAHA', $f->sumberPendanaan(['D']));
        $this->assertSame('DANANTARA', $f->sumberPendanaan(['F', 'G']));   // DDMF bagian dari Danantara
        $this->assertSame('CAMPURAN', $f->sumberPendanaan(['A', 'D']));
        $this->assertSame('LAINNYA', $f->sumberPendanaan(['Z']));

        DB::table('psn_sumber_dana')->insert([['psn_id' => $a->id, 'sumber_dana_id' => $this->ref('ref_sumber_dana', 'A')], ['psn_id' => $a->id, 'sumber_dana_id' => $this->ref('ref_sumber_dana', 'D')]]);
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->get(route('evaluasi.kertas-kerja'))->assertSee('Campuran');
        $this->post(route('evaluasi.terima', $a));
        $this->get(route('evaluasi.show', $a))->assertSee('Dari profil: Campuran');
        $this->put(route('evaluasi.update', $a), ['bagian_input' => 1, 'a8_sumber_pendanaan' => 'APBN', 'a6_tahun_penetapan' => 2100])->assertSessionHasNoErrors();
        $this->put(route('evaluasi.update', $a), ['a6_tahun_penetapan' => 2101])->assertSessionHasErrors('a6_tahun_penetapan');
        $b = $f->semua(app(EvaluasiStatusService::class)->daftar([$a->id]))->first();
        $this->assertSame(['APBN', 'ISIAN', 'APBN'], [$b['nilai']['a8'], $b['asal']['a8'], $b['mentah']['a8']]);
    }

    /** Kelas lebar kolom dibentuk di PHP: pastikan ikut ter-build (regresi kolom kertas kerja berantakan di hosting). */
    public function test_kelas_lebar_kertas_kerja_ada_di_css_build(): void
    {
        $manifest = public_path('build/manifest.json');
        if (! is_file($manifest)) {
            $this->markTestSkipped('Aset belum di-build (npm run build).');
        }
        $css = file_get_contents(public_path('build/'.json_decode(file_get_contents($manifest), true)['resources/css/app.css']['file']));
        $f = new FormatRingkas;
        $kelas = collect($f->kolom())->flatMap(fn ($k) => explode(' ', $f::lebar($k['kunci'])))->unique()->values();
        foreach ($kelas as $k) {
            $this->assertStringContainsString('.'.addcslashes($k, '[].'), $css, "Kelas {$k} tidak ada di CSS build");
        }
    }
}
