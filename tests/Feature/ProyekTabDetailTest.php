<?php

namespace Tests\Feature;

use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/** Tab Detail Proyek: Perencanaan, Risiko & Isu, Regulasi, Stakeholder (docs/rancangan-aplikasi.md, tabel tab). */
class ProyekTabDetailTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    public function test_empat_tab_detail_menampilkan_data_psn(): void
    {
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        // Kode referensi: pakai yang sudah diisi seeder, buat bila belum ada (nama dari seeder dipertahankan).
        $kode = function (string $tipe, string $k, string $nama) {
            DB::table('ref_kode')->insertOrIgnore(['tipe' => $tipe, 'kode' => $k, 'nama' => $nama]);

            return (int) DB::table('ref_kode')->where('tipe', $tipe)->where('kode', $k)->value('id');
        };
        $namaKode = fn (string $tipe, string $k) => DB::table('ref_kode')->where('tipe', $tipe)->where('kode', $k)->value('nama');
        $psn = $this->buatPsn(['nama' => 'Bendungan Detail', 'kode_psn' => 'R.4-UJI'], ['07']);
        $lain = $this->buatPsn(['nama' => 'PSN Lain'], ['07']);
        $cp = $this->buatKegiatan($psn, [], ['nama' => 'Pembebasan lahan', 'is_critical_path' => true]);

        // Perencanaan
        DB::table('psn_target_tahunan')->insert(['psn_id' => $psn->id, 'tahun' => 2027, 'uraian' => 'Bendungan mulai impounding']);
        $ind = DB::table('indikator')->insertGetId(['psn_id' => $psn->id, 'uraian' => 'Volume tampungan', 'satuan' => 'juta m3', 'baseline' => 0, 'target_akhir' => 120, 'capaian' => 30]);
        DB::table('indikator_target')->insert(['indikator_id' => $ind, 'tahun' => 2026, 'target' => 40, 'realisasi' => 30]);
        $tri = DB::table('trisula')->insertGetId(['psn_id' => $psn->id, 'kategori_id' => $kode('KTAC', 'B', 'Pertumbuhan Ekonomi'), 'indikator' => 'Lapangan kerja', 'satuan' => 'orang', 'target_akhir' => 500]);
        DB::table('trisula_target')->insert(['trisula_id' => $tri, 'tahun' => 2027, 'periode' => 'TAHUNAN', 'periode_ke' => 0, 'target' => 250]);
        DB::table('psn_sdgs')->insert(['psn_id' => $psn->id, 'sdgs_id' => $kode('SDGS', '6', 'Air Bersih dan Sanitasi Layak')]);
        DB::table('psn_dukungan')->insert(['psn_id' => $psn->id, 'uraian' => 'Percepatan konsinyasi lahan']);
        DB::table('psn_fasilitas')->insert(['psn_id' => $psn->id, 'tahun' => 2025, 'fasilitas_id' => $kode('FAST', 'H', 'Anggaran Pengadaan Tanah')]);
        $u = DB::table('usulan_psn')->insertGetId(['tahun_rkp' => 2026, 'nama' => 'Usulan Bendungan Detail', 'psn_id' => $psn->id, 'status' => 'DITETAPKAN']);

        // Risiko & isu
        $r1 = DB::table('risiko')->insertGetId(['psn_id' => $psn->id, 'kegiatan_id' => $cp->id, 'uraian' => 'Penolakan warga', 'kategori_id' => $kode('RISK', 'A', 'Sosial'),
            'level_awal' => 'Sangat Tinggi', 'level_harapan' => 'Sedang', 'rencana_perlakuan' => 'Sosialisasi intensif', 'is_titik_kritis' => true]);
        $r2 = DB::table('risiko')->insertGetId(['psn_id' => $psn->id, 'uraian' => 'Banjir saat konstruksi', 'level_awal' => 'Sedang', 'level_harapan' => 'Rendah']);
        // Entri identik (data SIRENDA terduplikasi) digabung menjadi satu baris.
        DB::table('risiko')->insert(array_fill(0, 3, ['psn_id' => $psn->id, 'uraian' => 'Gagal panen', 'level_awal' => 'Sedang', 'level_harapan' => 'Sedang']));
        DB::table('risiko_perlakuan')->insert(['risiko_id' => $r1, 'uraian' => 'Fasilitasi dialog', 'instansi_id' => $this->instansi('010', 'KEMENTERIAN DALAM NEGERI')]);
        DB::table('risiko_tahun_perlakuan')->insert(['risiko_id' => $r1, 'tahun' => 2026]);
        DB::table('risiko_pemantauan')->insert(['risiko_id' => $r2, 'tanggal' => '2026-09-30', 'tahun' => 2026, 'triwulan' => 3, 'level_aktual' => 'Tinggi', 'progres_persen' => 50, 'status_perlakuan' => 'BERJALAN']);
        DB::table('isu')->insert([
            ['psn_id' => $psn->id, 'kegiatan_id' => $cp->id, 'uraian' => 'Lahan 12 bidang belum bebas', 'status' => 'TERBUKA', 'tenggat' => '2026-01-31', 'pic_nama' => 'BBWS'],
            ['psn_id' => $psn->id, 'kegiatan_id' => null, 'uraian' => 'Izin galian C', 'status' => 'SELESAI', 'tenggat' => null, 'pic_nama' => null],
            ['psn_id' => $lain->id, 'kegiatan_id' => null, 'uraian' => 'Isu PSN lain', 'status' => 'TERBUKA', 'tenggat' => null, 'pic_nama' => null],
        ]);

        // Regulasi & stakeholder
        $reg = DB::table('regulasi')->insertGetId(['psn_id' => $psn->id, 'nama' => 'Perpres Penetapan Lokasi', 'tahap' => 'HARMONISASI', 'justifikasi' => 'Dasar pengadaan lahan']);
        DB::table('regulasi_target_tahun')->insert(['regulasi_id' => $reg, 'tahun' => 2027]);
        $this->instansi('145', 'KEMENTERIAN PEKERJAAN UMUM');
        $pran = $kode('PRAN', 'B', 'Penanggung Jawab');
        DB::table('psn_stakeholder')->insert([
            ['psn_id' => $psn->id, 'jenis' => 'A', 'peran_id' => $pran, 'peran' => 'Pelaksanaan pembangunan', 'instansi_utama' => '145', 'instansi_pendukung' => '010'],
            ['psn_id' => $psn->id, 'jenis' => 'B', 'peran_id' => $pran, 'peran' => 'Pengelola bendungan', 'instansi_utama' => 'BBWS Pemali Juana', 'instansi_pendukung' => null],
            ['psn_id' => $psn->id, 'jenis' => 'A', 'peran_id' => $pran, 'peran' => 'Penyediaan lahan', 'instansi_utama' => '33', 'instansi_pendukung' => null],
        ]);
        app(SnapshotService::class)->buat('2026-10', terbit: true);

        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $tab = fn (string $t) => $this->get(route('proyek.show', $psn).'?tab='.$t)->assertOk();

        $tab('perencanaan')->assertSee('Bendungan mulai impounding')->assertSee('Volume tampungan')->assertSee('juta m3')->assertSee('25%')
            ->assertSee('Lapangan kerja')->assertSee($namaKode('KTAC', 'B'))->assertSee($namaKode('SDGS', '6'))->assertSee('Percepatan konsinyasi lahan')
            ->assertSee($namaKode('FAST', 'H'))->assertSee('Usulan Bendungan Detail')->assertSee(route('perencanaan.show', $u))->assertDontSee('disiapkan pada fase berikutnya');

        $tab('risiko')->assertSeeInOrder(['Banjir saat konstruksi', 'Penolakan warga'])   // aktual Tinggi di atas
            ->assertSee('Titik kritis')->assertSee('3 entri serupa')->assertSee('(5 risiko)', false)->assertSee('Fasilitasi dialog')->assertSee(DB::table('ref_instansi')->where('kode', '010')->value('nama'))->assertSee('Sedang berjalan')
            ->assertSee('Lahan 12 bidang belum bebas')->assertSee('Lewat tenggat')->assertSee('Menghambat jalur kritis')->assertDontSee('Isu PSN lain');

        $tab('regulasi')->assertSee('Perpres Penetapan Lokasi')->assertSee('Harmonisasi')->assertSee('Dasar pengadaan lahan')->assertSee('2027');

        $tab('stakeholder')->assertSee('Pemetaan Stakeholder')->assertSee('Kerangka Kelembagaan')->assertSee(DB::table('ref_instansi')->where('kode', '145')->value('nama'))
            ->assertSee(DB::table('ref_instansi')->where('kode', '010')->value('nama'))->assertSee('BBWS Pemali Juana')->assertSee('PEMERINTAH PROVINSI '.DB::table('ref_wilayah')->where('kode', '33')->value('nama'))->assertDontSee('>145<', false);

        // PSN tanpa data: semua tab tetap terbuka dengan keterangan kosong.
        foreach (['perencanaan', 'risiko', 'regulasi', 'stakeholder'] as $t) {
            $this->get(route('proyek.show', $lain).'?tab='.$t)->assertOk()->assertDontSee('disiapkan pada fase berikutnya');
        }
    }

    protected function instansi(string $kode, string $nama): int
    {
        DB::table('ref_instansi')->insertOrIgnore(['kode' => $kode, 'nama' => $nama]);

        return (int) DB::table('ref_instansi')->where('kode', $kode)->value('id');
    }
}
