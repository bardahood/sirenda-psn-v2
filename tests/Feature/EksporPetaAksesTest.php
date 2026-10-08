<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class EksporPetaAksesTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $a = $this->buatPsn(['nama' => 'Alfa', 'nilai_investasi_rp' => 5e12], ['07']);
        $b = $this->buatPsn(['nama' => 'Beta'], ['19']);
        DB::table('psn_lokasi')->insert([['psn_id' => $a->id, 'provinsi_kode' => '32'], ['psn_id' => $b->id, 'provinsi_kode' => '32'], ['psn_id' => $b->id, 'provinsi_kode' => '35']]);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
    }

    public function test_pdf_ringkasan_eksekutif(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $r = $this->get('/laporan/ringkasan.pdf?prov=32')->assertOk();
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertStringContainsString('ringkasan-eksekutif-psn_2026-09.pdf', $r->headers->get('Content-Disposition'));
    }

    public function test_excel_portofolio(): void
    {
        Excel::fake();
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get('/api/v1/proyek?format=xlsx&prov=35')->assertOk();
        Excel::assertDownloaded('portofolio-psn_2026-09.xlsx', fn ($ekspor) => $ekspor->query()->count() === 1);
    }

    public function test_data_peta_per_provinsi(): void
    {
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $d = collect($this->getJson('/api/v1/peta')->assertOk()->json('data'))->keyBy('kode');
        $this->assertSame(2, $d['32']['jumlah']);
        $this->assertSame(1, $d['35']['jumlah']);
        $this->assertNotNull($d['32']['lat']);
        $this->assertCount(38, $d); // lingkup nasional (kode 00) dilaporkan di meta.nasional
        $this->get('/peta')->assertOk()->assertSee('Sebaran PSN per Provinsi');
    }

    /** Akses halaman per peran sesuai matriks RBAC (L/I/V/K; "-" = tidak ada akses). */
    public function test_akses_halaman_per_peran(): void
    {
        $halaman = ['/dashboard', '/proyek', '/perencanaan', '/risiko', '/kualitas-data', '/peta'];
        $harapan = [
            'Super Admin' => [200, 200, 200, 200, 200, 200],
            'Pimpinan' => [200, 200, 200, 200, 200, 200],
            'Tim Koordinasi/PMO' => [200, 200, 200, 200, 200, 200],
            'Direktorat Sektor' => [200, 200, 200, 200, 200, 200],
            'Operator K/L' => [200, 200, 200, 200, 200, 200],
        ];
        foreach ($harapan as $peran => $kode) {
            $this->actingAs($this->buatPengguna($peran, '07'));
            foreach ($halaman as $i => $url) {
                $this->get($url)->assertStatus($kode[$i]);
            }
        }

        // Tanpa peran: semua halaman ditolak.
        $this->actingAs(User::factory()->create());
        foreach ($halaman as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_pengaturan_hanya_super_admin(): void
    {
        foreach (['Pimpinan', 'Tim Koordinasi/PMO', 'Direktorat Sektor', 'Operator K/L'] as $peran) {
            $this->assertFalse($this->buatPengguna($peran)->can('pengaturan.lihat'), $peran);
        }
        $this->assertTrue($this->buatPengguna('Super Admin')->can('pengaturan.kelola'));
    }
}
