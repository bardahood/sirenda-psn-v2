<?php

namespace App\Support\Legacy;

use App\Support\Referensi\ReferensiImporter;
use Illuminate\Database\Connection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ETL dari basis data SIRENDA PSN lama (koneksi `legacy`) ke skema v2.
 *
 * Prinsip:
 * - Hanya membaca dari `legacy`; tidak pernah menulis ke sana.
 * - Setiap baris inti menyimpan jejak asal (legacy_id / legacy_ref) agar
 *   impor dapat diulang (--fresh) dan ditelusuri.
 * - Kolom melebar per tahun/bulan diubah menjadi baris per periode.
 * - Data yang tidak dapat dipetakan tidak dibuang diam-diam: dicatat di laporan.
 */
class LegacyImporter
{
    public const LANGKAH = ['referensi', 'pengguna', 'psn', 'kinerja', 'kegiatan', 'risiko', 'monev', 'usulan', 'riwayat'];

    /** Tabel data (bukan referensi) yang dikosongkan oleh --fresh, urutan anak dulu. */
    public const TABEL_DATA = [
        'snapshot_kelengkapan', 'snapshot_risiko', 'snapshot_kegiatan', 'snapshot_psn', 'pengisian_psn_riwayat', 'pengisian_psn',
        'audit_log', 'login_log', 'penilaian_skor', 'penilaian', 'usulan_lokasi', 'usulan_psn',
        'monev_evaluasi', 'monev_dokumentasi', 'monev_regulasi', 'monev_risiko', 'monev_anggaran', 'monev_capaian', 'monev_kelembagaan', 'monev',
        'isu', 'regulasi_target_tahun', 'regulasi', 'risiko_pemantauan', 'risiko_perlakuan', 'risiko_tahun_perlakuan', 'risiko',
        'kegiatan_target', 'kegiatan', 'trisula_target', 'trisula', 'penerima_manfaat_target', 'penerima_manfaat', 'indikator_target', 'indikator',
        'psn_stakeholder', 'psn_dokumen', 'psn_evaluasi_status', 'psn_fasilitas', 'psn_dukungan', 'psn_target_tahunan', 'psn_profil_item',
        'psn_sdgs', 'psn_sumber_dana', 'psn_lokasi', 'psn_unit_pengampu', 'psn_kelembagaan', 'psn',
    ];

    protected Connection $legacy;

    /** @var array<string, array<string,int>> peta kode -> id per tabel referensi */
    protected array $ref = [];

    /** @var array<string, array<int,int>> peta legacy id -> id baru per entitas */
    protected array $peta = [];

    /** @var array<string,int> */
    protected array $userId = [];

    protected array $laporan = ['jumlah' => [], 'peringatan' => []];

    protected ?\Closure $log = null;

    public function __construct(protected ReferensiImporter $referensi)
    {
        $this->legacy = DB::connection('legacy');
    }

    public function onLog(\Closure $log): static
    {
        $this->log = $log;

        return $this;
    }

    public function kosongkan(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TABEL_DATA as $tabel) {
            DB::table($tabel)->truncate();
        }
        $legacyUsers = DB::table('users')->whereNotNull('legacy_grup')->pluck('id');
        DB::table('model_has_roles')->where('model_type', 'App\\Models\\User')->whereIn('model_id', $legacyUsers)->delete();
        DB::table('users')->whereIn('id', $legacyUsers)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @param string[] $langkah */
    public function jalankan(array $langkah = self::LANGKAH): array
    {
        foreach (self::LANGKAH as $l) {
            if (! in_array($l, $langkah, true)) {
                // Langkah berikutnya tetap butuh peta id dari langkah yang dilewati.
                $this->muatPetaTersimpan($l);

                continue;
            }
            $this->info("== {$l}");
            DB::transaction(fn () => $this->{'langkah'.Str::studly($l)}());
        }

        return $this->laporan;
    }

    // ------------------------------------------------------------------ langkah

    protected function langkahReferensi(): void
    {
        $data = [];
        foreach ($this->legacy->table('master')->get() as $r) {
            $tipe = $r->tipe_tabel === 'KABP1' ? 'KABP' : trim($r->tipe_tabel);
            $data[$tipe][trim($r->kode_tabel)] = ['kode' => trim($r->kode_tabel), 'nama' => $this->teks($r->keterangan) ?? '-'];
        }
        $data = array_map('array_values', $data);
        $data['PETA'] = $this->legacy->table('peta')->get()->map(fn ($r) => ['kode' => trim($r->code), 'hc_key' => $r->{'hc-key'}])->all();

        // Kriteria penilaian tetap dari berkas seeder (sudah dikodekan KU/KP/KK).
        $json = json_decode(file_get_contents(database_path('seeders/data/referensi.json')), true);
        $data['KRITERIA'] = $json['KRITERIA'] ?? [];

        foreach ($this->referensi->import($data) as $tabel => $n) {
            $this->hitung($tabel, $n);
        }
    }

    protected function langkahPengguna(): void
    {
        $peran = [
            'administrator' => 'Super Admin',
            'monev' => 'Tim Koordinasi/PMO',
            'user' => 'Direktorat Sektor',
            'kementerian' => 'Operator K/L',
        ];
        $emailDipakai = DB::table('users')->pluck('email')->map(fn ($e) => Str::lower($e))->flip()->all();

        foreach ($this->legacy->table('users')->orderBy('username')->get() as $u) {
            $username = Str::lower(trim($u->username));
            if ($username === '' || DB::table('users')->where('username', $username)->exists()) {
                $this->peringatan('pengguna', "username kosong/duplikat dilewati: {$u->username}");

                continue;
            }
            $email = Str::lower(trim((string) $u->email));
            if ($email === '' || isset($emailDipakai[$email])) {
                $email = Str::slug($username, '.').'@pengguna-lama.invalid';
            }
            $emailDipakai[$email] = true;

            $id = DB::table('users')->insertGetId([
                'username' => $username,
                'name' => $this->teks($u->nama_pengguna) ?? $username,
                'email' => $email,
                // Hash kata sandi lama tidak dibawa: semua pengguna wajib reset.
                'password' => Hash::make(Str::random(40)),
                'wajib_ganti_password' => true,
                'unit_kerja_id' => $this->kode('ref_unit_kerja', $u->akses),
                'is_active' => $u->status === 'A',
                'last_login_at' => $this->waktu($u->last_login),
                'jumlah_login' => (int) $u->logins,
                'legacy_grup' => $u->grup,
                'legacy_akses' => $u->akses,
                'created_at' => $this->waktu($u->created_at) ?? now(),
                'updated_at' => now(),
            ]);

            if (isset($peran[$u->grup])) {
                $roleId = DB::table('roles')->where('name', $peran[$u->grup])->value('id');
                $roleId && DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => 'App\\Models\\User', 'model_id' => $id]);
            } else {
                $this->peringatan('pengguna', "grup tidak dikenal untuk {$username}: {$u->grup}");
            }
            $this->hitung('users');
        }
        $this->muatPengguna();
    }

    protected function langkahPsn(): void
    {
        $this->muatRef();
        $min = config('psn_dashboard.investasi.min_rp');
        $max = config('psn_dashboard.investasi.max_rp');

        foreach ($this->legacy->table('psn')->orderBy('id')->get() as $p) {
            $nilai = $this->angka($p->nilai_investasi);
            $anomali = $nilai !== null && $nilai > 0 && ($nilai < $min || $nilai > $max);
            if ($anomali) {
                $this->peringatan('investasi_anomali', "psn#{$p->id} {$this->potong($p->proyek_psn)}: Rp".number_format($nilai, 0, ',', '.'));
            }

            $id = DB::table('psn')->insertGetId([
                'legacy_id' => $p->id,
                'uuid' => $p->uuid ?: (string) Str::uuid(),
                'kode_psn' => $this->teks($p->kode_psn),
                'kode_krisna' => $this->teks($p->kode_krisna),
                'kode_rkp' => $this->teks($p->kode_rkp),
                'kode_sub' => $this->teks($p->kode_sub),
                'rkp_tahun' => $this->tahun($p->rkp),
                'kategori_rkp_id' => $this->refKode('KTGR', $p->kategori_rkp),
                'program_id' => $this->kode('ref_program', $p->program_psn, 'program psn#'.$p->id),
                'klaster_id' => $this->kode('ref_klaster', $p->klaster, 'klaster psn#'.$p->id),
                'sub_klaster_id' => $this->kode('ref_sub_klaster', $p->sub_klaster, 'sub_klaster psn#'.$p->id),
                'klaster_pkpn_id' => $this->kode('ref_klaster_pkpn', $p->klaster_pkpn, 'klaster_pkpn psn#'.$p->id),
                'nama' => $this->teks($p->proyek_psn) ?? '(tanpa nama)',
                'sub_proyek' => $this->teks($p->sub_proyek),
                'deskripsi' => $this->teks($p->deskripsi),
                'output' => $this->teks($p->output),
                'dampak' => $this->teks($p->dampak),
                'dasar_penetapan' => $this->teks($p->dasar_penetapan),
                'kpu' => $this->teks($p->kpu),
                'status_psn_id' => $this->kode('ref_status_psn', $p->status_psn, 'status_psn psn#'.$p->id),
                'ket_status' => $this->potong($p->ket_status, 255),
                'ketersediaan_info_id' => $this->refKode('SKIF', $p->status),
                'tahun_selesai' => $this->tahun($p->tahun_selesai),
                'ket_tahun_selesai' => $this->potong($p->ket_tahun, 255),
                'rencana_investasi_rp' => $this->angka($p->rencana_investasi),
                'nilai_investasi_rp' => $nilai,
                'investasi_anomali' => $anomali,
                'sumber_input' => $p->sumber,
                'pic_nama' => $this->potong($p->pic, 255),
                'catatan' => $this->potong($p->catatan, 500),
                'file_kerangka' => $this->teks($p->file_kerangka),
                'file_gambar' => $this->teks($p->file_gambar),
                'file_visualisasi' => $this->teks($p->file_visualisasi),
            ] + $this->jejak($p));
            $this->peta['psn'][$p->id] = $id;
            $this->hitung('psn');

            $this->kelembagaanPsn($id, $p);
            $this->sumberDanaDariKolom($id, $p);
        }

        // psn_penanggung_jawab (kode PJWB) -- tambahan di luar kolom psn.penanggung_jawab.
        foreach ($this->legacy->table('psn_penanggung_jawab')->whereNull('deleted_at')->get() as $r) {
            if (! $psn = $this->psnId($r->psn_id, 'psn_penanggung_jawab')) {
                continue;
            }
            $pj = $this->kode('ref_penanggung_jawab', $r->penanggung_jawab, 'psn_penanggung_jawab');
            $ada = DB::table('psn_kelembagaan')->where(['psn_id' => $psn, 'peran' => 'PENANGGUNG_JAWAB', 'penanggung_jawab_id' => $pj])->exists();
            if ($pj && ! $ada) {
                DB::table('psn_kelembagaan')->insert(['psn_id' => $psn, 'peran' => 'PENANGGUNG_JAWAB', 'penanggung_jawab_id' => $pj,
                    'urutan' => 1 + DB::table('psn_kelembagaan')->where(['psn_id' => $psn, 'peran' => 'PENANGGUNG_JAWAB'])->count()] + $this->jejak($r, false));
                $this->hitung('psn_kelembagaan');
            }
        }

        foreach ($this->legacy->table('unit_pengampu')->whereNull('deleted_at')->get() as $r) {
            $psn = $this->psnId($r->psn_id, 'unit_pengampu');
            $unit = $this->kode('ref_unit_kerja', $r->unit, 'unit_pengampu');
            if ($psn && $unit) {
                DB::table('psn_unit_pengampu')->insertOrIgnore(['psn_id' => $psn, 'unit_kerja_id' => $unit, 'keterangan' => $this->potong($r->keterangan, 255)] + $this->jejak($r, false));
                $this->hitung('psn_unit_pengampu');
            }
        }

        foreach ($this->legacy->table('lokasi_psn')->orderBy('id')->get() as $r) {
            if (! $psn = $this->psnId($r->psn_id, 'lokasi_psn')) {
                continue;
            }
            $prov = trim((string) $r->provinsi);
            if (! isset($this->ref['ref_wilayah'][$prov])) {
                $this->peringatan('lokasi', "lokasi#{$r->id}: kode provinsi tidak dikenal '{$prov}'");

                continue;
            }
            $kab = trim((string) $r->kabupaten);
            if ($kab !== '' && ! isset($this->ref['ref_wilayah'][$kab])) {
                $this->peringatan('lokasi', "lokasi#{$r->id}: kode kabupaten tidak dikenal '{$kab}'");
                $kab = '';
            }
            DB::table('psn_lokasi')->insert(['psn_id' => $psn, 'provinsi_kode' => $prov, 'kabupaten_kode' => $kab ?: null,
                'keterangan' => $this->potong($r->keterangan, 255)] + $this->jejak($r));
            $this->hitung('psn_lokasi');
        }

        foreach ($this->legacy->table('psn_pendanaan')->whereNull('deleted_at')->get() as $r) {
            $psn = $this->psnId($r->psn_id, 'psn_pendanaan');
            $dana = $this->kode('ref_sumber_dana', $r->pendanaan, 'psn_pendanaan');
            if ($psn && $dana) {
                $n = DB::table('psn_sumber_dana')->insertOrIgnore(['psn_id' => $psn, 'sumber_dana_id' => $dana, 'keterangan' => $this->potong($r->keterangan, 255)] + $this->jejak($r, false));
                $this->hitung('psn_sumber_dana', $n);
            }
        }

        foreach ($this->legacy->table('psn_sdgs')->whereNull('deleted_at')->get() as $r) {
            $psn = $this->psnId($r->psn_id, 'psn_sdgs');
            $sdgs = $this->refKode('SDGS', $r->sdgs, 'psn_sdgs');
            if ($psn && $sdgs) {
                $this->hitung('psn_sdgs', DB::table('psn_sdgs')->insertOrIgnore(['psn_id' => $psn, 'sdgs_id' => $sdgs, 'keterangan' => $this->potong($r->keterangan, 255)] + $this->jejak($r, false)));
            }
        }

        $this->salinPerPsn('psn_item', 'psn_profil_item', fn ($r) => ($bagian = $this->refKode('TYIT', $r->jenis, 'psn_item')) ? [
            'legacy_id' => $r->id, 'bagian_id' => $bagian, 'isi' => $this->teks($r->keterangan),
            'catatan' => $this->teks($r->catatan), 'file_bukti' => $this->teks($r->file_bukti),
        ] : null);

        $this->salinPerPsn('target_psn', 'psn_target_tahunan', fn ($r) => ($this->tahun($r->tahun) && $this->teks($r->target)) ? [
            'tahun' => $this->tahun($r->tahun), 'uraian' => $this->teks($r->target),
        ] : null);

        $this->salinPerPsn('dukungan_psn', 'psn_dukungan', fn ($r) => ($u = $this->teks($r->dukungan)) ? ['uraian' => $u] : null);

        $this->salinPerPsn('psn_fasilitas', 'psn_fasilitas', fn ($r) => [
            'tahun' => $this->tahun($r->tahun), 'fasilitas_id' => $this->refKode('FAST', $r->fasilitas, 'psn_fasilitas'),
            'keterangan' => $this->teks($r->keterangan),
        ]);

        // psn_kebutuhan: jenis A = isu & kebutuhan dukungan, jenis B = kebutuhan status PSN tahun berikutnya.
        $this->salinPerPsn('psn_kebutuhan', 'isu', fn ($r) => ($r->jenis === 'A' && ($u = $this->teks($r->point))) ? [
            'legacy_id' => $r->id, 'uraian' => $u, 'kebutuhan_dukungan' => $this->teks($r->deskripsi),
            'file_bukti' => $this->teks($r->file_bukti), 'status' => 'TERBUKA',
        ] : null);
        $this->salinPerPsn('psn_kebutuhan', 'psn_evaluasi_status', fn ($r) => ($r->jenis === 'B' && ($u = $this->teks($r->point))) ? [
            'kebutuhan_status' => $u, 'justifikasi' => $this->teks($r->deskripsi), 'file_bukti' => $this->teks($r->file_bukti),
        ] : null);

        $this->salinPerPsn('psn_dokumentasi', 'psn_dokumen', fn ($r) => [
            'judul' => $this->teks($r->dokumen) ?? '(tanpa judul)', 'deskripsi' => $this->teks($r->deskripsi),
            'kategori_id' => $this->refKode('KATD', $r->kategori, 'psn_dokumentasi'), 'path' => $this->teks($r->file),
        ]);

        $this->salinPerPsn('psn_stakeholder', 'psn_stakeholder', fn ($r) => [
            'legacy_id' => $r->id, 'jenis' => $this->teks($r->jenis), 'peran_id' => $this->refKode('PRAN', $r->kategori),
            'peran' => $this->teks($r->peran), 'instansi_utama' => $this->teks($r->instansi1), 'instansi_pendukung' => $this->teks($r->instansi2),
            'keterangan' => $this->teks($r->keterangan), 'catatan' => $this->teks($r->catatan), 'file_bukti' => $this->teks($r->file_bukti),
        ]);
    }

    protected function langkahKinerja(): void
    {
        $this->muatRef();
        $tahun = range(2026, 2030);

        foreach ($this->legacy->table('psn_indikator')->orderBy('id')->get() as $r) {
            if (! ($psn = $this->psnId($r->psn_id, 'psn_indikator')) || ! ($u = $this->teks($r->indikator))) {
                continue;
            }
            $id = DB::table('indikator')->insertGetId(['legacy_id' => $r->id, 'psn_id' => $psn, 'uraian' => $u,
                'satuan' => $this->potong($r->satuan, 100), 'baseline' => $this->potong($r->baseline, 100),
                'baseline_tahun' => $this->tahun($r->tahun), 'target_akhir' => $this->angka($r->target_akhir),
                'capaian' => $this->teks($r->capaian)] + $this->jejak($r));
            $this->hitung('indikator');
            $this->targetTahunan('indikator_target', 'indikator_id', $id, $r, $tahun);
        }

        foreach ($this->legacy->table('psn_penerima')->orderBy('id')->get() as $r) {
            if (! ($psn = $this->psnId($r->psn_id, 'psn_penerima')) || ! ($u = $this->teks($r->penerima))) {
                continue;
            }
            $id = DB::table('penerima_manfaat')->insertGetId(['legacy_id' => $r->id, 'psn_id' => $psn, 'uraian' => $u,
                'satuan' => $this->potong($r->satuan, 100), 'baseline' => $this->angka($r->baseline),
                'baseline_tahun' => $this->tahun($r->tahun), 'target_akhir' => $this->angka($r->target_akhir),
                'capaian' => $this->teks($r->capaian)] + $this->jejak($r));
            $this->hitung('penerima_manfaat');
            $this->targetTahunan('penerima_manfaat_target', 'penerima_manfaat_id', $id, $r, $tahun);
        }

        foreach ($this->legacy->table('psn_trisula')->orderBy('id')->get() as $r) {
            if (! $psn = $this->psnId($r->psn_id, 'psn_trisula')) {
                continue;
            }
            $id = DB::table('trisula')->insertGetId(['legacy_id' => $r->id, 'psn_id' => $psn,
                'kategori_id' => $this->refKode('KTAC', $r->kategori, 'psn_trisula'), 'indikator' => $this->teks($r->indikator),
                'kontribusi' => $this->teks($r->kontribusi), 'satuan' => $this->potong($r->satuan, 100), 'baseline' => $this->angka($r->baseline),
                'baseline_tahun' => $this->tahun($r->tahun), 'target_akhir' => $this->angka($r->target_akhir),
                'capaian' => $this->teks($r->capaian)] + $this->jejak($r));
            $this->peta['trisula'][$r->id] = $id;
            $this->hitung('trisula');
            $this->targetTahunan('trisula_target', 'trisula_id', $id, $r, $tahun, ['periode' => 'TAHUNAN', 'periode_ke' => 0]);
        }

        foreach ($this->legacy->table('psn_trisula_triwulan')->whereNull('deleted_at')->get() as $r) {
            if (! ($tid = $this->peta['trisula'][$r->trisula_id] ?? null) || ! ($th = $this->tahun($r->tahun))) {
                $this->peringatan('trisula_triwulan', "baris#{$r->id} tanpa trisula/tahun valid");

                continue;
            }
            foreach (range(1, 4) as $q) {
                [$t, $re] = [$this->angka($r->{"target_{$q}"}), $this->angka($r->{"realisasi_{$q}"})];
                if ($this->kosongSemua($t, $re)) {
                    continue;
                }
                DB::table('trisula_target')->insert(['trisula_id' => $tid, 'tahun' => $th, 'periode' => 'TRIWULAN', 'periode_ke' => $q,
                    'target' => $t, 'realisasi' => $re, 'status' => $this->potong($r->status, 255)] + $this->jejak($r, false));
                $this->hitung('trisula_target');
            }
        }
    }

    protected function langkahKegiatan(): void
    {
        $this->muatRef();

        foreach ($this->legacy->table('psn_kegiatan')->orderBy('id')->get() as $r) {
            if (! $psn = $this->psnId($r->psn_id, 'psn_kegiatan')) {
                continue;
            }
            $id = DB::table('kegiatan')->insertGetId([
                'legacy_ref' => "psn_kegiatan:{$r->id}", 'psn_id' => $psn,
                'jenis_id' => $this->refKode('TACT', $r->jenis, 'psn_kegiatan'),
                'nama' => $this->teks($r->kegiatan) ?? '(tanpa nama)', 'lokasi' => $this->teks($r->lokasi),
                'pelaksana' => $this->potong($r->pelaksana, 255),
                'satuan_1' => $this->potong($r->satuan1, 100), 'satuan_2' => $this->potong($r->satuan2, 100),
                'baseline_1' => $this->angka($r->baseline1), 'baseline_2' => $this->angka($r->baseline2),
                'baseline_tahun_1' => $this->tahun($r->tahun1), 'baseline_tahun_2' => $this->tahun($r->tahun2),
                'target_akhir_1' => $this->angka($r->target_akhir1), 'target_akhir_2' => $this->angka($r->target_akhir2),
                'is_critical_path' => (int) $r->kunci === 1,
                'sumber_dana_id' => $this->kode('ref_sumber_dana', $r->pendanaan),
                'status_teks' => $this->potong($r->status, 100), 'keterangan' => $this->teks($r->keterangan),
            ] + $this->jejak($r));
            $this->peta['kegiatan'][$r->id] = $id;
            $this->hitung('kegiatan');
            $this->targetKegiatanTahunan($id, $r);
        }

        // Critical path: turunan dari KP/RO (kegiatan_id) -- baris tanpa induk tetap diimpor sebagai CP mandiri.
        foreach ($this->legacy->table('psn_kegiatan_cp')->orderBy('id')->get() as $r) {
            if (! $psn = $this->psnId($r->psn_id, 'psn_kegiatan_cp')) {
                continue;
            }
            $parent = $r->kegiatan_id ? ($this->peta['kegiatan'][$r->kegiatan_id] ?? null) : null;
            if ($r->kegiatan_id && ! $parent) {
                $this->peringatan('kegiatan_cp', "cp#{$r->id}: induk psn_kegiatan#{$r->kegiatan_id} tidak ditemukan");
            }
            $id = DB::table('kegiatan')->insertGetId([
                'legacy_ref' => "psn_kegiatan_cp:{$r->id}", 'psn_id' => $psn, 'parent_id' => $parent,
                'nama' => $this->teks($r->kegiatan) ?? '(critical path tanpa nama)', 'lokasi' => $this->teks($r->lokasi),
                'target_akhir_1' => $this->angka($r->target_akhir1), 'target_akhir_2' => $this->angka($r->target_akhir2),
                'is_critical_path' => true, 'sumber_dana_id' => $this->kode('ref_sumber_dana', $r->pendanaan),
                'status_teks' => $this->potong($r->status, 100), 'keterangan' => $this->teks($r->keterangan),
            ] + $this->jejak($r));
            $this->peta['kegiatan_cp'][$r->id] = $id;
            $this->hitung('kegiatan');
            $this->targetKegiatanTahunan($id, $r);
        }

        foreach (['psn_kegiatan_detil' => ['kegiatan_id', 'kegiatan'], 'psn_kegiatan_detil_cp' => ['cp_id', 'kegiatan_cp']] as $tabel => [$fk, $peta]) {
            foreach ($this->legacy->table($tabel)->orderBy('id')->get() as $r) {
                $kid = $this->peta[$peta][$r->{$fk}] ?? null;
                if (! $kid || ! ($th = $this->tahun($r->tahun))) {
                    $this->peringatan('kegiatan_detil', "{$tabel}#{$r->id}: kegiatan/tahun tidak valid");

                    continue;
                }
                foreach (range(1, 12) as $m) {
                    $v = [
                        'target_persen' => $this->angka($r->{"target_persen_{$m}"} ?? null),
                        'realisasi_persen' => $this->angka($r->{"realisasi_persen_{$m}"} ?? null),
                        'target_1' => $this->angka($r->{"target_unit_{$m}"} ?? null),
                        'realisasi_1' => $this->angka($r->{"realisasi_unit_{$m}"} ?? null),
                        'pagu_rp' => $this->angka($r->{"target_anggaran_{$m}"} ?? null),
                        'realisasi_anggaran_rp' => $this->angka($r->{"realisasi_anggaran_{$m}"} ?? null),
                        'target_fisik' => $this->angka($r->{"target_fisik_{$m}"} ?? null),
                        'realisasi_fisik' => $this->angka($r->{"realisasi_fisik_{$m}"} ?? null),
                    ];
                    if ($this->kosongSemua(...array_values($v))) {
                        continue;
                    }
                    DB::table('kegiatan_target')->insertOrIgnore(['kegiatan_id' => $kid, 'tahun' => $th, 'periode' => 'BULANAN', 'periode_ke' => $m,
                        'metode' => $r->metode, 'status' => $this->potong($r->status, 50),
                        'dilaporkan_at' => $this->kosongSemua($v['realisasi_persen'], $v['realisasi_1'], $v['realisasi_anggaran_rp'], $v['realisasi_fisik']) ? null : $this->waktu($r->updated_at),
                    ] + $v + $this->jejak($r, false));
                    $this->hitung('kegiatan_target');
                }
            }
        }
    }

    protected function langkahRisiko(): void
    {
        $this->muatRef();

        foreach ($this->legacy->table('psn_risiko')->orderBy('id')->get() as $r) {
            if (! ($psn = $this->psnId($r->psn_id, 'psn_risiko')) || ! ($u = $this->teks($r->risiko))) {
                continue;
            }
            $kategori = trim((string) $r->kategori);
            $kategoriId = $kategori === 'Sosial' ? $this->refKode('RISK', 'A') : $this->refKode('RISK', $kategori, 'psn_risiko');
            $id = DB::table('risiko')->insertGetId([
                'legacy_id' => $r->id, 'psn_id' => $psn, 'uraian' => $u, 'kategori_id' => $kategoriId,
                'level_awal' => $this->level($r->risiko_awal ?: $r->level),
                'level_harapan' => $this->level($r->risiko_harapan),
                'rencana_perlakuan' => $this->teks($r->rencana), 'penanggung_jawab' => $this->teks($r->penanggung_jawab),
                'catatan' => $this->teks($r->catatan),
            ] + $this->jejak($r));
            $this->peta['risiko'][$r->id] = $id;
            $this->hitung('risiko');

            foreach ([2026, 2027, 2028, 2029] as $th) {
                if ((int) $r->{"cek_{$th}"} === 1) {
                    DB::table('risiko_tahun_perlakuan')->insertOrIgnore(['risiko_id' => $id, 'tahun' => $th]);
                }
            }

            $residual = $this->teks($r->risiko_residual);
            if ($r->progress !== null || $this->teks($r->status) || $residual) {
                DB::table('risiko_pemantauan')->insert([
                    'risiko_id' => $id, 'tanggal' => ($this->waktu($r->updated_at) ?? now())->toDateString(),
                    'tahun' => ($this->waktu($r->updated_at) ?? now())->year,
                    'level_aktual' => $this->level($residual), 'progres_persen' => $r->progress,
                    'status_perlakuan' => $this->potong($r->status, 50), 'sumber' => 'PELAPORAN',
                    'catatan' => $this->level($residual) ? null : $residual,
                ] + $this->jejak($r, false));
                $this->hitung('risiko_pemantauan');
            }
        }

        foreach ($this->legacy->table('psn_pj_perlakuan')->whereNull('deleted_at')->get() as $r) {
            if (! $rid = $this->peta['risiko'][$r->risk_id] ?? null) {
                $this->peringatan('risiko_perlakuan', "pj_perlakuan#{$r->id}: risiko#{$r->risk_id} tidak ditemukan");

                continue;
            }
            DB::table('risiko_perlakuan')->insert(['risiko_id' => $rid, 'uraian' => $this->potong($r->perlakuan, 255),
                'instansi_id' => $this->kode('ref_instansi', $r->pj_perlakuan, 'risiko_perlakuan'),
                'keterangan' => $this->potong($r->keterangan, 255)] + $this->jejak($r, false));
            $this->hitung('risiko_perlakuan');
        }

        foreach ($this->legacy->table('psn_regulasi')->orderBy('id')->get() as $r) {
            if (! ($psn = $this->psnId($r->psn_id, 'psn_regulasi')) || ! ($u = $this->teks($r->regulasi))) {
                continue;
            }
            $id = DB::table('regulasi')->insertGetId(['legacy_id' => $r->id, 'psn_id' => $psn, 'nama' => $u,
                'justifikasi' => $this->teks($r->justifikasi), 'penanggung_jawab' => $this->teks($r->penanggung_jawab),
                'tahap' => 'IDENTIFIKASI', 'catatan' => $this->teks($r->catatan)] + $this->jejak($r));
            $this->peta['regulasi'][$r->id] = $id;
            $this->hitung('regulasi');
            foreach ([2026, 2027, 2028, 2029] as $th) {
                if ((int) $r->{"cek_{$th}"} === 1) {
                    DB::table('regulasi_target_tahun')->insertOrIgnore(['regulasi_id' => $id, 'tahun' => $th]);
                }
            }
        }
    }

    protected function langkahMonev(): void
    {
        $this->muatRef();
        $jenis = [1 => 'PENGENDALIAN', 2 => 'PERENCANAAN'];

        foreach ($this->legacy->table('monev_header')->orderBy('id')->get() as $r) {
            if (! $psn = $this->psnId($r->psn_id, 'monev_header')) {
                continue;
            }
            $id = DB::table('monev')->insertGetId(['legacy_id' => $r->id, 'psn_id' => $psn,
                'jenis' => $jenis[$r->jenis] ?? 'PENGENDALIAN', 'tanggal' => $r->tanggal ?? now()->toDateString(),
                'tanggal_mulai' => $r->tanggal_mulai, 'tanggal_akhir' => $r->tanggal_akhir, 'pelaksana' => $this->teks($r->pelaksana),
                'kepatuhan_pelaporan' => $this->potong($r->kepatuhan, 50), 'skor' => $r->skor,
                'nilai_rekomendasi' => $this->potong($r->nilai_rekomendasi, 50), 'kesimpulan' => $this->teks($r->kesimpulan),
                'rekomendasi_lanjutan' => $this->teks($r->rekomendasi_lanjutan), 'keterangan' => $this->teks($r->keterangan),
                'dokumen_path' => $this->teks($r->dokumen)] + $this->jejak($r));
            $this->peta['monev'][$r->id] = $id;
            $this->hitung('monev');
        }

        $anak = [
            'monev_kelembagaan' => fn ($r) => ['peran' => $this->potong($r->peran, 50) ?? '-', 'instansi_tercatat' => $this->potong($r->instansi_tercatat, 255),
                'instansi_aktual' => $this->potong($r->instansi_aktual, 255), 'kesesuaian' => $this->potong($r->kesesuaian, 20), 'catatan' => $this->teks($r->catatan)],
            'monev_capaian' => fn ($r) => ['uraian' => $this->teks($r->aktifitas), 'satuan' => $this->potong($r->satuan, 50), 'target' => $this->angka($r->target),
                'realisasi_klaim' => $this->angka($r->realisasi_klaim), 'realisasi_aktual' => $this->angka($r->realisasi_aktual),
                'kesesuaian' => $this->potong($r->kesesuaian, 20), 'catatan' => $this->teks($r->catatan), 'file_bukti' => $this->teks($r->file_bukti)],
            'monev_anggaran' => fn ($r) => ['uraian' => $this->teks($r->aktifitas), 'sumber' => $this->potong($r->sumber, 200), 'rencana_rp' => $this->angka($r->rencana_anggaran),
                'realisasi_klaim_rp' => $this->angka($r->realisasi_klaim), 'realisasi_aktual_rp' => $this->angka($r->realisasi_aktual),
                'kesesuaian' => $this->potong($r->kesesuaian, 20), 'bukti_tersedia' => $this->potong($r->bukti, 20), 'catatan' => $this->teks($r->catatan), 'file_bukti' => $this->teks($r->file_bukti)],
            'monev_risiko' => fn ($r) => ['uraian' => $this->teks($r->risiko), 'kategori' => $this->potong($r->kategori, 50), 'level_awal' => $this->level($r->risiko_awal),
                'level_harapan' => $this->level($r->risiko_harapan), 'level_aktual' => $this->level($r->risiko_aktual), 'rencana' => $this->teks($r->rencana),
                'progres_persen' => $r->progress, 'status' => $this->potong($r->status, 50), 'hasil_evaluasi' => $this->potong($r->hasil_evaluasi, 50), 'catatan' => $this->teks($r->catatan)],
            'monev_regulasi' => fn ($r) => ['uraian' => $this->teks($r->regulasi), 'justifikasi' => $this->teks($r->justifikasi), 'target_tahun' => $this->tahun($r->target_tahun),
                'status_klaim' => $this->potong($r->status_klaim, 50), 'status_aktual' => $this->potong($r->status_aktual, 50), 'penanggung_jawab' => $this->teks($r->penanggung_jawab),
                'bukti_tersedia' => $this->potong($r->bukti, 20), 'kesesuaian' => $this->potong($r->kesesuaian, 20), 'catatan' => $this->teks($r->catatan)],
            'monev_dokumentasi' => fn ($r) => ['judul' => $this->teks($r->dokumen), 'deskripsi' => $this->teks($r->deskripsi),
                'kategori_id' => $this->refKode('KATD', $r->kategori), 'path' => $this->teks($r->file)],
            'monev_evaluasi' => fn ($r) => ['hasil_evaluasi' => $this->teks($r->hasil_evaluasi), 'isu_tantangan' => $this->teks($r->isu_tantangan),
                'tindak_lanjut' => $this->teks($r->tindak_lanjut), 'status_pengendalian' => $this->teks($r->status_pengendalian)],
        ];
        foreach ($anak as $tabel => $map) {
            foreach ($this->legacy->table($tabel)->whereNull('deleted_at')->get() as $r) {
                if (! $mid = $this->peta['monev'][$r->monev_id] ?? null) {
                    $this->peringatan('monev', "{$tabel}#{$r->id}: monev#{$r->monev_id} tidak ditemukan");

                    continue;
                }
                DB::table($tabel)->insert(['monev_id' => $mid] + $map($r) + $this->jejak($r, false));
                $this->hitung($tabel);
            }
        }

        $detil = $this->legacy->table('monev_detil')->count();
        $detil && $this->peringatan('monev', "{$detil} baris monev_detil (jawaban kriteria) belum dipetakan ke penilaian_skor");
    }

    protected function langkahUsulan(): void
    {
        $this->muatRef();
        foreach ($this->legacy->table('psn_2027')->orderBy('id')->get() as $r) {
            if (! $nama = $this->teks($r->proyek_psn)) {
                continue;
            }
            $id = DB::table('usulan_psn')->insertGetId(['legacy_id' => $r->id, 'tahun_rkp' => 2027, 'nama' => $nama,
                'klaster_id' => $this->kode('ref_klaster', $r->klaster), 'status' => 'DIAJUKAN'] + $this->jejak($r));
            $this->hitung('usulan_psn');
            $prov = trim((string) $r->provinsi);
            if (isset($this->ref['ref_wilayah'][$prov])) {
                $kab = trim((string) $r->kabupaten);
                DB::table('usulan_lokasi')->insert(['usulan_id' => $id, 'provinsi_kode' => $prov,
                    'kabupaten_kode' => isset($this->ref['ref_wilayah'][$kab]) ? $kab : null]);
                $this->hitung('usulan_lokasi');
            }
        }
    }

    /**
     * Tabel *_log lama berisi salinan baris + kolom `action` setiap kali disimpan.
     * Diubah menjadi audit_log: nilai_baru = salinan baris, nilai_lama = salinan
     * sebelumnya untuk id yang sama.
     */
    protected function langkahRiwayat(): void
    {
        $tabelBaru = [
            'psn_log' => ['psn', 'psn'], 'psn_risiko_log' => ['risiko', 'risiko'], 'psn_regulasi_log' => ['regulasi', 'regulasi'],
            'psn_kegiatan_log' => ['kegiatan', 'kegiatan'], 'psn_indikator_log' => ['indikator', null], 'psn_penerima_log' => ['penerima_manfaat', null],
            'psn_trisula_log' => ['trisula', 'trisula'], 'psn_item_log' => ['psn_profil_item', null], 'psn_kebutuhan_log' => ['isu', null],
            'psn_stakeholder_log' => ['psn_stakeholder', null], 'psn_dokumentasi_log' => ['psn_dokumen', null], 'psn_fasilitas_log' => ['psn_fasilitas', null],
            'psn_sdgs_log' => ['psn_sdgs', null], 'psn_pj_perlakuan_log' => ['risiko_perlakuan', null],
        ];
        $aksi = ['create' => 'CREATE', 'update' => 'UPDATE', 'delete' => 'DELETE'];

        foreach ($tabelBaru as $lama => [$baru, $peta]) {
            $sebelumnya = [];
            $rows = $this->legacy->table($lama)->get()
                ->sortBy(fn ($r) => ($r->updated_at ?? $r->created_at ?? $r->deleted_at ?? '').'|'.str_pad((string) ($r->id ?? 0), 10, '0', STR_PAD_LEFT));
            $batch = [];
            foreach ($rows as $r) {
                $baris = Arr::except((array) $r, ['action']);
                $legacyId = $r->id ?? null;
                $kunci = $legacyId ?? md5(json_encode($baris));
                // Stempel waktu tidak dikarang: bila baris lama tidak punya waktu sama sekali, dibiarkan null.
                $waktu = $this->waktu($r->action === 'delete' ? ($r->deleted_at ?? $r->updated_at ?? $r->created_at) : ($r->updated_at ?? $r->created_at));
                $lama = $sebelumnya[$kunci] ?? null;
                $abaikan = ['created_at', 'updated_at', 'created_by', 'updated_by'];
                if ($r->action === 'update' && $lama !== null) {
                    $beda = array_keys(array_filter(Arr::except($baris, $abaikan), fn ($v, $k) => ($lama[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
                    [$nilaiLama, $nilaiBaru] = [Arr::only($lama, $beda), Arr::only($baris, $beda)];
                    if (! $beda) {
                        // Simpan ulang tanpa perubahan isi: bukan perubahan, tidak dicatat.
                        $sebelumnya[$kunci] = $baris;

                        continue;
                    }
                } else {
                    [$nilaiLama, $nilaiBaru] = [$r->action === 'delete' ? ($lama ?? $baris) : null, $r->action === 'delete' ? null : $baris];
                }
                $label = $r->action === 'delete' ? ($r->deleted_by ?? $r->updated_by) : ($r->updated_by ?? $r->created_by);
                $psnLegacy = $lama === 'psn_log' ? $legacyId : ($r->psn_id ?? null);
                $batch[] = [
                    'user_id' => $this->userId[Str::lower(trim((string) $label))] ?? null,
                    'user_label' => $this->potong($label, 255),
                    'tabel' => $baru,
                    'record_id' => $peta && $legacyId ? ($this->peta[$peta][$legacyId] ?? null) : null,
                    'psn_id' => $psnLegacy ? ($this->peta['psn'][$psnLegacy] ?? null) : null,
                    'aksi' => $aksi[$r->action] ?? Str::upper((string) $r->action),
                    'nilai_lama' => $nilaiLama !== null ? json_encode($nilaiLama, JSON_INVALID_UTF8_SUBSTITUTE) : null,
                    'nilai_baru' => $nilaiBaru !== null ? json_encode($nilaiBaru, JSON_INVALID_UTF8_SUBSTITUTE) : null,
                    'sumber' => 'IMPOR_LEGACY',
                    'created_at' => $waktu,
                ];
                $sebelumnya[$kunci] = $baris;
            }
            foreach (array_chunk($batch, 200) as $chunk) {
                DB::table('audit_log')->insert($chunk);
            }
            $this->hitung('audit_log', count($batch));
        }

        $batch = [];
        foreach ($this->legacy->table('user_logs')->orderBy('logged_at')->get() as $r) {
            $batch[] = ['user_id' => $this->userId[Str::lower(trim((string) $r->username))] ?? null, 'username' => $r->username,
                'aktivitas' => Str::upper((string) $r->aktivitas), 'ip_address' => $this->potong($r->ip_address, 45), 'created_at' => $r->logged_at];
        }
        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('login_log')->insert($chunk);
        }
        $this->hitung('login_log', count($batch));
    }

    // ------------------------------------------------------------------ bantu: PSN

    protected function kelembagaanPsn(int $psnId, object $p): void
    {
        $peran = [
            'PENGUSUL' => [$p->pengusul], 'PENANGGUNG_JAWAB' => [$p->penanggung_jawab], 'PELAKSANA' => [$p->pelaksana, $p->pelaksana_2],
            'PENGELOLA' => [$p->pengelola], 'KONTRAKTOR' => [$p->kontraktor], 'SUPERVISI' => [$p->supervisi],
        ];
        foreach ($peran as $nama => $nilai) {
            $urutan = 0;
            foreach ($nilai as $v) {
                if (! $v = $this->teks($v)) {
                    continue;
                }
                $pj = $this->ref['ref_penanggung_jawab'][$v] ?? null; // kolom pengusul/PJ berisi kode PJWB
                DB::table('psn_kelembagaan')->insert(['psn_id' => $psnId, 'peran' => $nama, 'penanggung_jawab_id' => $pj,
                    'nama_teks' => $pj ? null : $v, 'urutan' => ++$urutan] + $this->jejak($p, false));
                $this->hitung('psn_kelembagaan');
            }
        }
    }

    /** psn.pendanaan berformat campur (JSON array atau satu kode); dipakai sebagai pelengkap psn_pendanaan. */
    protected function sumberDanaDariKolom(int $psnId, object $p): void
    {
        $raw = trim((string) $p->pendanaan);
        if ($raw === '') {
            return;
        }
        $kode = Str::startsWith($raw, '[') ? (json_decode($raw, true) ?: []) : [$raw];
        foreach ($kode as $k) {
            if ($dana = $this->kode('ref_sumber_dana', $k, "psn.pendanaan psn#{$p->id}")) {
                $this->hitung('psn_sumber_dana', DB::table('psn_sumber_dana')->insertOrIgnore(['psn_id' => $psnId, 'sumber_dana_id' => $dana] + $this->jejak($p, false)));
            }
        }
    }

    protected function salinPerPsn(string $lama, string $baru, \Closure $map): void
    {
        foreach ($this->legacy->table($lama)->orderBy('id')->get() as $r) {
            if (! ($psn = $this->psnId($r->psn_id, $lama)) || ! ($v = $map($r))) {
                continue;
            }
            $softDelete = ! in_array($baru, ['psn_sdgs'], true);
            DB::table($baru)->insert(['psn_id' => $psn] + $v + $this->jejak($r, $softDelete));
            $this->hitung($baru);
        }
    }

    protected function targetTahunan(string $tabel, string $fk, int $id, object $r, array $tahun, array $extra = []): void
    {
        foreach ($tahun as $th) {
            [$t, $re] = [$this->angka($r->{"target_{$th}"} ?? null), $this->angka($r->{"realisasi_{$th}"} ?? null)];
            if ($this->kosongSemua($t, $re)) {
                continue;
            }
            DB::table($tabel)->insert([$fk => $id, 'tahun' => $th, 'target' => $t, 'realisasi' => $re] + $extra + $this->jejak($r, false));
            $this->hitung($tabel);
        }
    }

    protected function targetKegiatanTahunan(int $kegiatanId, object $r): void
    {
        foreach ([2026, 2027, 2028, 2029] as $th) {
            $v = [
                'target_1' => $this->angka($r->{"target_1_{$th}"}), 'target_2' => $this->angka($r->{"target_2_{$th}"}),
                'pagu_rp' => $this->angka($r->{"anggaran_{$th}"}),
                'realisasi_1' => $this->angka($r->{"realisasi_target_1_{$th}"}), 'realisasi_2' => $this->angka($r->{"realisasi_target_2_{$th}"}),
                'realisasi_anggaran_rp' => $this->angka($r->{"realisasi_anggaran_{$th}"}),
            ];
            if ($this->kosongSemua(...array_values($v))) {
                continue;
            }
            $adaRealisasi = ! $this->kosongSemua($v['realisasi_1'], $v['realisasi_2'], $v['realisasi_anggaran_rp']);
            DB::table('kegiatan_target')->insert(['kegiatan_id' => $kegiatanId, 'tahun' => $th, 'periode' => 'TAHUNAN', 'periode_ke' => 0,
                'dilaporkan_at' => $adaRealisasi ? $this->waktu($r->updated_at) : null] + $v + $this->jejak($r, false));
            $this->hitung('kegiatan_target');
        }
    }

    // ------------------------------------------------------------------ bantu: peta & referensi

    protected function muatRef(): void
    {
        if ($this->ref) {
            return;
        }
        foreach (['ref_klaster', 'ref_sub_klaster', 'ref_klaster_pkpn', 'ref_program', 'ref_status_psn', 'ref_sumber_dana',
            'ref_unit_kerja', 'ref_instansi', 'ref_penanggung_jawab'] as $t) {
            $this->ref[$t] = DB::table($t)->pluck('id', 'kode')->all();
        }
        $this->ref['ref_wilayah'] = DB::table('ref_wilayah')->where('level', '<=', 2)->pluck('level', 'kode')->all();
        foreach (DB::table('ref_kode')->get() as $r) {
            $this->ref['ref_kode'][$r->tipe][$r->kode] = $r->id;
        }
        $this->muatPengguna();
    }

    protected function muatPengguna(): void
    {
        $this->userId = [];
        foreach (DB::table('users')->get(['id', 'username', 'email']) as $u) {
            $this->userId[Str::lower($u->username)] = $u->id;
            $this->userId[Str::lower($u->email)] = $u->id;
        }
    }

    /** Saat langkah dilewati (--only), muat ulang peta legacy_id -> id dari data yang sudah ada. */
    protected function muatPetaTersimpan(string $langkah): void
    {
        $sumber = [
            'pengguna' => fn () => $this->muatPengguna(),
            'psn' => fn () => $this->peta['psn'] = DB::table('psn')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all(),
            'kinerja' => fn () => $this->peta['trisula'] = DB::table('trisula')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all(),
            'kegiatan' => function () {
                foreach (DB::table('kegiatan')->whereNotNull('legacy_ref')->get(['id', 'legacy_ref']) as $k) {
                    [$t, $lid] = explode(':', $k->legacy_ref);
                    $this->peta[$t === 'psn_kegiatan' ? 'kegiatan' : 'kegiatan_cp'][(int) $lid] = $k->id;
                }
            },
            'risiko' => function () {
                $this->peta['risiko'] = DB::table('risiko')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();
                $this->peta['regulasi'] = DB::table('regulasi')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();
            },
            'monev' => fn () => $this->peta['monev'] = DB::table('monev')->whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all(),
        ];
        isset($sumber[$langkah]) && $sumber[$langkah]();
    }

    protected function psnId($legacyPsnId, string $konteks): ?int
    {
        $id = $this->peta['psn'][(int) $legacyPsnId] ?? null;
        if (! $id) {
            $this->peringatan('psn_tidak_ditemukan', "{$konteks}: psn_id {$legacyPsnId}");
        }

        return $id;
    }

    protected function kode(string $tabel, $kode, ?string $konteks = null): ?int
    {
        $this->muatRef();
        $k = trim((string) $kode);
        if ($k === '' || $k === '.' || $k === '-') {
            return null;
        }
        $id = $this->ref[$tabel][$k] ?? null;
        if (! $id && $konteks) {
            $this->peringatan('kode_tidak_dikenal', "{$tabel} '{$k}' ({$konteks})");
        }

        return $id;
    }

    protected function refKode(string $tipe, $kode, ?string $konteks = null): ?int
    {
        $this->muatRef();
        $k = trim((string) $kode);
        if ($k === '') {
            return null;
        }
        $id = $this->ref['ref_kode'][$tipe][$k] ?? null;
        if (! $id && $konteks) {
            $this->peringatan('kode_tidak_dikenal', "ref_kode {$tipe} '{$k}' ({$konteks})");
        }

        return $id;
    }

    // ------------------------------------------------------------------ bantu: pembersihan nilai

    protected function jejak(object $r, bool $softDelete = true): array
    {
        $by = fn ($v) => $v ? ($this->userId[Str::lower(trim((string) $v))] ?? null) : null;
        $j = [
            'created_by' => $by($r->created_by ?? null),
            'updated_by' => $by($r->updated_by ?? null),
            'created_at' => $this->waktu($r->created_at ?? null) ?? now(),
            'updated_at' => $this->waktu($r->updated_at ?? null) ?? $this->waktu($r->created_at ?? null) ?? now(),
        ];
        if ($softDelete) {
            $j['deleted_by'] = $by($r->deleted_by ?? null);
            $j['deleted_at'] = $this->waktu($r->deleted_at ?? null);
        }

        return $j;
    }

    /** Teks bersih: buang karakter pengganti (U+FFFD) & NBSP hasil salah encoding, kosong -> null. */
    protected function teks($v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = str_replace(["\u{FFFD}", "\u{00A0}", "\r\n"], [' ', ' ', "\n"], mb_convert_encoding((string) $v, 'UTF-8', 'UTF-8'));
        $v = trim(preg_replace('/[ \t]+/u', ' ', $v));

        return in_array($v, ['', '-', '.', 'NULL'], true) ? null : $v;
    }

    protected function potong($v, int $maks = 255): ?string
    {
        $v = $this->teks($v);

        return $v === null ? null : Str::limit($v, $maks - 3);
    }

    protected function angka($v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    protected function tahun($v): ?int
    {
        $v = (int) $v;

        return $v >= 1900 && $v <= 2100 ? $v : null;
    }

    protected function waktu($v): ?Carbon
    {
        if (! $v || str_starts_with((string) $v, '0000')) {
            return null;
        }

        return Carbon::parse($v);
    }

    protected function level($v): ?string
    {
        $v = Str::lower(trim((string) $v));

        return ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'sangat tinggi' => 'Sangat Tinggi'][$v] ?? null;
    }

    protected function kosongSemua(...$nilai): bool
    {
        foreach ($nilai as $n) {
            if ($n !== null && (float) $n != 0.0) {
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------ laporan

    protected function hitung(string $tabel, int $n = 1): void
    {
        $this->laporan['jumlah'][$tabel] = ($this->laporan['jumlah'][$tabel] ?? 0) + $n;
    }

    protected function peringatan(string $jenis, string $pesan): void
    {
        $this->laporan['peringatan'][$jenis][] = $pesan;
    }

    protected function info(string $pesan): void
    {
        $this->log && ($this->log)($pesan);
    }
}
