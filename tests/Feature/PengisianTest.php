<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\KegiatanTarget;
use App\Models\PengisianPsn;
use App\Models\PeriodeCutoff;
use App\Models\Psn;
use App\Services\SnapshotService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

class PengisianTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected Psn $a;

    protected Psn $b;

    protected array $ro = [];

    protected int $risiko;

    protected int $isu;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $this->a = $this->buatPsn(['nama' => 'Alfa'], ['07']);
        $this->b = $this->buatPsn(['nama' => 'Beta'], ['19']);
        $tahunan = ['periode' => 'TAHUNAN', 'periode_ke' => 0, 'pagu_rp' => 10_000_000, 'realisasi_anggaran_rp' => 5_000_000];
        $this->ro = [
            $this->buatKegiatan($this->a, [$tahunan], ['nama' => 'RO Satu', 'is_critical_path' => true])->id,
            $this->buatKegiatan($this->a, [$tahunan], ['nama' => 'RO Dua'])->id,
        ];
        $this->buatKegiatan($this->b, [$tahunan], ['nama' => 'RO Beta']);
        $this->risiko = DB::table('risiko')->insertGetId(['psn_id' => $this->a->id, 'uraian' => 'Pembebasan lahan', 'kemungkinan_harapan' => 2, 'dampak_harapan' => 2]);
        $this->isu = DB::table('isu')->insertGetId(['psn_id' => $this->a->id, 'uraian' => 'Izin lingkungan', 'status' => 'TERBUKA']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function url(string $tambahan = '', ?Psn $psn = null): string
    {
        return '/pengisian/2026-10/'.($psn ?? $this->a)->id.$tambahan;
    }

    protected function isiRo(array $nilai): array
    {
        return ['ro' => collect($this->ro)->mapWithKeys(fn ($id, $i) => [$id => $nilai[$i] ?? []])->all()];
    }

    public function test_akses_halaman_dan_cakupan_unit(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->get('/pengisian')->assertOk()->assertSee('Alfa')->assertDontSee('Beta');
        $this->get($this->url())->assertOk()->assertSee('RO Satu')->assertSee('Pembebasan lahan')->assertSee('Izin lingkungan');
        $this->get($this->url('', $this->b))->assertNotFound();
        $this->post($this->url('', $this->b), [])->assertNotFound();
        $this->get('/pengisian/2026-11/'.$this->a->id)->assertNotFound(); // periode mendatang

        foreach (['Pimpinan', 'Tim Koordinasi/PMO'] as $peran) {
            $this->actingAs($this->buatPengguna($peran));
            $this->get('/pengisian')->assertForbidden();
        }

        // Direktorat melihat semua PSN tetapi hanya mengisi PSN unitnya.
        $this->actingAs($this->buatPengguna('Direktorat Sektor', '19'));
        $this->get('/pengisian?milik=0')->assertOk()->assertSee('Alfa')->assertSee('Beta');
        $this->get('/pengisian')->assertOk()->assertDontSee('Alfa');
        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 10]]))->assertForbidden();
    }

    public function test_simpan_draf_tercatat_dan_tidak_membuat_baris_kosong(): void
    {
        Storage::fake('public');
        $op = $this->buatPengguna('Operator K/L', '07');
        $this->actingAs($op);

        $this->post($this->url(), $this->isiRo([['rencana_persen' => 40, 'realisasi_persen' => '35,5', 'realisasi_anggaran_rp' => 1_000_000, 'permasalahan' => 'Cuaca']])
            + ['bukti' => [$this->ro[0] => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')]])
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $t = KegiatanTarget::where(['kegiatan_id' => $this->ro[0], 'periode' => 'BULANAN', 'periode_ke' => 10, 'tahun' => 2026])->firstOrFail();
        $this->assertSame('35.50', $t->realisasi_persen);
        $this->assertNotNull($t->dilaporkan_at);
        Storage::disk('public')->assertExists($t->bukti_path);
        $this->assertFalse(KegiatanTarget::where(['kegiatan_id' => $this->ro[1], 'periode' => 'BULANAN'])->exists());

        $g = PengisianPsn::where('psn_id', $this->a->id)->firstOrFail();
        $this->assertSame('DRAFT', $g->status);
        $this->assertSame(1, DB::table('pengisian_psn_riwayat')->where('pengisian_psn_id', $g->id)->count());
        $this->assertTrue(AuditLog::where('tabel', 'kegiatan_target')->where('record_id', $t->id)->where('psn_id', $this->a->id)->where('aksi', 'CREATE')->exists());

        // Simpan ulang tanpa perubahan: tidak ada jejak audit baru dan waktu pelaporan tetap.
        $jumlahAudit = AuditLog::where('tabel', 'kegiatan_target')->count();
        $waktu = $t->dilaporkan_at;
        Carbon::setTestNow('2026-10-09 10:00:00');
        $this->post($this->url(), $this->isiRo([['rencana_persen' => '40.00', 'realisasi_persen' => 35.5, 'realisasi_anggaran_rp' => 1000000, 'permasalahan' => 'Cuaca']]));
        $this->assertSame($jumlahAudit, AuditLog::where('tabel', 'kegiatan_target')->count());
        $this->assertTrue($waktu->eq($t->fresh()->dilaporkan_at));

        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 120]]))->assertSessionHasErrors('ro.'.$this->ro[0].'.realisasi_persen');
        $this->post($this->url(), ['bukti' => [$this->ro[0] => UploadedFile::fake()->create('x.exe', 10)]])->assertSessionHasErrors('bukti.'.$this->ro[0]);
    }

    public function test_alur_ajukan_kembalikan_verifikasi(): void
    {
        $op = $this->buatPengguna('Operator K/L', '07');
        $this->actingAs($op);

        // Pengajuan ditolak bila ada KP/RO tanpa realisasi.
        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 30]]) + ['ajukan' => 1])->assertSessionHasErrors('status');
        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 30], ['realisasi_persen' => 20]]) + ['ajukan' => 1])->assertSessionHasNoErrors();
        $g = PengisianPsn::where('psn_id', $this->a->id)->firstOrFail();
        $this->assertSame('DIAJUKAN', $g->status);
        $this->assertSame($op->id, $g->diajukan_oleh);
        $this->assertTrue(AuditLog::where('tabel', 'pengisian_psn')->where('aksi', 'SUBMIT')->where('psn_id', $this->a->id)->exists());

        // Terkunci setelah diajukan; operator tidak dapat memverifikasi.
        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 31]]))->assertSessionHasErrors('status');
        $this->post($this->url('/verifikasi'), ['keputusan' => 'setuju'])->assertForbidden();

        // Direktorat unit lain tidak berwenang; direktorat pengampu wajib memberi catatan saat mengembalikan.
        $this->actingAs($this->buatPengguna('Direktorat Sektor', '19'));
        $this->post($this->url('/verifikasi'), ['keputusan' => 'setuju'])->assertForbidden();
        $dir = $this->buatPengguna('Direktorat Sektor', '07');
        $this->actingAs($dir);
        $this->get($this->url())->assertSee('Verifikasi Isian');
        $this->post($this->url('/verifikasi'), ['keputusan' => 'kembalikan'])->assertSessionHasErrors('catatan');
        $this->post($this->url('/verifikasi'), ['keputusan' => 'kembalikan', 'catatan' => 'Lampirkan bukti RO Dua'])->assertSessionHasNoErrors();
        $this->assertSame('DIKEMBALIKAN', $g->fresh()->status);
        $this->assertTrue(AuditLog::where('tabel', 'pengisian_psn')->where('aksi', 'RETURN')->exists());

        // Perbaiki, ajukan ulang, lalu diverifikasi.
        $this->actingAs($op);
        $this->get($this->url())->assertSee('Lampirkan bukti RO Dua');
        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 30], ['realisasi_persen' => 25]]) + ['ajukan' => 1])->assertSessionHasNoErrors();
        $this->actingAs($dir);
        $this->post($this->url('/verifikasi'), ['keputusan' => 'setuju'])->assertSessionHasNoErrors();
        $g->refresh();
        $this->assertSame('DIVERIFIKASI', $g->status);
        $this->assertSame($dir->id, $g->diverifikasi_oleh);
        $this->assertSame(['DRAFT', 'DIAJUKAN', 'DIKEMBALIKAN', 'DIAJUKAN', 'DIVERIFIKASI'],
            DB::table('pengisian_psn_riwayat')->where('pengisian_psn_id', $g->id)->orderBy('id')->pluck('ke_status')->all());
        $this->post($this->url('/verifikasi'), ['keputusan' => 'setuju'])->assertSessionHasErrors('status');
    }

    public function test_pemantauan_risiko_isu_dan_snapshot_membaca_isian(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->post($this->url(), ['risiko' => [$this->risiko => ['kemungkinan_aktual' => 4]]])->assertSessionHasErrors('risiko.'.$this->risiko.'.dampak_aktual');

        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 35, 'rencana_persen' => 40, 'realisasi_anggaran_rp' => 1_000_000]]) + [
            'risiko' => [$this->risiko => ['kemungkinan_aktual' => 4, 'dampak_aktual' => 4, 'status_perlakuan' => 'BERJALAN']],
            'risiko_baru' => ['uraian' => 'Kenaikan harga material', 'kemungkinan_harapan' => 5, 'dampak_harapan' => 4],
            'isu' => [$this->isu => ['status' => 'SELESAI', 'tindak_lanjut' => 'Izin terbit']],
            'isu_baru' => ['uraian' => 'Akses jalan kerja', 'pic_nama' => 'PPK 1', 'tenggat' => '2026-11-01'],
        ])->assertSessionHasNoErrors();

        $m = DB::table('risiko_pemantauan')->where('risiko_id', $this->risiko)->first();
        $this->assertSame(['2026-10-31', 'Tinggi', 4], [$m->tanggal, $m->level_aktual, (int) $m->triwulan]);
        $this->assertSame('Sangat Tinggi', DB::table('risiko')->where('uraian', 'Kenaikan harga material')->value('level_harapan'));
        $this->assertSame('2026-10-08', DB::table('isu')->where('id', $this->isu)->value('tanggal_selesai'));
        $this->assertTrue(DB::table('isu')->where('uraian', 'Akses jalan kerja')->where('status', 'TERBUKA')->exists());
        $this->assertTrue(AuditLog::where('tabel', 'risiko_pemantauan')->where('psn_id', $this->a->id)->exists());

        // Snapshot cut-off membaca isian: progres bulanan, realisasi anggaran bulanan yang dilaporkan, risiko aktual.
        app(SnapshotService::class)->buat('2026-10', terbit: true);
        $c = PeriodeCutoff::where('kode', '2026-10')->firstOrFail();
        $g = DB::table('snapshot_kegiatan')->where('periode_cutoff_id', $c->id)->where('kegiatan_id', $this->ro[0])->first();
        $this->assertSame([40.0, 35.0, 1_000_000.0, 10_000_000.0], [(float) $g->target_persen, (float) $g->realisasi_persen, (float) $g->realisasi_anggaran_rp, (float) $g->pagu_rp]);
        $r = DB::table('snapshot_risiko')->where('periode_cutoff_id', $c->id)->where('risiko_id', $this->risiko)->first();
        $this->assertSame([4, 4, 'Tinggi'], [(int) $r->kemungkinan_aktual, (int) $r->dampak_aktual, $r->level_aktual]);
        // RO tanpa isian bulanan tetap memakai realisasi anggaran TAHUNAN lama.
        $this->assertSame(5_000_000.0, (float) DB::table('snapshot_kegiatan')->where('periode_cutoff_id', $c->id)->where('kegiatan_id', $this->ro[1])->value('realisasi_anggaran_rp'));

        // Setelah terbit: terkunci.
        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 50]]))->assertStatus(409);
        $this->get($this->url())->assertOk()->assertSee('Periode ini sudah diterbitkan');
    }

    public function test_risiko_dan_isu_tidak_diubah_tanpa_izin_risiko_input(): void
    {
        // Izin risiko.input dicabut dari Operator: progres tetap tersimpan, risiko & isu diabaikan.
        Role::findByName('Operator K/L')->revokePermissionTo('risiko.input');
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->post($this->url(), $this->isiRo([['realisasi_persen' => 10]]) + ['isu_baru' => ['uraian' => 'Tidak boleh']])->assertSessionHasNoErrors();
        $this->assertFalse(DB::table('isu')->where('uraian', 'Tidak boleh')->exists());
        $this->assertTrue(KegiatanTarget::where(['kegiatan_id' => $this->ro[0], 'periode' => 'BULANAN'])->exists());
    }
}
