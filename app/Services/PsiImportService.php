<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\IntegrasiPsiLog;
use App\Models\UsulanDokumen;
use App\Models\UsulanJalurKritis;
use App\Models\UsulanPsi;
use App\Models\UsulanPsn;
use App\Models\UsulanSurat;
use App\Support\HtmlAman;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Menerima usulan dari aplikasi PSI (kontrak "psi.usulan.v1", docs/integrasi-psi.md):
 * validasi, pemetaan ke referensi SIRENDA, simpan idempoten per psi_id (versi naik = pembaruan).
 * Nilai yang tidak terpetakan (klaster, jenis instansi, provinsi) dicatat sebagai peringatan,
 * tidak dibuang diam-diam.
 */
class PsiImportService
{
    public const JENIS_DOKUMEN = ['PDF_USULAN', 'SURAT_PENGUSULAN', 'LAMPIRAN'];

    public function aturan(): array
    {
        return [
            'psi_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'versi' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['DIAJUKAN', 'DIREVISI'])],
            'diajukan_at' => ['nullable', 'date'],
            'tahun_rkp' => ['required', 'integer', 'min:2024', 'max:2100'],
            'surat' => ['nullable', 'array'],
            'surat.psi_surat_id' => ['required_with:surat', 'string', 'max:64'],
            'surat.nomor' => ['nullable', 'string', 'max:150'],
            'surat.sifat' => ['nullable', 'string', 'max:50'],
            'surat.lampiran' => ['nullable', 'string', 'max:150'],
            'surat.tempat' => ['nullable', 'string', 'max:100'],
            'surat.tanggal' => ['nullable', 'date'],
            'surat.ditujukan_kepada' => ['nullable', 'string', 'max:2000'],
            'surat.perihal' => ['nullable', 'string', 'max:2000'],
            'pengusul' => ['required', 'array'],
            'pengusul.instansi' => ['required', 'string', 'max:255'],
            'pengusul.jenis_instansi' => ['nullable', 'string', 'max:100'],
            'pengusul.kontak' => ['nullable', 'array'],
            'pengusul.kontak.nama' => ['nullable', 'string', 'max:255'],
            'pengusul.kontak.jabatan' => ['nullable', 'string', 'max:255'],
            'pengusul.kontak.email' => ['nullable', 'email', 'max:255'],
            'pengusul.kontak.telepon' => ['nullable', 'string', 'max:50'],
            'data_umum' => ['required', 'array'],
            'data_umum.nama' => ['required', 'string', 'max:500'],
            'data_umum.sektor' => ['nullable', 'string', 'max:255'],
            'data_umum.klaster' => ['nullable', 'string', 'max:255'],
            'data_umum.klaster_kode' => ['nullable', 'string', 'max:20'],
            'data_umum.jenis_psn' => ['nullable', 'string', 'max:100'],
            'data_umum.nilai_investasi_rp' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999999999'],
            'data_umum.lokasi' => ['nullable', 'array', 'max:100'],
            'data_umum.lokasi.*.provinsi_kode' => ['required', 'string', 'max:13'],
            'data_umum.lokasi.*.kabupaten_kode' => ['nullable', 'string', 'max:13'],
            'rincian' => ['nullable', 'array'],
            'rincian.tujuan' => ['nullable', 'string', 'max:20000'],
            'rincian.sasaran' => ['nullable', 'string', 'max:20000'],
            'rincian.lingkup_pekerjaan_html' => ['nullable', 'string', 'max:2000000'],
            'rincian.bagian_lain' => ['nullable', 'array', 'max:50'],
            'rincian.bagian_lain.*.judul' => ['required', 'string', 'max:255'],
            'rincian.bagian_lain.*.isi_html' => ['nullable', 'string', 'max:2000000'],
            'kesiapan' => ['nullable', 'array'],
            'kesiapan.jalur_kritis' => ['nullable', 'array', 'max:200'],
            'kesiapan.jalur_kritis.*.kode' => ['required', 'string', 'max:30', 'distinct'],
            'kesiapan.jalur_kritis.*.kegiatan' => ['required', 'string', 'max:2000'],
            'kesiapan.jalur_kritis.*.target_selesai' => ['nullable', 'string', 'max:100'],
            'kesiapan.jalur_kritis.*.sebelumnya' => ['nullable', 'array'],
            'kesiapan.jalur_kritis.*.sebelumnya.*' => ['string', 'max:30'],
            'kesiapan.jalur_kritis.*.jalur_kritis' => ['nullable', 'boolean'],
            'kesiapan.penjelasan_html' => ['nullable', 'string', 'max:2000000'],
            'kesiapan.diagram_url' => ['nullable', 'url:https,http', 'max:1000'],
            'kesiapan.bagian_lain' => ['nullable', 'array', 'max:50'],
            'kesiapan.bagian_lain.*.judul' => ['required', 'string', 'max:255'],
            'kesiapan.bagian_lain.*.isi_html' => ['nullable', 'string', 'max:2000000'],
            'dokumen' => ['nullable', 'array', 'max:100'],
            'dokumen.*.jenis' => ['required', Rule::in(self::JENIS_DOKUMEN)],
            'dokumen.*.judul' => ['nullable', 'string', 'max:255'],
            'dokumen.*.nama_berkas' => ['nullable', 'string', 'max:255'],
            'dokumen.*.url' => ['required', 'url:https,http', 'max:1000'],
            'dokumen.*.ukuran' => ['nullable', 'integer', 'min:0'],
            'dokumen.*.sha256' => ['nullable', 'string', 'size:64'],
        ];
    }

    /**
     * @return array{usulan: UsulanPsn, baru: bool, diabaikan: bool, peringatan: string[]}
     */
    public function terima(array $payload, ?string $ip = null): array
    {
        $v = Validator::make($payload, $this->aturan());
        if ($v->fails()) {
            $this->log('USULAN', $payload['psi_id'] ?? null, null, 422, false, 'Validasi gagal: '.implode(' ', $v->errors()->all()), $payload, $ip);
            throw new ValidationException($v);
        }
        $d = $v->validated();

        // Satu kiriman = satu entri audit ringkas (sumber INTEGRASI_PSI); event model dimatikan agar
        // baris anak (jalur kritis, dokumen, lokasi) tidak membanjiri jejak audit/aktivitas.
        return Model::withoutEvents(fn () => DB::transaction(function () use ($d, $payload, $ip) {
            $psi = UsulanPsi::withTrashed()->where('psi_id', $d['psi_id'])->lockForUpdate()->first();

            // Versi lama / sama: diabaikan (idempoten), bukan galat.
            if ($psi && $d['versi'] <= $psi->versi) {
                $this->log('USULAN', $d['psi_id'], $psi->usulan_id, 200, true, "Versi {$d['versi']} diabaikan (tersimpan versi {$psi->versi}).", $payload, $ip);

                return ['usulan' => $psi->usulan, 'baru' => false, 'diabaikan' => true, 'peringatan' => $psi->peringatan_impor ?? []];
            }

            $peringatan = [];
            $du = $d['data_umum'];
            $klasterId = $this->petaKlaster($du['klaster_kode'] ?? null, $du['klaster'] ?? null, $peringatan);
            $jenisPengusul = $this->petaJenisInstansi($d['pengusul']['jenis_instansi'] ?? null, $peringatan);
            $jenisPsn = Str::lower(trim((string) ($du['jenis_psn'] ?? '')));

            $kolomUsulan = [
                'tahun_rkp' => $d['tahun_rkp'],
                'nama' => $du['nama'],
                'klaster_id' => $klasterId,
                'pengusul_teks' => $d['pengusul']['instansi'],
                'jenis_pengusul' => $jenisPengusul,
                'is_infrastruktur' => in_array($jenisPsn, config('psn_dashboard.psi.jenis_psn_infrastruktur'), true),
                'nilai_investasi_rp' => $du['nilai_investasi_rp'] ?? null,
            ];

            $baru = ! $psi;
            if ($baru) {
                $usulan = UsulanPsn::withoutGlobalScopes()->create($kolomUsulan + ['status' => 'DIAJUKAN']);
            } else {
                $usulan = UsulanPsn::withoutGlobalScopes()->withTrashed()->findOrFail($psi->usulan_id);
                // Penetapan PMO di SIRENDA tidak ditimpa oleh nilai PSI yang tidak dapat dipetakan.
                $kolomUsulan['klaster_id'] ??= $usulan->klaster_id;
                $kolomUsulan['jenis_pengusul'] ??= $usulan->jenis_pengusul;
                if ($jenisPsn === '') {
                    unset($kolomUsulan['is_infrastruktur']);
                }
                $usulan->fill($kolomUsulan)->save();
            }

            // Lokasi: diganti seluruhnya sesuai versi terbaru.
            $usulan->lokasi()->delete();
            $provValid = DB::table('ref_wilayah')->pluck('level', 'kode');
            foreach ($du['lokasi'] ?? [] as $l) {
                $prov = $l['provinsi_kode'];
                $kab = $l['kabupaten_kode'] ?? null;
                if (($provValid[$prov] ?? null) !== 1) {
                    $peringatan[] = "Kode provinsi \"{$prov}\" tidak dikenal; lokasi tidak disimpan.";

                    continue;
                }
                if ($kab !== null && ! isset($provValid[$kab])) {
                    $peringatan[] = "Kode kabupaten/kota \"{$kab}\" tidak dikenal; hanya provinsi yang disimpan.";
                    $kab = null;
                }
                $usulan->lokasi()->create(['provinsi_kode' => $prov, 'kabupaten_kode' => $kab]);
            }

            $surat = null;
            if (! empty($d['surat'])) {
                $s = $d['surat'];
                $surat = UsulanSurat::updateOrCreate(['psi_surat_id' => $s['psi_surat_id']], [
                    'nomor' => $s['nomor'] ?? null, 'sifat' => $s['sifat'] ?? null, 'lampiran' => $s['lampiran'] ?? null, 'tempat' => $s['tempat'] ?? null,
                    'tanggal' => $s['tanggal'] ?? null, 'ditujukan_kepada' => $s['ditujukan_kepada'] ?? null, 'perihal' => $s['perihal'] ?? null,
                    'instansi_pengusul' => $d['pengusul']['instansi'],
                ]);
            }

            $bagian = fn (?array $daftar) => $daftar ? array_values(array_map(fn ($b) => ['judul' => $b['judul'], 'isi_html' => HtmlAman::bersihkan($b['isi_html'] ?? null)], $daftar)) : null;
            $penilaianFinal = $usulan->penilaian()->where('status', 'FINAL')->exists();

            $psi ??= new UsulanPsi(['usulan_id' => $usulan->id, 'psi_id' => $d['psi_id'], 'diterima_at' => now()]);
            $psi->fill([
                'surat_id' => $surat?->id,
                'versi' => $d['versi'],
                'status_psi' => $d['status'] ?? ($baru ? 'DIAJUKAN' : 'DIREVISI'),
                'diajukan_at' => $d['diajukan_at'] ?? null,
                'diperbarui_at' => now(),
                'perlu_tinjau_ulang' => ! $baru && $penilaianFinal,
                'instansi_pengusul' => $d['pengusul']['instansi'],
                'jenis_instansi' => $d['pengusul']['jenis_instansi'] ?? null,
                'kontak' => $d['pengusul']['kontak'] ?? null,
                'sektor' => $du['sektor'] ?? null,
                'klaster_teks' => $du['klaster'] ?? $du['klaster_kode'] ?? null,
                'jenis_psn' => $du['jenis_psn'] ?? null,
                'tujuan' => $d['rincian']['tujuan'] ?? null,
                'sasaran' => $d['rincian']['sasaran'] ?? null,
                'lingkup_pekerjaan_html' => HtmlAman::bersihkan($d['rincian']['lingkup_pekerjaan_html'] ?? null),
                'rincian_lain' => $bagian($d['rincian']['bagian_lain'] ?? null),
                'penjelasan_jalur_kritis_html' => HtmlAman::bersihkan($d['kesiapan']['penjelasan_html'] ?? null),
                'diagram_jalur_kritis_url' => $d['kesiapan']['diagram_url'] ?? null,
                'kesiapan_lain' => $bagian($d['kesiapan']['bagian_lain'] ?? null),
                'peringatan_impor' => $peringatan ?: null,
                'payload_terakhir' => $payload,
            ]);
            if ($psi->trashed()) {
                $psi->restore();
            }
            $psi->save();

            // Jalur kritis & dokumen: diganti seluruhnya sesuai versi terbaru.
            UsulanJalurKritis::where('usulan_id', $usulan->id)->delete();
            $kodeAda = collect($d['kesiapan']['jalur_kritis'] ?? [])->pluck('kode')->all();
            foreach (array_values($d['kesiapan']['jalur_kritis'] ?? []) as $i => $j) {
                $pendahulu = array_values(array_intersect($j['sebelumnya'] ?? [], $kodeAda));
                if (count($pendahulu) < count($j['sebelumnya'] ?? [])) {
                    $peringatan[] = "Langkah jalur kritis {$j['kode']} merujuk kegiatan sebelumnya yang tidak ada.";
                }
                UsulanJalurKritis::create(['usulan_id' => $usulan->id, 'urutan' => $i + 1, 'kode' => $j['kode'], 'kegiatan' => $j['kegiatan'],
                    'target_selesai' => $j['target_selesai'] ?? null, 'sebelumnya' => $pendahulu, 'is_jalur_kritis' => (bool) ($j['jalur_kritis'] ?? false)]);
            }
            UsulanDokumen::where('usulan_id', $usulan->id)->delete();
            foreach ($d['dokumen'] ?? [] as $dok) {
                UsulanDokumen::create(['usulan_id' => $usulan->id] + array_intersect_key($dok, array_flip(['jenis', 'judul', 'nama_berkas', 'url', 'ukuran', 'sha256'])));
            }
            if ($peringatan !== ($psi->peringatan_impor ?? [])) {
                $psi->forceFill(['peringatan_impor' => $peringatan ?: null])->saveQuietly();
            }

            AuditLog::create(['user_label' => 'integrasi-psi', 'tabel' => 'usulan_psn', 'record_id' => $usulan->id, 'aksi' => $baru ? 'CREATE' : 'UPDATE',
                'nilai_baru' => ['psi_id' => $d['psi_id'], 'versi' => $d['versi'], 'nama' => $du['nama']], 'sumber' => 'INTEGRASI_PSI', 'ip_address' => $ip]);
            $this->log('USULAN', $d['psi_id'], $usulan->id, $baru ? 201 : 200, true,
                ($baru ? 'Usulan baru diterima' : "Usulan diperbarui ke versi {$d['versi']}").($peringatan ? '; '.count($peringatan).' peringatan pemetaan' : '').'.', $payload, $ip);

            return ['usulan' => $usulan, 'baru' => $baru, 'diabaikan' => false, 'peringatan' => $peringatan];
        }));
    }

    /** Pengusul menarik usulan di aplikasi PSI. */
    public function tarik(string $psiId, ?string $alasan, ?string $ip = null): UsulanPsi
    {
        $psi = UsulanPsi::where('psi_id', $psiId)->firstOrFail();
        $psi->update(['status_psi' => 'DITARIK', 'diperbarui_at' => now()]);
        $this->log('TARIK', $psiId, $psi->usulan_id, 200, true, 'Usulan ditarik pengusul'.($alasan ? ": {$alasan}" : '.'), ['alasan' => $alasan], $ip);

        return $psi;
    }

    protected function petaKlaster(?string $kode, ?string $nama, array &$peringatan): ?int
    {
        if ($kode && ($id = DB::table('ref_klaster')->where('kode', $kode)->value('id'))) {
            return (int) $id;
        }
        if ($nama && ($id = DB::table('ref_klaster')->whereRaw('LOWER(TRIM(nama)) = ?', [Str::lower(trim($nama))])->value('id'))) {
            return (int) $id;
        }
        if ($kode || $nama) {
            $peringatan[] = 'Klaster "'.($nama ?? $kode).'" tidak ditemukan di referensi SIRENDA; klaster perlu ditetapkan manual.';
        }

        return null;
    }

    protected function petaJenisInstansi(?string $jenis, array &$peringatan): ?string
    {
        if ($jenis === null || trim($jenis) === '') {
            return null;
        }
        $peta = config('psn_dashboard.psi.jenis_instansi');
        $hasil = $peta[Str::lower(trim($jenis))] ?? null;
        if (! $hasil) {
            $peringatan[] = "Jenis instansi \"{$jenis}\" tidak dikenali; jenis pengusul perlu ditetapkan manual (memengaruhi sub-kriteria KP4–KP6).";
        }

        return $hasil;
    }

    public function log(string $aksi, ?string $psiId, ?int $usulanId, ?int $http, bool $ok, string $pesan, ?array $payload = null, ?string $ip = null, string $arah = 'MASUK', ?string $respons = null): void
    {
        IntegrasiPsiLog::create(['arah' => $arah, 'aksi' => $aksi, 'psi_id' => $psiId, 'usulan_id' => $usulanId, 'status_http' => $http, 'berhasil' => $ok,
            'pesan' => Str::limit($pesan, 2000), 'payload' => $payload, 'respons' => $respons ? Str::limit($respons, 5000) : null, 'ip_address' => $ip]);
    }
}
