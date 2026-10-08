<?php

namespace Tests\Feature;

use App\Models\Penilaian;
use App\Models\UsulanPsn;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class PerencanaanTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected UsulanPsn $usulan;

    protected UsulanPsn $usulanLain;

    protected array $k;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $this->k = DB::table('ref_kriteria')->pluck('id', 'kode')->all();

        // Unit 19 = Dit. Sumber Daya Air; 07 = Dit. Konektivitas.
        $this->usulan = UsulanPsn::withoutGlobalScopes()->create(['tahun_rkp' => 2027, 'nama' => 'Bendungan Usulan', 'jenis_pengusul' => 'KL',
            'is_infrastruktur' => true, 'unit_kerja_id' => $this->ref('ref_unit_kerja', '19'), 'klaster_id' => $this->ref('ref_klaster', 'R.4')]);
        $this->usulanLain = UsulanPsn::withoutGlobalScopes()->create(['tahun_rkp' => 2027, 'nama' => 'Jalan Usulan', 'jenis_pengusul' => 'PEMDA',
            'unit_kerja_id' => $this->ref('ref_unit_kerja', '07')]);
    }

    /** Nilai lengkap untuk usulan K/L infrastruktur. */
    protected function nilaiLengkap(array $timpa = []): array
    {
        $v = $timpa + ['KU1' => 1, 'KU2' => 1, 'KU3' => 1, 'KP1' => 3, 'KP2' => 2, 'KP3' => 3, 'KP4' => 1,
            'KK1' => 3, 'KK2' => 3, 'KK3' => 2, 'KK4' => 1, 'KK5' => 3, 'KL1' => 2, 'KT1' => 3];

        return collect($v)->mapWithKeys(fn ($n, $kode) => [$this->k[$kode] => $n])->all();
    }

    protected function sesi(UsulanPsn $u): Penilaian
    {
        $this->post(route('perencanaan.penilaian.store', $u), ['forum' => 'Rapat Pleno II', 'tanggal' => '2026-10-01'])->assertRedirect();

        return Penilaian::withoutGlobalScopes()->where('usulan_id', $u->id)->latest('id')->firstOrFail();
    }

    public function test_alur_penilaian_lengkap_hingga_final(): void
    {
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $p = $this->sesi($this->usulan);

        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $this->nilaiLengkap(), 'temuan' => [$this->k['KP1'] => 'Dokumen tematik lengkap']])->assertRedirect();
        $p->refresh();
        $this->assertTrue($p->gate_lulus);
        $this->assertEquals(79.25, $p->nilai_akhir);
        $this->assertSame('AMBANG_BELUM_DITETAPKAN', $p->rekomendasi);

        $this->get(route('perencanaan.show', $this->usulan))->assertOk()
            ->assertSee('79,25')->assertSee('Ambang rekomendasi belum ditetapkan')->assertSee('Dokumen tematik lengkap')->assertDontSee('DITOLAK');

        $this->post(route('perencanaan.penilaian.final', $p))->assertRedirect();
        $this->assertSame('FINAL', $p->fresh()->status);
        $this->assertTrue(DB::table('audit_log')->where(['tabel' => 'penilaian', 'record_id' => $p->id, 'aksi' => 'VERIFY'])->exists());
        $this->assertTrue(DB::table('audit_log')->where(['tabel' => 'penilaian_skor', 'aksi' => 'CREATE'])->exists());

        // Setelah FINAL, skor tidak dapat diubah.
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $this->nilaiLengkap(['KP1' => 0])])->assertForbidden();
    }

    public function test_ambang_terkonfigurasi_menghasilkan_rekomendasi(): void
    {
        Config::set('psn_dashboard.penilaian.ambang', ['direkomendasikan' => 75, 'dipertimbangkan' => 60]);
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $p = $this->sesi($this->usulan);
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $this->nilaiLengkap()]);
        $this->assertSame('DIREKOMENDASIKAN', $p->fresh()->rekomendasi);
    }

    public function test_gate_override_ditolak_dan_dapat_difinalkan_walau_belum_lengkap(): void
    {
        Config::set('psn_dashboard.penilaian.ambang', ['direkomendasikan' => 10, 'dipertimbangkan' => 5]);
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $p = $this->sesi($this->usulan);

        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => [$this->k['KU2'] => 0]]);
        $this->assertSame('DITOLAK', $p->fresh()->rekomendasi);
        $this->get(route('perencanaan.show', $this->usulan))->assertSee('DITOLAK')->assertSee('KU2');

        $this->post(route('perencanaan.penilaian.final', $p))->assertRedirect();
        $this->assertSame('FINAL', $p->fresh()->status);
    }

    public function test_final_ditolak_bila_penilaian_belum_lengkap(): void
    {
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $p = $this->sesi($this->usulan);
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $this->nilaiLengkap(['KT1' => null])]);

        $this->post(route('perencanaan.penilaian.final', $p))->assertSessionHasErrors('finalisasi');
        $this->assertSame('DRAFT', $p->fresh()->status);
    }

    public function test_validasi_skor(): void
    {
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $p = $this->sesi($this->usulan);
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => [$this->k['KU1'] => 2]])->assertSessionHasErrors("nilai.{$this->k['KU1']}");
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => [$this->k['KP1'] => 4]])->assertSessionHasErrors("nilai.{$this->k['KP1']}");
    }

    public function test_hak_akses_sesuai_matriks(): void
    {
        // Operator K/L unit 19: I¹ -- input hanya usulan unitnya, tanpa finalisasi, tidak melihat usulan unit lain.
        $op = $this->buatPengguna('Operator K/L', '19');
        $this->actingAs($op);
        $this->get(route('perencanaan.index'))->assertOk()->assertSee('Bendungan Usulan')->assertDontSee('Jalan Usulan');
        $this->get(route('perencanaan.show', $this->usulanLain))->assertNotFound();
        $p = $this->sesi($this->usulan);
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $this->nilaiLengkap()])->assertRedirect();
        $this->post(route('perencanaan.penilaian.final', $p))->assertForbidden();

        // Operator membuat usulan: unit dipaksa ke unitnya sendiri.
        $this->post(route('perencanaan.store'), ['nama' => 'Usulan Operator', 'tahun_rkp' => 2027, 'jenis_pengusul' => 'KL', 'unit_kerja_id' => $this->ref('ref_unit_kerja', '07')])->assertRedirect();
        $this->assertSame($this->ref('ref_unit_kerja', '19'), (int) UsulanPsn::withoutGlobalScopes()->where('nama', 'Usulan Operator')->value('unit_kerja_id'));

        // Direktorat Sektor unit 19: V¹ -- finalisasi usulan unitnya, tidak untuk unit lain (tetapi dapat melihat).
        $dit = $this->buatPengguna('Direktorat Sektor', '19');
        $this->actingAs($dit);
        $this->post(route('perencanaan.penilaian.final', $p))->assertRedirect();
        $this->assertSame('FINAL', $p->fresh()->status);
        $this->get(route('perencanaan.show', $this->usulanLain))->assertOk();
        $this->post(route('perencanaan.penilaian.store', $this->usulanLain), ['forum' => 'X', 'tanggal' => '2026-10-01'])->assertForbidden();
        $this->post(route('perencanaan.penilaian.buka', $p))->assertForbidden();

        // PMO: kelola -- membuka kembali penilaian FINAL.
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->post(route('perencanaan.penilaian.buka', $p))->assertRedirect();
        $this->assertSame('DRAFT', $p->fresh()->status);

        // Pimpinan: lihat saja.
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get(route('perencanaan.show', $this->usulan))->assertOk()->assertDontSee('Simpan skor');
        $this->get(route('perencanaan.create'))->assertForbidden();
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => []])->assertForbidden();
    }

    public function test_api_skor_usulan(): void
    {
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->getJson("/api/v1/usulan/{$this->usulan->id}/skor")->assertOk()->assertJsonPath('data', null);

        $p = $this->sesi($this->usulan);
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $this->nilaiLengkap()]);

        $r = $this->getJson("/api/v1/usulan/{$this->usulan->id}/skor")->assertOk()
            ->assertJsonPath('data.nilai_akhir', 79.25)
            ->assertJsonPath('data.gate.lulus', true)
            ->assertJsonPath('data.rekomendasi.kode', 'AMBANG_BELUM_DITETAPKAN')
            ->assertJsonPath('meta.bobot.PENDUKUNG', 0.35);
        $kp5 = collect($r->json('data.sub_kriteria'))->firstWhere('kode', 'KP5');
        $this->assertFalse($kp5['berlaku']);

        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->getJson("/api/v1/usulan/{$this->usulan->id}/skor")->assertNotFound();
    }

    public function test_daftar_diurutkan_nilai_akhir(): void
    {
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $p = $this->sesi($this->usulan);
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $this->nilaiLengkap()]);
        $this->get(route('perencanaan.index'))->assertOk()->assertSeeInOrder(['Bendungan Usulan', '79,25', 'Jalan Usulan']);
        $this->get(route('perencanaan.index', ['status' => 'BELUM_DINILAI']))->assertDontSee('Bendungan Usulan')->assertSee('Jalan Usulan');
    }
}
