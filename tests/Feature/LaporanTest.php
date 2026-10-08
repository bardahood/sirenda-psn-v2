<?php

namespace Tests\Feature;

use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $a = $this->buatPsn(['nama' => 'Alfa']);
        DB::table('risiko')->insert(['psn_id' => $a->id, 'uraian' => 'R1', 'kemungkinan_harapan' => 3, 'dampak_harapan' => 3]);
        app(SnapshotService::class)->buat('2026-08', terbit: true);
        app(SnapshotService::class)->buat('2026-09', terbit: true);
    }

    public function test_arsip_per_cutoff_dan_unduhan(): void
    {
        Excel::fake();
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get('/laporan')->assertOk()->assertSeeInOrder(['2026-09', '2026-08'])->assertSee('periode=2026-08', false);

        $this->get('/laporan/risiko.xlsx?periode=2026-08')->assertOk();
        Excel::assertDownloaded('register-risiko-psn_2026-08.xlsx', fn ($e) => $e->query()->count() === 1);
        $this->get('/laporan/pengisian.xlsx?periode=2026-09')->assertOk();
        Excel::assertDownloaded('rekap-pengisian-psn_2026-09.xlsx', fn ($e) => $e->collection()->count() === 1 && $e->collection()->first()[4] === 'Draf'); // baris DRAFT dibuat oleh snapshot
        $this->get('/laporan/ringkasan.pdf?periode=2026-08')->assertOk();
        $this->get('/laporan/risiko.xlsx?periode=2026-10')->assertNotFound();
    }

    public function test_operator_tanpa_izin_laporan(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->get('/laporan')->assertForbidden();
        $this->get('/laporan/pengisian.xlsx')->assertForbidden();
    }

    public function test_header_csp(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('script-src *', $csp);
        $this->assertStringContainsString('https://tile.openstreetmap.org', $csp);
    }
}
