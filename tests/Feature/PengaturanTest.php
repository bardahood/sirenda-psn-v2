<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PeriodeCutoff;
use App\Models\User;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class PengaturanTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $this->admin = $this->buatPengguna('Super Admin');
    }

    public function test_hanya_super_admin_mengakses_pengaturan_dan_kamus_untuk_semua(): void
    {
        $halaman = ['/pengaturan/pengguna', '/pengaturan/pengguna/baru', '/pengaturan/cutoff', '/pengaturan/master'];
        $this->actingAs($this->admin);
        foreach ($halaman as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/pengaturan')->assertRedirect('/pengaturan/pengguna');

        foreach (['Pimpinan' => null, 'Tim Koordinasi/PMO' => null, 'Direktorat Sektor' => '19', 'Operator K/L' => '07'] as $peran => $unit) {
            $this->actingAs($this->buatPengguna($peran, $unit));
            foreach ($halaman as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->put('/pengaturan/master/klaster/1', ['nama' => 'X', 'urutan' => 1])->assertForbidden();
            $this->get('/kamus-indikator')->assertOk()->assertSee('K1');
        }
    }

    public function test_buat_ubah_peran_dan_reset_sandi_tercatat_di_audit(): void
    {
        $this->actingAs($this->admin);
        $unit = $this->ref('ref_unit_kerja', '07');

        $this->post('/pengaturan/pengguna', ['username' => 'op.baru', 'name' => 'Operator Baru', 'email' => 'op@uji.test', 'peran' => 'Operator K/L', 'is_active' => 1])
            ->assertSessionHasErrors('unit_kerja_id');

        $this->post('/pengaturan/pengguna', ['username' => 'op.baru', 'name' => 'Operator Baru', 'email' => 'op@uji.test', 'peran' => 'Operator K/L', 'unit_kerja_id' => $unit, 'is_active' => 1])
            ->assertRedirect()->assertSessionHas('status');
        $u = User::where('username', 'op.baru')->firstOrFail();
        $this->assertTrue($u->hasRole('Operator K/L'));
        $this->assertTrue((bool) $u->wajib_ganti_password);
        $this->assertTrue(AuditLog::where('tabel', 'users')->where('record_id', $u->id)->where('aksi', 'CREATE')->exists());

        $this->put("/pengaturan/pengguna/{$u->id}", ['username' => 'op.baru', 'name' => 'Operator Baru', 'email' => 'op@uji.test', 'peran' => 'Pimpinan', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Pimpinan'], $u->fresh()->getRoleNames()->all());
        $log = AuditLog::where('tabel', 'users')->where('record_id', $u->id)->whereNotNull('nilai_baru->peran')->latest('id')->first();
        $this->assertSame('Pimpinan', $log->nilai_baru['peran']);
        $this->assertSame('Operator K/L', $log->nilai_lama['peran']);

        $hashLama = $u->fresh()->password;
        $this->post("/pengaturan/pengguna/{$u->id}/reset-sandi")->assertSessionHas('status');
        $this->assertNotSame($hashLama, $u->fresh()->password);
        $this->assertTrue(AuditLog::where('record_id', $u->id)->where('tabel', 'users')->where('nilai_baru->reset_kata_sandi', true)->exists());
        // Hash kata sandi tidak pernah masuk audit.
        $this->assertFalse(AuditLog::where('tabel', 'users')->where(fn ($q) => $q->where('nilai_baru', 'like', '%"password"%')->orWhere('nilai_baru', 'like', '%$2y$%'))->exists());
    }

    public function test_super_admin_tidak_dapat_mengunci_akun_sendiri(): void
    {
        $this->actingAs($this->admin);
        $isi = ['username' => $this->admin->username, 'name' => 'Admin', 'email' => $this->admin->email, 'is_active' => 1];
        $this->put("/pengaturan/pengguna/{$this->admin->id}", $isi + ['peran' => 'Pimpinan'])->assertSessionHasErrors('peran');
        $this->put("/pengaturan/pengguna/{$this->admin->id}", ['is_active' => 0] + $isi + ['peran' => 'Super Admin'])->assertSessionHasErrors('peran');
        $this->assertTrue($this->admin->fresh()->hasRole('Super Admin'));
        $this->assertTrue((bool) $this->admin->fresh()->is_active);
    }

    public function test_bangun_dan_terbitkan_cutoff_dari_ui(): void
    {
        $this->buatPsn(['nama' => 'Alfa']);
        $this->actingAs($this->admin);

        $this->post('/pengaturan/cutoff', ['kode' => '2026-13'])->assertSessionHasErrors('kode');
        $this->post('/pengaturan/cutoff', ['kode' => '2026-09', 'terbit' => 1])->assertSessionHas('status');

        $c = PeriodeCutoff::where('kode', '2026-09')->firstOrFail();
        $this->assertSame('TERBIT', $c->status);
        $this->assertSame(1, DB::table('snapshot_psn')->where('periode_cutoff_id', $c->id)->count());
        $this->assertTrue(AuditLog::where('tabel', 'periode_cutoff')->where('aksi', 'PUBLISH')->exists());
        $this->get('/pengaturan/cutoff')->assertOk()->assertSee('2026-09');
    }

    public function test_ubah_master_data_tercatat_dan_membatalkan_cache_opsi_filter(): void
    {
        $this->actingAs($this->admin);
        $id = $this->ref('ref_klaster', 'R.6');
        Cache::put('psn_dashboard:filter-opsi', ['basi'], 600);

        $this->put("/pengaturan/master/klaster/{$id}", ['nama' => 'Klaster Diubah', 'urutan' => 3, 'is_aktif' => 1])->assertSessionHas('status', 'Perubahan tersimpan.');
        $this->assertSame('Klaster Diubah', DB::table('ref_klaster')->where('id', $id)->value('nama'));
        $this->assertNull(Cache::get('psn_dashboard:filter-opsi'));
        $log = AuditLog::where('tabel', 'ref_klaster')->where('record_id', $id)->firstOrFail();
        $this->assertSame('Klaster Diubah', $log->nilai_baru['nama']);

        $this->put("/pengaturan/master/klaster/{$id}", ['nama' => 'Klaster Diubah', 'urutan' => 3, 'is_aktif' => 1])->assertSessionHas('status', 'Tidak ada perubahan.');
        $this->put('/pengaturan/master/unit/999999', ['nama' => 'X', 'jenis' => 'KL'])->assertNotFound();
    }
}
