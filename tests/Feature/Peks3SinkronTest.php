<?php

namespace Tests\Feature;

use App\Services\Peks3TerapkanService;
use App\Support\DashboardCache;
use App\Support\Legacy\LegacyImporter;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Sinkron SIRENDA PEKS3 -> dashboard (docs/integrasi-peks3.md): tanda tangan HMAC, paket per PSN
 * ke basis data cermin, dan penerapan atomik. Cermin memakai basis data uji terpisah.
 */
class Peks3SinkronTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected int $urutan = 0;

    protected const RAHASIA = 'rahasia-uji-peks3-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $db = config('database.connections.mysql.database').'_cermin';
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$db}`");
        config(['database.connections.legacy' => array_merge(config('database.connections.mysql'), ['database' => $db])]);
        DB::purge('legacy');
        $s = Schema::connection('legacy');
        foreach (['peks3_cermin', 'psn', 'psn_kegiatan', 'psn_kebutuhan', 'monev_header', 'monev_detil', 'users'] as $t) {
            $s->dropIfExists($t);
        }
        $s->create('peks3_cermin', fn (Blueprint $t) => $t->id());
        $s->create('psn', function (Blueprint $t) {
            $t->integer('id')->primary();
            $t->string('kode_krisna', 50); // NOT NULL berisi '' di PEKS3
            $t->text('proyek_psn');
            $t->timestamp('deleted_at')->nullable();
        });
        $s->create('psn_kegiatan', fn (Blueprint $t) => [$t->integer('id')->primary(), $t->integer('psn_id'), $t->text('kegiatan')]);
        $s->create('psn_kebutuhan', fn (Blueprint $t) => [$t->integer('id')->primary(), $t->integer('psn_id'), $t->text('point')]);
        $s->create('monev_header', fn (Blueprint $t) => [$t->integer('id')->primary(), $t->integer('psn_id')]);
        $s->create('monev_detil', fn (Blueprint $t) => [$t->integer('id')->primary(), $t->integer('monev_id'), $t->text('jawaban')->nullable()]);
        $s->create('users', fn (Blueprint $t) => [$t->string('username', 50)->primary(), $t->string('password', 255), $t->string('nama_pengguna', 100)->nullable()]);
        config(['psn_dashboard.peks3.aktif' => true, 'psn_dashboard.peks3.rahasia' => self::RAHASIA]);
    }

    protected function tearDown(): void
    {
        DB::statement('DROP DATABASE IF EXISTS `'.config('database.connections.legacy.database').'`');
        parent::tearDown();
    }

    protected function kirim(string $path, array $body, array $ubahHeader = []): TestResponse
    {
        $json = json_encode($body);
        // Stempel waktu unik per kiriman agar kiriman ulang berisi sama tidak dianggap replay (409).
        $t = (string) (time() + ($this->urutan++ % 200));
        $h = array_merge(['X-Client-Id' => 'sirenda-peks3', 'X-Timestamp' => $t, 'Content-Type' => 'application/json', 'Accept' => 'application/json'], $ubahHeader);
        $h['X-Signature'] ??= 'sha256='.hash_hmac('sha256', "{$h['X-Timestamp']}.{$json}", self::RAHASIA);

        return $this->call('POST', "/api/integrasi/peks3/v1/{$path}", [], [], [], collect($h)->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(), $json);
    }

    protected function paket(int $id, string $versi, array $tambah = []): array
    {
        return ['psn_id' => $id, 'versi' => $versi, 'tabel' => array_merge([
            'psn' => [['id' => $id, 'kode_krisna' => '', 'proyek_psn' => "Proyek {$id}", 'deleted_at' => null]],
            'psn_kegiatan' => [['id' => $id * 10 + 1, 'psn_id' => $id, 'kegiatan' => 'KP 1'], ['id' => $id * 10 + 2, 'psn_id' => $id, 'kegiatan' => 'KP 2']],
            'psn_kebutuhan' => [['id' => $id * 10 + 1, 'psn_id' => $id, 'point' => '  isu dengan spasi  ']],
        ], $tambah)];
    }

    public function test_autentikasi_hmac_waktu_replay_dan_ip(): void
    {
        $body = $this->paket(7, '2026-11-14T08:00:00Z');
        config(['psn_dashboard.peks3.aktif' => false]);
        $this->kirim('psn', $body)->assertStatus(503);
        config(['psn_dashboard.peks3.aktif' => true]);

        $this->kirim('psn', $body, ['X-Client-Id' => 'lain'])->assertStatus(401);
        $this->kirim('psn', $body, ['X-Signature' => 'sha256=salah'])->assertStatus(401);
        $lama = (string) (time() - 3600);
        $this->kirim('psn', $body, ['X-Timestamp' => $lama, 'X-Signature' => 'sha256='.hash_hmac('sha256', $lama.'.'.json_encode($body), self::RAHASIA)])
            ->assertStatus(401)->assertJsonPath('message', fn ($m) => str_contains($m, 'jam server'));

        $t = (string) time();
        $tanda = ['X-Timestamp' => $t, 'X-Signature' => 'sha256='.hash_hmac('sha256', $t.'.'.json_encode($body), self::RAHASIA)];
        $this->kirim('psn', $body, $tanda)->assertStatus(202);
        $this->kirim('psn', $body, $tanda)->assertStatus(409); // replay

        config(['psn_dashboard.peks3.ip_diizinkan' => ['10.0.0.9']]);
        $this->kirim('psn', $this->paket(8, '2026-11-14T08:00:00Z'))->assertStatus(403);
    }

    public function test_paket_mengganti_baris_psn_dan_mempertahankan_string_kosong(): void
    {
        $this->kirim('psn', ['paket' => [$this->paket(7, '2026-11-14T08:00:00Z'), $this->paket(9, '2026-11-14T08:00:00Z')]])
            ->assertStatus(202)->assertJsonPath('data.0.hasil', 'DITERIMA')->assertJsonPath('data.0.baris.psn_kegiatan', 2);
        $c = DB::connection('legacy');
        $this->assertSame('', $c->table('psn')->where('id', 7)->value('kode_krisna'));
        $this->assertSame('  isu dengan spasi  ', $c->table('psn_kebutuhan')->where('psn_id', 7)->value('point'));

        // Versi baru: satu KP dihapus fisik di PEKS3, tabel yang tidak dikirim tidak disentuh, PSN lain aman.
        $p = $this->paket(7, '2026-11-14T09:00:00Z');
        $p['tabel']['psn_kegiatan'] = [['id' => 71, 'psn_id' => 7, 'kegiatan' => 'KP 1 (ubah)']];
        unset($p['tabel']['psn_kebutuhan']);
        $p['tabel']['tabel_asing'] = [];
        $p['tabel']['psn'][0]['kolom_baru'] = 'x';
        $this->kirim('psn', $p)->assertStatus(202)->assertJsonPath('data.0.peringatan', fn ($w) => count($w) === 2);
        $this->assertSame(['KP 1 (ubah)'], $c->table('psn_kegiatan')->where('psn_id', 7)->pluck('kegiatan')->all());
        $this->assertSame(1, $c->table('psn_kebutuhan')->where('psn_id', 7)->count());
        $this->assertSame(2, $c->table('psn_kegiatan')->where('psn_id', 9)->count());

        // Isi sama -> TETAP; versi lebih lama -> diabaikan.
        $this->kirim('psn', $p)->assertJsonPath('data.0.hasil', 'TETAP');
        $this->kirim('psn', $this->paket(7, '2026-11-14T07:00:00Z'))->assertJsonPath('data.0.hasil', 'VERSI_LAMA');
        $this->assertSame(1, $c->table('psn_kegiatan')->where('psn_id', 7)->count());

        $this->assertNotNull(DB::table('integrasi_peks3_status')->value('tertunda_sejak'));
        $this->assertSame(['DITERIMA', 'DITERIMA', 'DITERIMA', 'TETAP', 'VERSI_LAMA'], DB::table('integrasi_peks3_log')->orderBy('id')->pluck('hasil')->all());
    }

    public function test_validasi_kepemilikan_baris_dan_monev(): void
    {
        $p = $this->paket(7, '2026-11-14T08:00:00Z');
        $p['tabel']['psn_kegiatan'][0]['psn_id'] = 8;
        $this->kirim('psn', $p)->assertStatus(422)->assertJsonPath('data.0.pesan', fn ($m) => str_contains($m, 'psn_kegiatan[0].psn_id harus bernilai 7'));
        $this->assertSame(0, DB::connection('legacy')->table('psn')->count()); // tidak ada yang ditulis

        $this->kirim('psn', ['psn_id' => 7, 'versi' => 'x', 'tabel' => []])->assertStatus(422);

        $ok = $this->paket(7, '2026-11-14T08:00:00Z', ['monev_header' => [['id' => 5, 'psn_id' => 7]], 'monev_detil' => [['id' => 50, 'monev_id' => 5, 'jawaban' => 'Ya']]]);
        $this->kirim('psn', $ok)->assertStatus(202);
        $salah = $this->paket(7, '2026-11-14T09:00:00Z', ['monev_header' => [], 'monev_detil' => [['id' => 51, 'monev_id' => 99]]]);
        $this->kirim('psn', $salah)->assertStatus(422);
        // monev_header dihapus di PEKS3 -> anaknya ikut terhapus walau monev_detil tidak dikirim.
        $this->kirim('psn', $this->paket(7, '2026-11-14T10:00:00Z', ['monev_header' => []]))->assertStatus(202);
        $this->assertSame(0, DB::connection('legacy')->table('monev_detil')->count());
    }

    public function test_hapus_rekonsiliasi_referensi_dan_status(): void
    {
        foreach ([7, 8, 9, 10, 11, 12] as $id) {
            $this->kirim('psn', $this->paket($id, '2026-11-14T08:00:00Z'))->assertStatus(202);
        }
        $c = DB::connection('legacy');

        $this->kirim('psn', ['psn_id' => 12, 'versi' => '2026-11-14T09:00:00Z', 'dihapus' => true])->assertStatus(202);
        $this->assertSame(0, $c->table('psn_kegiatan')->where('psn_id', 12)->count());

        // Daftar ID terpotong (akan menghapus > 20%) ditolak; daftar wajar menghapus PSN yang hilang.
        $this->kirim('rekonsiliasi', ['psn_id' => [7, 8]])->assertStatus(422);
        $this->kirim('rekonsiliasi', ['psn_id' => [7, 8, 9, 10]])->assertStatus(202)->assertJsonPath('data.dihapus', [11]);
        $this->assertSame([7, 8, 9, 10], $c->table('psn')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all());

        // Referensi: upsert, kata sandi tidak pernah disimpan.
        $this->kirim('referensi', ['tabel' => 'users', 'baris' => [['username' => 'budi', 'password' => '$2y$rahasia', 'nama_pengguna' => 'Budi']]])->assertStatus(202);
        $this->kirim('referensi', ['tabel' => 'users', 'baris' => [['username' => 'budi', 'nama_pengguna' => 'Budi S.']]])->assertStatus(202);
        $this->assertSame(['', 'Budi S.'], array_values((array) $c->table('users')->where('username', 'budi')->first(['password', 'nama_pengguna'])));
        $this->kirim('referensi', ['tabel' => 'sessions', 'baris' => []])->assertStatus(422);

        $t = (string) time();
        $this->call('GET', '/api/integrasi/peks3/v1/status', [], [], [], ['HTTP_X_CLIENT_ID' => 'sirenda-peks3', 'HTTP_X_TIMESTAMP' => $t,
            'HTTP_X_SIGNATURE' => 'sha256='.hash_hmac('sha256', "{$t}.", self::RAHASIA), 'HTTP_ACCEPT' => 'application/json'])
            ->assertOk()->assertJsonPath('data.tertunda', true)->assertJsonPath('data.psn_di_cermin', 4);
    }

    public function test_cermin_wajib_ditandai(): void
    {
        Schema::connection('legacy')->drop('peks3_cermin');
        $this->kirim('psn', $this->paket(7, '2026-11-14T08:00:00Z'))->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'peks3:siapkan-cermin'));
    }

    public function test_terapkan_atomik_menunggu_jeda_dan_membatalkan_cache(): void
    {
        $catat = new \ArrayObject;
        $palsu = new class($catat) extends LegacyImporter
        {
            public function __construct(public \ArrayObject $catat) {}

            public function kosongkan(bool $transaksional = false, bool $pertahankanUsulan = false): void
            {
                $this->catat[] = ['kosongkan', $transaksional, $pertahankanUsulan, DB::transactionLevel()];
            }

            public function jalankan(array $langkah = self::LANGKAH): array
            {
                $this->catat[] = ['jalankan', $langkah];

                return ['jumlah' => [], 'peringatan' => ['x' => ['a', 'b']]];
            }
        };
        $this->app->instance(LegacyImporter::class, $palsu);
        $layanan = app(Peks3TerapkanService::class);

        $this->assertFalse($layanan->perlu());
        $this->kirim('psn', $this->paket(7, '2026-11-14T08:00:00Z'))->assertStatus(202);
        $this->assertFalse($layanan->perlu()); // jeda 60 detik belum lewat
        $this->travel(61)->seconds();
        $this->assertTrue($layanan->perlu());

        $versiCache = DashboardCache::versi();
        $level = DB::transactionLevel();
        $h = $layanan->terapkan();
        $this->assertSame('BERHASIL', $h['status']);
        $this->assertSame(2, $h['peringatan']);
        $this->assertSame(['kosongkan', true, true, $level + 1], $catat[0]); // di dalam transaksi
        $this->assertNotContains('usulan', $catat[1][1]);
        $this->assertGreaterThan($versiCache, DashboardCache::versi());
        $this->assertNull(DB::table('integrasi_peks3_status')->value('tertunda_sejak'));
        $this->assertFalse($layanan->perlu());
        $this->assertTrue(DB::table('audit_log')->where('tabel', 'integrasi_peks3')->exists());

        $this->artisan('peks3:terapkan')->expectsOutputToContain('Tidak ada perubahan')->assertSuccessful();
    }

    public function test_halaman_integrasi_data_memantau_dan_menjadwalkan_penerapan(): void
    {
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        $this->kirim('psn', $this->paket(7, '2026-11-14T08:00:00Z'))->assertStatus(202);
        $this->kirim('psn', ['psn_id' => 8, 'versi' => '2026-11-14T08:00:00Z', 'tabel' => ['psn' => [['id' => 9]]]])->assertStatus(422);

        $this->actingAs($this->buatPengguna('Pimpinan'))->get(route('pengaturan.integrasi'))->assertForbidden();

        $admin = $this->buatPengguna('Super Admin');
        $this->actingAs($admin)->get(route('pengaturan.integrasi'))->assertOk()
            ->assertSee('Sinkron SIRENDA PEKS3 ke Dashboard')->assertSee('Menunggu diterapkan')->assertSee('Terpasang')
            ->assertSee('Gagal atau ditolak')->assertSee('4 baris')->assertDontSee(self::RAHASIA);
        $this->get(route('pengaturan.integrasi', ['hasil' => 'GAGAL']))->assertOk()->assertSee('paket[0]')->assertDontSee('4 baris');

        // Penjadwal belum pernah berjalan -> ditandai; setelah perintah berjalan -> detak tercatat.
        $this->get(route('pengaturan.integrasi'))->assertSee('Tidak berjalan');
        $layanan = app(Peks3TerapkanService::class);
        $this->assertFalse($layanan->perlu());
        $this->post(route('pengaturan.integrasi.terapkan'))->assertRedirect()->assertSessionHas('status');
        $this->assertTrue($layanan->perlu()); // tanpa menunggu jeda
        $this->assertTrue(DB::table('audit_log')->where('tabel', 'integrasi_peks3')->where('user_id', $admin->id)->exists());

        config(['psn_dashboard.peks3.aktif' => false]);
        $this->post(route('pengaturan.integrasi.terapkan'))->assertStatus(422);
    }
}
