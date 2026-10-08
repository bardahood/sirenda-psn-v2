<?php

namespace Tests\Feature;

use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SkemaReferensiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
    }

    public function test_referensi_terisi_dari_master_lama(): void
    {
        $this->assertSame(38, DB::table('ref_wilayah')->where('level', 1)->count());
        $this->assertSame(1, DB::table('ref_wilayah')->where('level', 0)->count(), 'kode 00 Nasional');
        $this->assertSame('id-ac', DB::table('ref_wilayah')->where('kode', '11')->value('hc_key'));
        $this->assertSame('11', DB::table('ref_wilayah')->where('kode', '11.01')->value('induk_kode'));
        $this->assertSame(18, DB::table('ref_klaster')->count());
        $this->assertSame(327, DB::table('ref_unit_kerja')->count());
    }

    public function test_status_keluar_psn_tidak_aktif_dan_tahap_terpetakan(): void
    {
        $this->assertFalse((bool) DB::table('ref_status_psn')->where('kode', '6')->value('is_aktif'));
        $this->assertSame('PERENCANAAN', DB::table('ref_status_psn')->where('kode', '5')->value('tahap'));
        $this->assertSame('OPERASI', DB::table('ref_status_psn')->where('kode', '1')->value('tahap'));
    }

    public function test_kriteria_penilaian_3_utama_6_pendukung_5_kesiapan(): void
    {
        $per = DB::table('ref_kriteria')->groupBy('kelompok')->pluck(DB::raw('count(*)'), 'kelompok');
        $this->assertEquals(['UTAMA' => 3, 'PENDUKUNG' => 6, 'KESIAPAN' => 5], $per->map(fn ($n) => (int) $n)->all());
        $this->assertSame('YA_TIDAK', DB::table('ref_kriteria')->where('kode', 'KU1')->value('tipe_nilai'));
        $this->assertSame('PENGUSUL_PEMDA', DB::table('ref_kriteria')->where('kode', 'KP5')->value('kondisional'));
    }

    public function test_peran_rbac_sesuai_matriks(): void
    {
        $this->assertTrue(Role::findByName('Pimpinan')->hasPermissionTo('ringkasan.lihat'));
        $this->assertFalse(Role::findByName('Pimpinan')->hasPermissionTo('pengaturan.lihat'));
        $this->assertTrue(Role::findByName('Tim Koordinasi/PMO')->hasPermissionTo('perencanaan.kelola'));
        $this->assertTrue(Role::findByName('Direktorat Sektor')->hasPermissionTo('detail.verifikasi'));
        $this->assertFalse(Role::findByName('Operator K/L')->hasPermissionTo('detail.verifikasi'));
        $this->assertTrue(Role::findByName('Super Admin')->hasPermissionTo('pengaturan.kelola'));
    }

    public function test_view_dashboard_dapat_dibaca(): void
    {
        $this->assertSame(0, DB::table('v_psn_ringkas')->count());
        $this->assertSame(0, DB::table('v_kegiatan_progres')->count());
    }
}
