<?php

namespace Tests\Feature;

use App\Models\Kegiatan;
use App\Models\KegiatanTarget;
use App\Models\Psn;
use App\Models\SnapshotPsn;
use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class CakupanAksesTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected Psn $milikUnit;

    protected Psn $lain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);

        // Unit 101 = Menteri Pekerjaan Umum, 19 = Direktorat Sumber Daya Air.
        $this->milikUnit = $this->buatPsn(['nama' => 'Milik PU'], ['101', '19']);
        $this->lain = $this->buatPsn(['nama' => 'Milik lain'], ['120']);
        foreach ([$this->milikUnit, $this->lain] as $p) {
            $this->buatKegiatan($p, [['periode' => 'TAHUNAN', 'periode_ke' => 0, 'target_1' => 1]]);
        }
        app(SnapshotService::class)->buat('2026-09');
    }

    public function test_operator_kl_hanya_melihat_psn_unitnya_di_level_query(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '101'));

        $this->assertSame(['Milik PU'], Psn::pluck('nama')->all());
        $this->assertSame(1, Kegiatan::count());
        $this->assertSame(1, KegiatanTarget::count());
        $this->assertSame(1, SnapshotPsn::count());
        $this->assertNull(Psn::find($this->lain->id));
    }

    public function test_operator_tanpa_unit_tidak_melihat_apa_pun(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L'));
        $this->assertSame(0, Psn::count());
    }

    public function test_peran_lain_melihat_seluruh_portofolio(): void
    {
        foreach (['Pimpinan', 'Tim Koordinasi/PMO', 'Direktorat Sektor', 'Super Admin'] as $peran) {
            $this->actingAs($this->buatPengguna($peran, '19'));
            $this->assertSame(2, Psn::count(), $peran);
        }
    }

    public function test_tanpa_login_scope_tidak_berlaku(): void
    {
        $this->assertSame(2, Psn::count());
    }

    public function test_policy_input_verifikasi_sesuai_matriks(): void
    {
        $operator = $this->buatPengguna('Operator K/L', '101');
        $direktorat = $this->buatPengguna('Direktorat Sektor', '19');
        $pimpinan = $this->buatPengguna('Pimpinan');
        $pmo = $this->buatPengguna('Tim Koordinasi/PMO');
        $admin = $this->buatPengguna('Super Admin');

        // Operator: input¹, tanpa verifikasi.
        $this->assertTrue($operator->can('update', $this->milikUnit));
        $this->assertFalse($operator->can('update', $this->lain));
        $this->assertFalse($operator->can('verifikasi', $this->milikUnit));

        // Direktorat: verifikasi¹ (mencakup input) hanya PSN yang diampu, tetapi dapat melihat semua.
        $this->assertTrue($direktorat->can('verifikasi', $this->milikUnit));
        $this->assertFalse($direktorat->can('verifikasi', $this->lain));
        $this->assertTrue($direktorat->can('view', $this->lain));

        // Pimpinan & PMO: lihat saja pada Detail.
        $this->assertTrue($pimpinan->can('view', $this->lain));
        $this->assertFalse($pimpinan->can('update', $this->milikUnit));
        $this->assertFalse($pmo->can('update', $this->milikUnit));

        $this->assertTrue($admin->can('delete', $this->lain));
    }
}
