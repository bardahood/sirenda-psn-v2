<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KegiatanTarget;
use App\Models\Psn;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
    }

    public function test_create_update_delete_restore_tercatat_dengan_nilai_lama_dan_baru(): void
    {
        $user = $this->buatPengguna('Super Admin');
        $this->actingAs($user);

        $psn = Psn::create(['nama' => 'Bendungan Uji', 'nilai_investasi_rp' => 10]);
        $this->assertSame($user->id, $psn->created_by);
        $this->assertNotEmpty($psn->uuid);

        $psn->update(['nama' => 'Bendungan Uji Baru', 'nilai_investasi_rp' => 10]);
        $psn->delete();
        $this->assertSame($user->id, Psn::withTrashed()->find($psn->id)->deleted_by);
        $psn->restore();

        $log = AuditLog::where('tabel', 'psn')->where('record_id', $psn->id)->orderBy('id')->get();
        $this->assertSame(['CREATE', 'UPDATE', 'DELETE', 'RESTORE'], $log->pluck('aksi')->all());
        $this->assertSame(['nama' => 'Bendungan Uji'], $log[1]->nilai_lama, 'hanya kolom yang berubah');
        $this->assertSame(['nama' => 'Bendungan Uji Baru'], $log[1]->nilai_baru);
        $this->assertSame($psn->id, $log[1]->psn_id);
        $this->assertSame($user->id, $log[1]->user_id);
        $this->assertSame('Bendungan Uji Baru', $log[2]->nilai_lama['nama']);
    }

    public function test_tabel_turunan_tercatat_dengan_psn_induk(): void
    {
        $this->actingAs($this->buatPengguna('Super Admin'));
        $psn = $this->buatPsn();
        $kegiatan = $this->buatKegiatan($psn, []);

        $t = KegiatanTarget::create(['kegiatan_id' => $kegiatan->id, 'tahun' => 2026, 'periode' => 'BULANAN', 'periode_ke' => 9, 'realisasi_persen' => 40]);

        $log = AuditLog::where('tabel', 'kegiatan_target')->where('record_id', $t->id)->firstOrFail();
        $this->assertSame($psn->id, $log->psn_id);
        $this->assertEquals(40, $log->nilai_baru['realisasi_persen']);
    }
}
