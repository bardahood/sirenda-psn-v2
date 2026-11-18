<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/** Pengaturan > Peran & Hak Akses: centang menu per peran. */
class PeranHakAksesTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
    }

    public function test_centang_mengubah_menu_yang_bisa_dibuka_dan_tercatat_di_audit(): void
    {
        $pimpinan = $this->buatPengguna('Pimpinan');
        $this->actingAs($pimpinan)->get('/kualitas-data')->assertOk();
        $this->get('/pengaturan/peran')->assertForbidden();

        $admin = $this->buatPengguna('Super Admin');
        $role = Role::findByName('Pimpinan');
        $this->actingAs($admin)->get('/pengaturan/peran?peran='.$role->id)->assertOk()
            ->assertSee('Hak Akses: Pimpinan')->assertSee('Evaluasi Status PSN')->assertSee('Kualitas Data');

        // Pimpinan: tutup Kualitas Data, beri Evaluasi sampai Input (Lihat ikut tercentang).
        $hak = ['ringkasan' => 'lihat', 'portofolio' => 'lihat', 'detail' => 'lihat', 'perencanaan' => 'lihat', 'risiko' => 'lihat',
            'kualitas' => '', 'laporan' => 'lihat', 'evaluasi' => 'input', 'pengaturan' => ''];
        $this->put('/pengaturan/peran/'.$role->id, ['hak' => $hak])->assertRedirect()->assertSessionHas('status');

        $pimpinan = $pimpinan->fresh();
        $this->assertFalse($pimpinan->can('kualitas.lihat'));
        $this->assertTrue($pimpinan->can('evaluasi.lihat') && $pimpinan->can('evaluasi.input'));
        $this->assertFalse($pimpinan->can('evaluasi.verifikasi'));
        $this->actingAs($pimpinan)->get('/kualitas-data')->assertForbidden();
        $this->assertTrue(AuditLog::where('tabel', 'roles')->where('record_id', $role->id)->where('aksi', 'UPDATE')->exists());

        // Tingkat yang tidak tersedia ditolak; Super Admin tidak dapat diubah.
        $this->actingAs($admin)->put('/pengaturan/peran/'.$role->id, ['hak' => ['kualitas' => 'kelola']])->assertSessionHasErrors('hak.kualitas');
        $this->put('/pengaturan/peran/'.Role::findByName('Super Admin')->id, ['hak' => []])->assertForbidden();
    }

    public function test_peran_baru_disalin_dan_hanya_peran_tambahan_tanpa_pengguna_yang_bisa_dihapus(): void
    {
        $this->actingAs($this->buatPengguna('Super Admin'));
        $this->post('/pengaturan/peran', ['nama' => 'Analis Direktorat', 'salin_dari' => Role::findByName('Pimpinan')->id])->assertRedirect();
        $baru = Role::findByName('Analis Direktorat');
        $this->assertEqualsCanonicalizing(Role::findByName('Pimpinan')->permissions->pluck('name')->all(), $baru->permissions->pluck('name')->all());
        $this->post('/pengaturan/peran', ['nama' => 'Analis Direktorat'])->assertSessionHasErrors('nama');

        $this->delete('/pengaturan/peran/'.Role::findByName('Pimpinan')->id)->assertSessionHasErrors('peran');
        $this->buatPengguna('Analis Direktorat');
        $this->delete('/pengaturan/peran/'.$baru->id)->assertSessionHasErrors('peran');
        $baru->users()->detach();
        $this->delete('/pengaturan/peran/'.$baru->id)->assertRedirect();
        $this->assertNull(Role::where('name', 'Analis Direktorat')->first());
    }

    public function test_seeder_tidak_menimpa_hak_akses_yang_sudah_diubah(): void
    {
        Role::findByName('Pimpinan')->revokePermissionTo('kualitas.lihat');
        $this->seed(PeranSeeder::class);
        $this->assertFalse(Role::findByName('Pimpinan')->fresh()->hasPermissionTo('kualitas.lihat'));
    }
}
