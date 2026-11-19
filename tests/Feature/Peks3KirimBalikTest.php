<?php

namespace Tests\Feature;

use App\Http\Controllers\TerimaDariDashboardController;
use App\Models\Peks3Keluar;
use App\Models\Psn;
use App\Services\Peks3KeluarService;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Sinkron dua arah (docs/integrasi-peks3.md §9): isian menu Pengisian -> antrean peks3_keluar -> kit penerima
 * PEKS3 (dijalankan di basis data cermin uji sebagai tiruan basis data SIRENDA PEKS3) -> pemulihan setelah impor ulang.
 */
class Peks3KirimBalikTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected const RAHASIA = 'rahasia-uji-peks3-0123456789abcdef0123456789abcdef';

    protected Psn $psn;

    protected int $kegiatan;

    protected int $risiko;

    protected int $isu;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);

        // Tiruan basis data SIRENDA PEKS3 (sekaligus cermin bertanda peks3_cermin).
        $db = config('database.connections.mysql.database').'_cermin';
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$db}`");
        config(['database.connections.legacy' => array_merge(config('database.connections.mysql'), ['database' => $db])]);
        DB::purge('legacy');
        $s = Schema::connection('legacy');
        foreach (['peks3_cermin', 'psn', 'psn_kegiatan', 'psn_kegiatan_cp', 'psn_kegiatan_detil', 'psn_kegiatan_detil_cp', 'psn_risiko', 'psn_kebutuhan'] as $t) {
            $s->dropIfExists($t);
        }
        $jejak = function (Blueprint $t) {
            $t->string('uuid', 50);
            $t->string('created_by', 50)->nullable();
            $t->dateTime('created_at')->nullable();
            $t->string('updated_by', 50)->nullable();
            $t->dateTime('updated_at')->nullable();
            $t->dateTime('deleted_at')->nullable();
        };
        $s->create('peks3_cermin', fn (Blueprint $t) => $t->id());
        $s->create('psn', fn (Blueprint $t) => [$t->increments('id'), $t->text('proyek_psn'), $t->dateTime('deleted_at')->nullable()]);
        foreach (['psn_kegiatan', 'psn_kegiatan_cp'] as $tb) {
            $s->create($tb, fn (Blueprint $t) => [$t->increments('id'), $t->integer('psn_id'), $t->text('kegiatan'), $t->dateTime('deleted_at')->nullable()]);
        }
        $s->create('psn_kegiatan_detil', function (Blueprint $t) use ($jejak) {
            $t->increments('id');
            $t->integer('psn_id');
            $t->integer('kegiatan_id');
            $t->year('tahun');
            foreach (range(1, 12) as $m) {
                $t->float("target_persen_{$m}")->nullable();
                $t->float("realisasi_persen_{$m}")->nullable();
                $t->float("realisasi_anggaran_{$m}")->nullable();
            }
            $jejak($t);
        });
        $s->create('psn_risiko', function (Blueprint $t) use ($jejak) {
            $t->increments('id');
            $t->integer('psn_id');
            $t->text('risiko')->nullable();
            $t->string('kategori', 50)->nullable();
            $t->string('risiko_harapan', 50)->nullable();
            $t->text('rencana')->nullable();
            $t->text('penanggung_jawab')->nullable();
            $t->text('risiko_residual')->nullable();
            $t->string('status', 50)->nullable();
            $jejak($t);
        });
        // psn_kebutuhan SEBELUM migrasi §9.5 (tanpa kolom status/pic/tenggat).
        $s->create('psn_kebutuhan', function (Blueprint $t) use ($jejak) {
            $t->increments('id');
            $t->integer('psn_id');
            $t->string('jenis', 5)->nullable();
            $t->text('point')->nullable();
            $t->text('deskripsi')->nullable();
            $jejak($t);
        });
        $c = DB::connection('legacy');
        $c->table('psn')->insert(['id' => 701, 'proyek_psn' => 'Bendungan Uji']);
        $c->table('psn_kegiatan')->insert(['id' => 7011, 'psn_id' => 701, 'kegiatan' => 'Bendungan utama']);
        $c->table('psn_kegiatan_detil')->insert(['id' => 1, 'uuid' => 'u1', 'psn_id' => 701, 'kegiatan_id' => 7011, 'tahun' => 2026, 'realisasi_persen_9' => 30, 'updated_at' => '2026-09-30 10:00:00']);
        $c->table('psn_risiko')->insert(['id' => 501, 'uuid' => 'u2', 'psn_id' => 701, 'risiko' => 'Banjir', 'risiko_residual' => 'Sedang']);
        $c->table('psn_kebutuhan')->insert(['id' => 601, 'uuid' => 'u3', 'psn_id' => 701, 'jenis' => 'A', 'point' => 'Izin lingkungan']);

        // Data dashboard hasil impor dari PEKS3.
        $this->psn = $this->buatPsn(['nama' => 'Bendungan Uji', 'legacy_id' => 701], ['07']);
        $this->kegiatan = $this->buatKegiatan($this->psn, [['periode' => 'TAHUNAN', 'periode_ke' => 0, 'pagu_rp' => 10_000_000]], ['nama' => 'Bendungan utama', 'legacy_ref' => 'psn_kegiatan:7011'])->id;
        $this->risiko = DB::table('risiko')->insertGetId(['legacy_id' => 501, 'psn_id' => $this->psn->id, 'uraian' => 'Banjir', 'level_harapan' => 'Sedang']);
        $this->isu = DB::table('isu')->insertGetId(['legacy_id' => 601, 'psn_id' => $this->psn->id, 'uraian' => 'Izin lingkungan', 'status' => 'TERBUKA']);

        // Kit penerima PEKS3 dipasang sebagai rute uji, menulis ke tiruan basis data PEKS3.
        require_once base_path('docs/integrasi-peks3/kit-peks3/app/Http/Controllers/TerimaDariDashboardController.php');
        Route::post('/uji-peks3/api/dashboard/v1/perubahan', [TerimaDariDashboardController::class, 'perubahan']);
        config([
            'services.dashboard_peks3' => ['rahasia' => self::RAHASIA, 'balik_aktif' => true, 'balik_client_id' => 'dashboard-psn', 'balik_koneksi' => 'legacy'],
            'psn_dashboard.peks3.rahasia' => self::RAHASIA,
            'psn_dashboard.peks3.balik.aktif' => true,
            'psn_dashboard.peks3.balik.url' => 'https://peks3.uji/api/dashboard/v1',
        ]);
        Http::fake(['peks3.uji/*' => function (HttpRequest $req) {
            $h = collect(['X-Client-Id', 'X-Timestamp', 'X-Signature', 'Content-Type'])->mapWithKeys(fn ($k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $req->header($k)[0] ?? null])->all();
            $res = $this->call('POST', '/uji-peks3/api/dashboard/v1/perubahan', [], [], [], $h, $req->body());

            return Http::response($res->getContent(), $res->getStatusCode(), ['Content-Type' => 'application/json']);
        }]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::statement('DROP DATABASE IF EXISTS `'.config('database.connections.legacy.database').'`');
        parent::tearDown();
    }

    protected function isi(array $tambahan = []): void
    {
        $this->post('/pengisian/2026-10/'.$this->psn->id, ['ro' => [$this->kegiatan => ['rencana_persen' => 45, 'realisasi_persen' => 40, 'realisasi_anggaran_rp' => 2_000_000, 'permasalahan' => 'Hujan deras']]]
            + ['risiko' => [$this->risiko => ['kemungkinan_aktual' => 4, 'dampak_aktual' => 4, 'status_perlakuan' => 'BERJALAN']],
                'risiko_baru' => ['uraian' => 'Kenaikan harga semen', 'kemungkinan_harapan' => 3, 'dampak_harapan' => 3],
                'isu' => [$this->isu => ['status' => 'PROSES', 'pic_nama' => 'PPK 2', 'tindak_lanjut' => 'Koordinasi KLHK']],
                'isu_baru' => ['uraian' => 'Akses jalan kerja', 'pic_nama' => 'PPK 1', 'tenggat' => '2026-11-01']] + $tambahan)->assertSessionHasNoErrors();
    }

    /** Tiruan impor ulang dari cermin: isian dashboard di tabel data PSN hilang, baris cermin dibuat ulang. */
    protected function imporUlang(): void
    {
        $c = DB::connection('legacy');
        DB::table('kegiatan_target')->where('kegiatan_id', $this->kegiatan)->where('periode', 'BULANAN')->delete();
        $d = $c->table('psn_kegiatan_detil')->where('kegiatan_id', 7011)->where('tahun', 2026)->first();
        foreach (range(1, 12) as $m) {
            if ($d->{"realisasi_persen_{$m}"} !== null || $d->{"target_persen_{$m}"} !== null) {
                DB::table('kegiatan_target')->insert(['kegiatan_id' => $this->kegiatan, 'tahun' => 2026, 'periode' => 'BULANAN', 'periode_ke' => $m,
                    'target_persen' => $d->{"target_persen_{$m}"}, 'realisasi_persen' => $d->{"realisasi_persen_{$m}"}, 'realisasi_anggaran_rp' => $d->{"realisasi_anggaran_{$m}"}]);
            }
        }
        DB::table('risiko_pemantauan')->delete();
        DB::table('risiko')->delete();
        DB::table('isu')->delete();
        foreach ($c->table('psn_risiko')->get() as $r) {
            DB::table('risiko')->insert(['legacy_id' => $r->id, 'psn_id' => $this->psn->id, 'uraian' => $r->risiko, 'level_harapan' => $r->risiko_harapan]);
        }
        foreach ($c->table('psn_kebutuhan')->get() as $r) {
            DB::table('isu')->insert(['legacy_id' => $r->id, 'psn_id' => $this->psn->id, 'uraian' => $r->point, 'status' => 'TERBUKA']);
        }
    }

    public function test_isian_pengisian_dikirim_ke_peks3_dan_tetap_ada_setelah_impor_ulang(): void
    {
        $this->actingAs($u = $this->buatPengguna('Operator K/L', '07'));
        $this->isi();

        $ops = Peks3Keluar::orderBy('id')->get()->keyBy('jenis');
        $this->assertEqualsCanonicalizing(['RO_BULAN', 'RISIKO_PANTAU', 'RISIKO_BARU', 'ISU_UBAH', 'ISU_BARU'], $ops->keys()->all());
        $this->assertEquals(['target_persen_10' => 45, 'realisasi_persen_10' => 40, 'realisasi_anggaran_10' => 2_000_000], $ops['RO_BULAN']->kirim['nilai']);
        $this->assertSame(['risiko_residual' => 'Tinggi', 'status' => 'BERJALAN'], $ops['RISIKO_PANTAU']->kirim['nilai']);

        // Isian kedua sebelum terkirim digabung ke operasi yang sama.
        $this->post('/pengisian/2026-10/'.$this->psn->id, ['ro' => [$this->kegiatan => ['rencana_persen' => 45, 'realisasi_persen' => 42, 'realisasi_anggaran_rp' => 2_000_000, 'permasalahan' => 'Hujan deras']]]);
        $this->assertSame(1, Peks3Keluar::where('jenis', 'RO_BULAN')->count());
        $this->assertEquals(42, Peks3Keluar::where('jenis', 'RO_BULAN')->first()->kirim['nilai']['realisasi_persen_10']);

        // Impor ulang SEBELUM terkirim: isian dashboard dipulihkan, tidak hilang.
        $this->imporUlang();
        app(Peks3KeluarService::class)->pulihkan();
        $t = DB::table('kegiatan_target')->where(['kegiatan_id' => $this->kegiatan, 'periode' => 'BULANAN', 'periode_ke' => 10])->first();
        $this->assertSame([42.0, 'Hujan deras'], [(float) $t->realisasi_persen, $t->permasalahan]);
        $this->assertSame('Tinggi', DB::table('risiko_pemantauan')->where('tanggal', '2026-10-31')->value('level_aktual'));
        $this->assertTrue(DB::table('risiko')->where('uraian', 'Kenaikan harga semen')->exists());
        $this->assertSame('PROSES', DB::table('isu')->where('legacy_id', 601)->value('status'));
        $this->assertTrue(DB::table('isu')->where('uraian', 'Akses jalan kerja')->exists());

        // Kirim ke PEKS3 (kit penerima): ditulis ke basis data PEKS3.
        $this->artisan('peks3:kirim-balik')->assertSuccessful();
        $c = DB::connection('legacy');
        $d = $c->table('psn_kegiatan_detil')->where('id', 1)->first();
        $this->assertSame([42.0, 45.0, 30.0], [(float) $d->realisasi_persen_10, (float) $d->target_persen_10, (float) $d->realisasi_persen_9]);
        $this->assertSame('dashboard:'.$u->username, $d->updated_by);
        $this->assertSame(['Tinggi', 'BERJALAN'], [$c->table('psn_risiko')->where('id', 501)->value('risiko_residual'), $c->table('psn_risiko')->where('id', 501)->value('status')]);
        $this->assertTrue($c->table('psn_risiko')->where('risiko', 'Kenaikan harga semen')->where('risiko_harapan', 'Sedang')->exists());
        $this->assertTrue($c->table('psn_kebutuhan')->where('point', 'Akses jalan kerja')->where('jenis', 'A')->exists());
        $st = Peks3Keluar::pluck('status', 'jenis');
        $this->assertSame('DITERIMA', $st['RO_BULAN']);
        $this->assertSame('DITERIMA', $st['ISU_BARU']);      // diterima, kolom status/pic dilewati (belum ada di PEKS3)
        $this->assertSame('DITOLAK', $st['ISU_UBAH']);       // seluruh kolomnya belum ada di PEKS3
        $this->assertStringContainsString('belum ada', Peks3Keluar::where('jenis', 'ISU_UBAH')->value('pesan'));

        // Data kembali lewat sinkron PEKS3 -> dashboard: operasi yang sudah tercermin SELESAI, tanpa duplikasi.
        $this->imporUlang();
        $h = app(Peks3KeluarService::class)->pulihkan();
        $this->assertGreaterThanOrEqual(3, $h['selesai']);
        $this->assertSame('SELESAI', Peks3Keluar::where('jenis', 'RO_BULAN')->value('status'));
        $this->assertSame(1, DB::table('risiko')->where('uraian', 'Kenaikan harga semen')->count());
        $this->assertSame(1, DB::table('isu')->where('uraian', 'Akses jalan kerja')->count());
        // Kolom khusus dashboard tetap: catatan permasalahan & riwayat pemantauan per cut-off; isu yang belum bisa disimpan PEKS3 tetap dipertahankan.
        $this->assertSame('Hujan deras', DB::table('kegiatan_target')->where(['kegiatan_id' => $this->kegiatan, 'periode_ke' => 10])->value('permasalahan'));
        $this->assertSame(4, (int) DB::table('risiko_pemantauan')->where('tanggal', '2026-10-31')->value('kemungkinan_aktual'));
        $this->assertSame('PROSES', DB::table('isu')->where('legacy_id', 601)->value('status'));
    }

    public function test_konflik_bila_peks3_sudah_mengubah_data_yang_sama(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->post('/pengisian/2026-10/'.$this->psn->id, ['risiko' => [$this->risiko => ['kemungkinan_aktual' => 2, 'dampak_aktual' => 2, 'status_perlakuan' => 'SELESAI']]])->assertSessionHasNoErrors();
        // Operator PEKS3 mengubah residual risiko yang sama sebelum kiriman dashboard tiba.
        DB::connection('legacy')->table('psn_risiko')->where('id', 501)->update(['risiko_residual' => 'Sangat Tinggi']);

        $this->artisan('peks3:kirim-balik')->assertSuccessful();
        $op = Peks3Keluar::where('jenis', 'RISIKO_PANTAU')->firstOrFail();
        $this->assertSame('KONFLIK', $op->status);
        $this->assertSame('Sangat Tinggi', DB::connection('legacy')->table('psn_risiko')->where('id', 501)->value('risiko_residual'));

        // Super Admin memutuskan di Pengaturan > Integrasi Data: timpa PEKS3.
        $this->actingAs($this->buatPengguna('Super Admin'));
        $this->get('/pengaturan/integrasi')->assertOk()->assertSee('Kiriman Balik Dashboard ke SIRENDA PEKS3')->assertSee('Timpa PEKS3');
        $this->post(route('pengaturan.integrasi.balik-ulang', $op->id), ['timpa' => 1])->assertRedirect();
        $this->artisan('peks3:kirim-balik')->assertSuccessful();
        $this->assertSame('DITERIMA', $op->fresh()->status);
        $this->assertSame('Rendah', DB::connection('legacy')->table('psn_risiko')->where('id', 501)->value('risiko_residual'));
        $this->assertTrue(DB::table('audit_log')->where('tabel', 'peks3_keluar')->where('record_id', $op->id)->exists());
    }

    public function test_penerima_menolak_tanda_tangan_salah_dan_pengirim_mencoba_ulang(): void
    {
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->post('/pengisian/2026-10/'.$this->psn->id, ['ro' => [$this->kegiatan => ['realisasi_persen' => 41]]])->assertSessionHasNoErrors();
        config(['psn_dashboard.peks3.rahasia' => str_repeat('x', 40)]); // kunci berbeda dengan PEKS3

        $this->artisan('peks3:kirim-balik')->assertFailed();
        $op = Peks3Keluar::firstOrFail();
        $this->assertSame(['ANTRE', 1], [$op->status, $op->percobaan]);
        $this->assertStringContainsString('HTTP 401', $op->pesan);
        $this->assertNotNull($op->berikutnya_at);
        $this->assertNull(DB::connection('legacy')->table('psn_kegiatan_detil')->where('id', 1)->value('realisasi_persen_10'));

        // Penerimaan nonaktif di dashboard: antrean tetap dicatat, tidak dikirim.
        config(['psn_dashboard.peks3.balik.aktif' => false]);
        $this->artisan('peks3:kirim-balik')->expectsOutputToContain('NONAKTIF')->assertSuccessful();
    }

    public function test_isu_terkirim_setelah_peks3_menambah_kolom_pemantauan_isu(): void
    {
        // Migrasi §9.5 di SIRENDA PEKS3.
        Schema::connection('legacy')->table('psn_kebutuhan', function (Blueprint $t) {
            $t->string('status', 20)->nullable();
            $t->string('pic_nama', 255)->nullable();
            $t->date('tenggat')->nullable();
            $t->text('tindak_lanjut')->nullable();
            $t->date('tanggal_selesai')->nullable();
        });
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->post('/pengisian/2026-10/'.$this->psn->id, ['isu' => [$this->isu => ['status' => 'SELESAI', 'pic_nama' => 'PPK 2', 'tindak_lanjut' => 'Izin terbit']]])->assertSessionHasNoErrors();
        $this->artisan('peks3:kirim-balik')->assertSuccessful();

        $this->assertSame('DITERIMA', Peks3Keluar::where('jenis', 'ISU_UBAH')->value('status'));
        $r = DB::connection('legacy')->table('psn_kebutuhan')->where('id', 601)->first();
        $this->assertSame(['SELESAI', 'PPK 2', 'Izin terbit', '2026-10-08'], [$r->status, $r->pic_nama, $r->tindak_lanjut, $r->tanggal_selesai]);
        $this->assertTrue(app(Peks3KeluarService::class)->tercermin(Peks3Keluar::where('jenis', 'ISU_UBAH')->firstOrFail()));
    }
}
