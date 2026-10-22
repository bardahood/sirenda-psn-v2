<?php

namespace Tests\Feature;

use App\Models\Penilaian;
use App\Models\Pleno;
use App\Models\PlenoUsulan;
use App\Models\UsulanPsi;
use App\Models\UsulanPsn;
use App\Models\UsulanRekomendasi;
use App\Support\JwtRs256;
use Database\Seeders\PeranSeeder;
use Database\Seeders\ReferensiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\BuatDataPsn;
use Tests\TestCase;

/**
 * Alur usulan PSI ujung-ke-ujung: (1-3) PSI mengirim usulan lewat API, tampil di Perencanaan;
 * (4) penilaian; (5) pleno; (6) rekomendasi resmi; (7) hasil dikirim / diambil PSI.
 */
class IntegrasiPsiTest extends TestCase
{
    use BuatDataPsn, RefreshDatabase;

    protected const TOKEN = 'token-uji-psi-0123456789';

    protected array $k;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ReferensiSeeder::class, PeranSeeder::class]);
        config(['psn_dashboard.psi.token_masuk' => self::TOKEN, 'psn_dashboard.psi.url_api' => null, 'psn_dashboard.psi.ip_diizinkan' => []]);
        $this->k = DB::table('ref_kriteria')->pluck('id', 'kode')->all();
    }

    protected function payload(array $timpa = []): array
    {
        $p = json_decode(file_get_contents(base_path('docs/contoh/psi-usulan.json')), true);
        $p['data_umum']['klaster'] = DB::table('ref_klaster')->where('kode', 'R.4')->value('nama');

        return array_replace_recursive($p, $timpa);
    }

    protected function kirim(array $payload, ?string $token = self::TOKEN)
    {
        return $this->withHeaders($token ? ['Authorization' => "Bearer {$token}"] : [])->postJson('/api/integrasi/psi/v1/usulan', $payload);
    }

    protected function usulan(): UsulanPsn
    {
        return UsulanPsn::withoutGlobalScopes()->findOrFail(UsulanPsi::where('psi_id', 'CONTOH-USL-2026-0001')->value('usulan_id'));
    }

    public function test_api_masuk_wajib_token_dan_dapat_dibatasi_ip(): void
    {
        $this->kirim($this->payload(), null)->assertUnauthorized();
        $this->kirim($this->payload(), 'salah')->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])->getJson('/api/integrasi/psi/v1/referensi')->assertOk()->assertJsonStructure(['data' => ['klaster', 'provinsi', 'keputusan_pleno']]);

        config(['psn_dashboard.psi.ip_diizinkan' => ['10.1.2.3']]);
        $this->kirim($this->payload())->assertForbidden();

        config(['psn_dashboard.psi.token_masuk' => null]);
        $this->kirim($this->payload())->assertStatus(503);
        $this->assertSame(0, UsulanPsn::withoutGlobalScopes()->count());
    }

    public function test_api_masuk_dengan_jwt_rs256_client_id(): void
    {
        $kunci = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($kunci, $privat);
        $publik = tempnam(sys_get_temp_dir(), 'psi').'.pem';
        file_put_contents($publik, openssl_pkey_get_details($kunci)['key']);
        config(['psn_dashboard.psi.token_masuk' => null, 'psn_dashboard.psi.kunci_publik' => $publik, 'psn_dashboard.psi.client_id' => 'konsultan-psn-uji']);

        $kini = time();
        $klaim = fn (array $timpa = []) => $timpa + ['iss' => 'konsultan-psn-uji', 'iat' => $kini, 'exp' => $kini + 120, 'jti' => (string) Str::uuid()];
        $jwt = JwtRs256::buat($klaim(), $privat);

        $this->kirim($this->payload(), $jwt)->assertCreated();
        $this->kirim($this->payload(), $jwt)->assertUnauthorized()->assertJsonFragment(['message' => 'Token integrasi tidak valid: token sudah pernah dipakai (jti).']);
        $this->kirim($this->payload(), JwtRs256::buat($klaim(['iss' => 'klien-lain']), $privat))->assertUnauthorized();
        $this->kirim($this->payload(), JwtRs256::buat($klaim(['exp' => $kini - 600, 'iat' => $kini - 700]), $privat))->assertUnauthorized();
        $this->kirim($this->payload(), JwtRs256::buat($klaim(['exp' => $kini + 86400]), $privat))->assertUnauthorized();
        $this->kirim($this->payload(), JwtRs256::buat(array_diff_key($klaim(), ['jti' => 1]), $privat))->assertUnauthorized();

        // Ditandatangani kunci lain, atau alg diganti (HS256/none) -> ditolak.
        openssl_pkey_export(openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]), $privatLain);
        $this->kirim($this->payload(), JwtRs256::buat($klaim(), $privatLain))->assertUnauthorized();
        [, $p, $sig] = explode('.', JwtRs256::buat($klaim(), $privat));
        $this->kirim($this->payload(), rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=').'.'.$p.'.'.$sig)->assertUnauthorized();

        // Kunci publik tidak terbaca dan token statis kosong -> API nonaktif.
        config(['psn_dashboard.psi.kunci_publik' => '/tidak/ada.pem']);
        $this->kirim($this->payload(), JwtRs256::buat($klaim(), $privat))->assertStatus(503);
        @unlink($publik);
    }

    public function test_terima_usulan_idempoten_versi_dan_validasi(): void
    {
        $r = $this->kirim($this->payload(['rincian' => ['lingkup_pekerjaan_html' => '<ul><li>Konstruksi</li></ul><script>alert(1)</script><img src=x onerror=alert(1)>']]))
            ->assertCreated()->assertJsonPath('data.status', 'DITERIMA');
        $u = $this->usulan();
        $this->assertSame($r->json('data.sirenda_id'), $u->id);
        $this->assertSame(['CONTOH — Pembangunan Jalan Tol Lingkar Kota A', 'KL', true, 2027], [$u->nama, $u->jenis_pengusul, $u->is_infrastruktur, $u->tahun_rkp]);
        $this->assertSame($this->ref('ref_klaster', 'R.4'), $u->klaster_id);
        $this->assertSame(['32'], $u->lokasi()->pluck('provinsi_kode')->all());
        $this->assertSame(5, $u->jalurKritis()->count());
        $this->assertSame(3, $u->dokumen()->count());
        $this->assertSame('001/DC/IX/2026', $u->psi->surat->nomor);
        // HTML editor PSI disaring (XSS).
        $this->assertSame('<ul><li>Konstruksi</li></ul>', $u->psi->lingkup_pekerjaan_html);

        // Versi sama: diabaikan; versi naik: diperbarui & isian anak diganti.
        $this->kirim($this->payload())->assertOk()->assertJsonPath('data.status', 'DIABAIKAN_VERSI_LAMA');
        $v2 = $this->payload(['versi' => 2, 'data_umum' => ['nama' => 'Jalan Tol Revisi']]);
        $v2['kesiapan']['jalur_kritis'] = [['kode' => 'A', 'kegiatan' => 'FS', 'sebelumnya' => ['Z']]];
        $this->kirim($v2)->assertOk()->assertJsonPath('data.status', 'DIPERBARUI');
        $u->refresh();
        $this->assertSame(['Jalan Tol Revisi', 2], [$u->nama, $u->psi->versi]);
        $this->assertSame(['A'], $u->jalurKritis()->pluck('kode')->all());
        $this->assertSame(1, UsulanPsn::withoutGlobalScopes()->count());
        $this->assertNotEmpty($u->psi->peringatan_impor); // pendahulu "Z" tidak ada

        // Validasi -> 422 JSON, tercatat di log integrasi.
        $this->kirim(['psi_id' => 'X 1', 'versi' => 0])->assertUnprocessable()->assertJsonValidationErrors(['psi_id', 'versi', 'tahun_rkp', 'pengusul', 'data_umum']);
        $this->assertTrue(DB::table('integrasi_psi_log')->where('berhasil', false)->where('status_http', 422)->exists());
        // Satu entri audit ringkas per kiriman, bersumber INTEGRASI_PSI.
        $this->assertSame(2, DB::table('audit_log')->where('sumber', 'INTEGRASI_PSI')->count());
        $this->assertSame(0, DB::table('audit_log')->where('tabel', 'usulan_jalur_kritis')->count());
    }

    public function test_nilai_tak_terpetakan_menjadi_peringatan(): void
    {
        $this->kirim($this->payload(['pengusul' => ['jenis_instansi' => 'Badan Lain'], 'data_umum' => ['klaster' => 'Klaster Fiktif', 'lokasi' => [['provinsi_kode' => '99']]]]))
            ->assertCreated()->assertJsonCount(3, 'data.peringatan');
        $u = $this->usulan();
        $this->assertNull($u->klaster_id);
        $this->assertNull($u->jenis_pengusul);
        $this->assertSame(0, $u->lokasi()->count());

        // PMO menetapkan klaster & jenis pengusul; versi baru yang tetap tak terpetakan tidak menimpanya.
        $klaster = DB::table('ref_klaster')->where('kode', 'R.4')->value('id');
        $u->forceFill(['klaster_id' => $klaster, 'jenis_pengusul' => 'KL', 'is_infrastruktur' => true])->save();
        $this->kirim($this->payload(['versi' => 2, 'pengusul' => ['jenis_instansi' => 'Badan Lain'], 'data_umum' => ['klaster' => 'Klaster Fiktif', 'jenis_psn' => null]]))
            ->assertOk()->assertJsonPath('data.status', 'DIPERBARUI');
        $u->refresh();
        $this->assertSame($klaster, $u->klaster_id);
        $this->assertSame('KL', $u->jenis_pengusul);
        $this->assertTrue((bool) $u->is_infrastruktur);
    }

    public function test_alur_lengkap_penilaian_pleno_rekomendasi_kirim(): void
    {
        $this->kirim($this->payload())->assertCreated();
        $u = $this->usulan();
        $pmo = $this->buatPengguna('Tim Koordinasi/PMO');
        $dir = $this->buatPengguna('Direktorat Sektor', '19');

        // (3) Tampil di Perencanaan dengan tab isian PSI.
        $this->actingAs($pmo);
        $this->get('/perencanaan?sumber=PSI')->assertOk()->assertSee('CONTOH — Pembangunan Jalan Tol')->assertSee('CONTOH-USL-2026-0001');
        foreach (['surat' => '001/DC/IX/2026', 'umum' => 'Jalan dan Jembatan', 'rincian' => 'Pengadaan tanah 320 ha', 'kesiapan' => 'Penyusunan AMDAL', 'dokumen' => 'Studi Kelayakan'] as $tab => $teks) {
            $this->get("/perencanaan/{$u->id}?tab={$tab}")->assertOk()->assertSee($teks);
        }

        // PMO menetapkan direktorat pengampu -> Direktorat 19 berwenang menilai.
        $this->put(route('perencanaan.pemetaan', $u), ['unit_kerja_id' => $this->ref('ref_unit_kerja', '19'), 'klaster_id' => $u->klaster_id, 'jenis_pengusul' => 'KL', 'is_infrastruktur' => 1])->assertRedirect();

        // (4) Penilaian oleh direktorat pengampu.
        $this->actingAs($dir);
        $this->post(route('perencanaan.penilaian.store', $u), ['forum' => 'Penilaian Usulan RKP 2027', 'tanggal' => '2026-10-20'])->assertRedirect();
        $p = Penilaian::where('usulan_id', $u->id)->firstOrFail();
        $nilai = collect(['KU1' => 1, 'KU2' => 1, 'KU3' => 1, 'KP1' => 3, 'KP2' => 2, 'KP3' => 3, 'KP4' => 1, 'KK1' => 3, 'KK2' => 3, 'KK3' => 2, 'KK4' => 1, 'KK5' => 3, 'KL1' => 2, 'KT1' => 3])
            ->mapWithKeys(fn ($n, $kode) => [$this->k[$kode] => $n])->all();
        $this->put(route('perencanaan.penilaian.skor', $p), ['nilai' => $nilai])->assertRedirect();
        $this->post(route('perencanaan.penilaian.final', $p))->assertRedirect();

        // (6) Rekomendasi ditolak sebelum ada keputusan pleno.
        $this->actingAs($pmo);
        $this->post(route('perencanaan.rekomendasi', $u), ['rekomendasi' => 'DIREKOMENDASIKAN', 'tanggal' => '2026-10-25', 'tetapkan' => 1])->assertSessionHasErrors('rekomendasi');

        // (5) Pleno: jadwal, agenda, keputusan (catatan wajib selain "Disetujui"), selesai.
        $this->post(route('perencanaan.pleno.store'), ['nama' => 'Rapat Pleno II', 'tanggal' => '2026-10-24', 'tahun_rkp' => 2027])->assertRedirect();
        $pleno = Pleno::firstOrFail();
        $this->get(route('perencanaan.pleno.show', $pleno))->assertOk()->assertSee('CONTOH — Pembangunan Jalan Tol');
        $this->post(route('perencanaan.pleno.agenda', $pleno), ['usulan_id' => [$u->id]])->assertRedirect();
        $a = $pleno->agenda()->firstOrFail();
        $this->assertSame($p->id, $a->penilaian_id);
        $this->post(route('perencanaan.pleno.selesai', $pleno))->assertSessionHasErrors('pleno');
        $this->put(route('perencanaan.pleno.keputusan', $pleno), ['agenda' => [$a->id => ['keputusan' => 'DISETUJUI_CATATAN']]])->assertSessionHasErrors();
        $this->put(route('perencanaan.pleno.keputusan', $pleno), ['agenda' => [$a->id => ['keputusan' => 'DISETUJUI_CATATAN', 'catatan' => 'Lengkapi dokumen AMDAL.', 'tindak_lanjut' => 'Dit. PSI menyurati pengusul']]])->assertSessionHasNoErrors();
        $this->post(route('perencanaan.pleno.selesai', $pleno))->assertSessionHasNoErrors();
        $this->put(route('perencanaan.pleno.keputusan', $pleno), ['agenda' => [$a->id => ['keputusan' => 'DITOLAK', 'catatan' => 'x']]])->assertStatus(409);

        // (6) Rekomendasi resmi.
        $this->post(route('perencanaan.rekomendasi', $u), ['rekomendasi' => 'DIREKOMENDASIKAN', 'tanggal' => '2026-10-25', 'catatan' => 'Direkomendasikan dengan catatan pleno.', 'nomor_dokumen' => 'BA-01/2026', 'tetapkan' => 1])->assertSessionHasNoErrors();
        $rek = UsulanRekomendasi::where('usulan_id', $u->id)->firstOrFail();
        $this->assertSame(['FINAL', $pmo->id], [$rek->status, $rek->ditetapkan_oleh]);
        $this->get("/perencanaan/{$u->id}?tab=rekomendasi")->assertOk()->assertSee('Rekomendasi Resmi')->assertSee('PSI_API_URL');

        // (7a) Pull: PSI mengambil hasil.
        $h = $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])->getJson('/api/integrasi/psi/v1/usulan/CONTOH-USL-2026-0001/hasil')->assertOk();
        $h->assertJsonPath('data.tahap', 'REKOMENDASI')->assertJsonPath('data.rekomendasi.nilai', 'DIREKOMENDASIKAN')
            ->assertJsonPath('data.penilaian.nilai_akhir', 79.25)->assertJsonPath('data.penilaian.gate_lulus', true)
            ->assertJsonPath('data.pleno.0.keputusan', 'DISETUJUI_CATATAN')->assertJsonPath('data.pleno.0.catatan', 'Lengkapi dokumen AMDAL.');
        $this->assertCount(14, collect($h->json('data.penilaian.kriteria'))->where('berlaku', true));

        // (7b) Push: belum dikonfigurasi -> galat ramah; gagal -> GAGAL; berhasil -> TERKIRIM.
        $this->post(route('perencanaan.kirim-psi', $u))->assertSessionHasErrors('kirim');
        config(['psn_dashboard.psi.url_api' => 'https://psi.contoh.go.id/api', 'psn_dashboard.psi.token_keluar' => 'rahasia-keluar']);
        Http::fake(['psi.contoh.go.id/*' => Http::sequence()->push(['pesan' => 'galat'], 500)->push(['diterima' => true], 200)]);
        $this->post(route('perencanaan.kirim-psi', $u))->assertSessionHasErrors('kirim');
        $this->assertSame('GAGAL', $u->psi->fresh()->kirim_status);

        $this->post(route('perencanaan.kirim-psi', $u))->assertSessionHasNoErrors();
        $this->assertSame('TERKIRIM', $u->psi->fresh()->kirim_status);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://psi.contoh.go.id/api/usulan/CONTOH-USL-2026-0001/hasil-penilaian'
            && $r->hasHeader('Authorization', 'Bearer rahasia-keluar') && $r['rekomendasi']['nilai'] === 'DIREKOMENDASIKAN' && $r['skema'] === 'sirenda.hasil.v1');
        $this->get('/perencanaan?tahap=TERKIRIM')->assertOk()->assertSee('CONTOH — Pembangunan Jalan Tol');
        $this->get("/perencanaan/{$u->id}?tab=riwayat")->assertOk()->assertSee('Kirim hasil')->assertSee('Kirim usulan');

        // Versi baru dari PSI setelah FINAL -> perlu ditinjau ulang.
        $this->kirim($this->payload(['versi' => 2]))->assertOk();
        $this->assertTrue($u->psi->fresh()->perlu_tinjau_ulang);
    }

    public function test_hak_akses_pleno_rekomendasi_dan_tarik(): void
    {
        $this->kirim($this->payload())->assertCreated();
        $u = $this->usulan();

        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get('/perencanaan/pleno')->assertOk();
        $this->get("/perencanaan/{$u->id}?tab=kesiapan")->assertOk();
        $this->post(route('perencanaan.pleno.store'), ['nama' => 'X', 'tanggal' => '2026-10-24'])->assertForbidden();
        $this->post(route('perencanaan.rekomendasi', $u), ['rekomendasi' => 'DITOLAK', 'tanggal' => '2026-10-24'])->assertForbidden();
        $this->post(route('perencanaan.kirim-psi', $u))->assertForbidden();
        $this->put(route('perencanaan.pemetaan', $u), ['unit_kerja_id' => null])->assertForbidden();

        // Operator hanya melihat usulan unitnya; usulan PSI tanpa direktorat pengampu tidak terlihat.
        $pleno = Pleno::create(['nama' => 'Pleno Uji Cakupan', 'tanggal' => '2026-10-24', 'status' => 'TERJADWAL']);
        PlenoUsulan::create(['pleno_id' => $pleno->id, 'usulan_id' => $u->id, 'urutan' => 1]);
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->get("/perencanaan/{$u->id}")->assertNotFound();
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->get("/perencanaan/{$u->id}?tab=pleno")->assertOk()->assertDontSee('Tambahkan ke agenda');
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));
        $this->get(route('perencanaan.pleno.show', $pleno))->assertOk()->assertDontSee('CONTOH — Pembangunan Jalan Tol');
        $this->actingAs($this->buatPengguna('Pimpinan'));
        $this->get(route('perencanaan.pleno.show', $pleno))->assertOk()->assertSee('CONTOH — Pembangunan Jalan Tol');
        $this->actingAs($this->buatPengguna('Operator K/L', '07'));

        // Pengusul menarik usulan.
        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])->postJson('/api/integrasi/psi/v1/usulan/CONTOH-USL-2026-0001/tarik', ['alasan' => 'Dialihkan'])
            ->assertOk()->assertJsonPath('data.status', 'DITARIK');
        $this->actingAs($this->buatPengguna('Tim Koordinasi/PMO'));
        $this->get('/perencanaan?tahap=DITARIK')->assertOk()->assertSee('CONTOH — Pembangunan Jalan Tol');
        $this->withHeaders(['Authorization' => 'Bearer '.self::TOKEN])->getJson('/api/integrasi/psi/v1/usulan/TIDAK-ADA/hasil')->assertNotFound();
    }
}
