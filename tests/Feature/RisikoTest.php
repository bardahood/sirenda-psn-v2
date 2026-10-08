<?php

namespace Tests\Feature;

use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class RisikoTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected array $id = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $k1 = DB::table('ref_kode')->insertGetId(['tipe' => 'RISK', 'kode' => 'UJ1', 'nama' => 'Risiko Lahan', 'urutan' => 1]);
        $k2 = DB::table('ref_kode')->insertGetId(['tipe' => 'RISK', 'kode' => 'UJ2', 'nama' => 'Risiko Pendanaan', 'urutan' => 2]);

        $a = $this->buatPsn(['nama' => 'Alfa'], ['07']);
        $b = $this->buatPsn(['nama' => 'Beta'], ['19']);
        $r = fn (array $x) => DB::table('risiko')->insertGetId($x + ['uraian' => 'Risiko uji']);
        $this->id = [
            'r1' => $r(['psn_id' => $a->id, 'uraian' => 'Pembebasan lahan', 'kategori_id' => $k1, 'kemungkinan_harapan' => 4, 'dampak_harapan' => 5]),
            'r2' => $r(['psn_id' => $a->id, 'kemungkinan_harapan' => 2, 'dampak_harapan' => 2]),
            'r3' => $r(['psn_id' => $a->id, 'level_harapan' => 'Tinggi']), // data lama: label saja
            'r4' => $r(['psn_id' => $b->id, 'kategori_id' => $k2, 'kemungkinan_harapan' => 4, 'dampak_harapan' => 5]),
            'k1' => $k1,
        ];
        DB::table('risiko_pemantauan')->insert(['risiko_id' => $this->id['r1'], 'tanggal' => '2026-09-15', 'tahun' => 2026, 'kemungkinan_aktual' => 2, 'dampak_aktual' => 3]);

        $kemarin = now()->subDay()->toDateString();
        DB::table('isu')->insert([
            ['psn_id' => $a->id, 'uraian' => 'Isu A mendatang', 'tenggat' => now()->addMonth()->toDateString(), 'status' => 'TERBUKA', 'pic_nama' => 'PIC A'],
            ['psn_id' => $a->id, 'uraian' => 'Isu A terlambat', 'tenggat' => $kemarin, 'status' => 'PROSES', 'pic_nama' => null],
            ['psn_id' => $a->id, 'uraian' => 'Isu A selesai', 'tenggat' => $kemarin, 'status' => 'SELESAI', 'pic_nama' => 'PIC A'],
            ['psn_id' => $b->id, 'uraian' => 'Isu B terlambat', 'tenggat' => $kemarin, 'status' => 'TERBUKA', 'pic_nama' => 'PIC B'],
        ]);
        DB::table('regulasi')->insert([
            ['psn_id' => $a->id, 'nama' => 'Perpres A1', 'tahap' => 'PENYUSUNAN'],
            ['psn_id' => $a->id, 'nama' => 'Perpres A2', 'tahap' => 'PENYUSUNAN'],
            ['psn_id' => $b->id, 'nama' => 'PP B', 'tahap' => 'DITETAPKAN'],
        ]);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
    }

    protected function sel(array $hm): array
    {
        return collect($hm['sel'])->mapWithKeys(fn ($s) => ["{$s['kemungkinan']}-{$s['dampak']}" => $s['jumlah']])->sortKeys()->all();
    }

    public function test_heatmap_harapan_dan_aktual_serta_tanpa_skala(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $d = $this->getJson('/api/v1/risiko/ringkasan')->assertOk()->json('data');

        $this->assertSame(4, $d['total_risiko']);
        $this->assertSame(['2-2' => 1, '4-5' => 2], $this->sel($d['harapan']));
        $this->assertSame(3, $d['harapan']['berskala']);
        $this->assertSame(1, $d['harapan']['tanpa_skala']);
        $this->assertSame(['Rendah' => 1, 'Sedang' => 0, 'Tinggi' => 1, 'Sangat Tinggi' => 2], collect($d['harapan']['per_level'])->pluck('jumlah', 'level')->all());
        $this->assertSame(['2-3' => 1], $this->sel($d['aktual']));
        $this->assertSame(0, $d['aktual']['tanpa_skala']);

        $this->assertSame(['IDENTIFIKASI' => 0, 'PENYUSUNAN' => 2, 'HARMONISASI' => 0, 'DITETAPKAN' => 1], collect($d['regulasi'])->pluck('jumlah', 'tahap')->all());
        $this->assertSame(['terbuka' => 3, 'lewat_tenggat' => 2, 'tanpa_pic' => 1], $d['isu']);

        $k = $this->getJson("/api/v1/risiko/ringkasan?kategori={$this->id['k1']}")->json('data');
        $this->assertSame(1, $k['total_risiko']);
    }

    public function test_register_urut_skor_residual_dan_filter_sel(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $urut = collect($this->getJson('/api/v1/risiko/register')->assertOk()->json('data'));
        // r4 20 (harapan), r3 16 (label Tinggi), r1 6 (aktual menggantikan harapan 20), r2 4.
        $this->assertSame([$this->id['r4'], $this->id['r3'], $this->id['r1'], $this->id['r2']], $urut->pluck('risiko_id')->all());
        $this->assertSame([20, 16, 6, 4], $urut->pluck('skor_residual')->all());

        $sel = $this->getJson('/api/v1/risiko/register?jenis=harapan&kemungkinan=4&dampak=5')->json('data');
        $this->assertEqualsCanonicalizing([$this->id['r1'], $this->id['r4']], array_column($sel, 'risiko_id'));
        $this->assertSame([$this->id['r1']], array_column($this->getJson('/api/v1/risiko/register?jenis=aktual&kemungkinan=2&dampak=3')->json('data'), 'risiko_id'));
        $this->assertSame([$this->id['r3']], array_column($this->getJson('/api/v1/risiko/register?jenis=harapan&level=Tinggi')->json('data'), 'risiko_id'));
        $this->assertSame([$this->id['r1']], array_column($this->getJson('/api/v1/risiko/register?q=lahan')->json('data'), 'risiko_id'));

        $this->getJson('/api/v1/risiko/register?kemungkinan=6&dampak=1')->assertUnprocessable();
        $this->getJson('/api/v1/risiko/register?level=Ekstrem')->assertUnprocessable();
    }

    public function test_isu_lewat_tenggat_di_atas(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $isu = collect($this->getJson('/api/v1/risiko/isu')->assertOk()->json('data'));
        $this->assertSame([true, true, false], $isu->pluck('lewat_tenggat')->all());
        $this->assertSame('Isu A mendatang', $isu->last()['uraian']);

        $this->assertSame(2, $this->getJson('/api/v1/risiko/isu?lewat_tenggat=1')->json('meta.total'));
        $this->assertSame(4, $this->getJson('/api/v1/risiko/isu?termasuk_selesai=1')->json('meta.total'));
    }

    public function test_operator_hanya_melihat_risiko_dan_isu_unitnya(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '19'));
        $d = $this->getJson('/api/v1/risiko/ringkasan')->json('data');
        $this->assertSame(1, $d['total_risiko']);
        $this->assertSame(['terbuka' => 1, 'lewat_tenggat' => 1, 'tanpa_pic' => 0], $d['isu']);
        $this->assertSame([$this->id['r4']], array_column($this->getJson('/api/v1/risiko/register')->json('data'), 'risiko_id'));
        $this->assertSame(['Isu B terlambat'], array_column($this->getJson('/api/v1/risiko/isu')->json('data'), 'uraian'));
        $this->get('/risiko')->assertOk()->assertSee('Register Risiko');
    }
}
