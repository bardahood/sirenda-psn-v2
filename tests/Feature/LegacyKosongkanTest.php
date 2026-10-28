<?php

namespace Tests\Feature;

use App\Models\Pleno;
use App\Models\PlenoUsulan;
use App\Models\UsulanPsi;
use App\Models\UsulanPsn;
use App\Services\PsiImportService;
use App\Support\Legacy\LegacyImporter;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Impor ulang (legacy:import --fresh) hanya mengosongkan data hasil impor; data yang lahir di v2
 * (usulan PSI beserta pleno, akun pengguna, jejak audit aksi aplikasi) dipertahankan.
 */
class LegacyKosongkanTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    public function test_kosongkan_mempertahankan_data_v2(): void
    {
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $psn = $this->buatPsn(['legacy_id' => 10]);
        $usulanLama = UsulanPsn::withoutGlobalScopes()->create(['legacy_id' => 5, 'tahun_rkp' => 2027, 'nama' => 'Usulan dari SIRENDA lama']);
        $hasil = app(PsiImportService::class)->terima(json_decode(file_get_contents(base_path('docs/contoh/psi-usulan.json')), true), '127.0.0.1');
        $usulanPsi = $hasil['usulan'];
        $pleno = Pleno::create(['nama' => 'Pleno Uji', 'tanggal' => '2026-10-24', 'status' => 'TERJADWAL']);
        PlenoUsulan::create(['pleno_id' => $pleno->id, 'usulan_id' => $usulanPsi->id, 'urutan' => 1]);
        $pengguna = $this->buatPengguna('Direktorat Sektor', '07');
        $pengguna->forceFill(['legacy_grup' => 'user', 'password' => Hash::make('SandiBaru2026!')])->save();
        DB::table('audit_log')->insert([
            ['tabel' => 'psn', 'record_id' => $psn->id, 'aksi' => 'UPDATE', 'sumber' => 'IMPOR_LEGACY', 'created_at' => now()],
            ['tabel' => 'pleno', 'record_id' => $pleno->id, 'aksi' => 'CREATE', 'sumber' => 'APLIKASI', 'created_at' => now()],
        ]);

        app(LegacyImporter::class)->kosongkan();

        $this->assertSame(0, DB::table('psn')->count());
        $this->assertNull(UsulanPsn::withoutGlobalScopes()->find($usulanLama->id));
        $this->assertNotNull(UsulanPsn::withoutGlobalScopes()->find($usulanPsi->id));
        $this->assertSame(1, UsulanPsi::count());
        $this->assertSame(1, PlenoUsulan::count());
        $this->assertTrue(Hash::check('SandiBaru2026!', $pengguna->fresh()->password));
        // Jejak impor & jejak tabel data PSN dihapus; jejak aksi aplikasi pada pleno tetap ada.
        $this->assertSame(0, DB::table('audit_log')->where('sumber', 'IMPOR_LEGACY')->orWhere('tabel', 'psn')->count());
        $this->assertTrue(DB::table('audit_log')->where('tabel', 'pleno')->where('sumber', 'APLIKASI')->exists());
    }
}
