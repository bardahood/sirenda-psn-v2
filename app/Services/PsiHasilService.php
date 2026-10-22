<?php

namespace App\Services;

use App\Enums\Rekomendasi;
use App\Models\AuditLog;
use App\Models\Penilaian;
use App\Models\UsulanPsn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Hasil proses SIRENDA untuk satu usulan PSI (kontrak "sirenda.hasil.v1"): penilaian FINAL,
 * catatan/keputusan pleno, dan rekomendasi resmi. Dikirim ke PSI (push) bila PSI_API_URL diisi,
 * dan selalu dapat diambil PSI lewat GET /api/integrasi/psi/v1/usulan/{psi_id}/hasil (pull).
 */
class PsiHasilService
{
    public function __construct(protected ScoringService $scoring, protected AlurUsulan $alur, protected PsiImportService $impor) {}

    public function payload(UsulanPsn $u): array
    {
        $u->loadMissing(['psi', 'plenoUsulan.pleno', 'rekomendasiFinal.penetap']);
        $penilaian = Penilaian::where('usulan_id', $u->id)->where('status', 'FINAL')->latest('id')->first();
        $hasil = $penilaian ? $this->scoring->hitungPenilaian($penilaian, simpan: false) : null;
        $temuan = $penilaian ? DB::table('penilaian_skor')->where('penilaian_id', $penilaian->id)->pluck('temuan', 'kriteria_id') : collect();
        $rek = $u->rekomendasiFinal;

        return [
            'skema' => 'sirenda.hasil.v1',
            'psi_id' => $u->psi?->psi_id,
            'sirenda_id' => $u->id,
            'versi_usulan' => $u->psi?->versi,
            'tahap' => $this->alur->tahap($u),
            'penilaian' => $penilaian ? [
                'id' => $penilaian->id,
                'forum' => $penilaian->forum,
                'tanggal' => $penilaian->tanggal?->toDateString(),
                'gate_lulus' => $hasil['gate']['lulus'],
                'gate_gagal' => $hasil['gate']['gagal'],
                'skor_komponen' => collect($hasil['komponen'])->map(fn ($k) => $k['skor'])->all(),
                'nilai_akhir' => $hasil['nilai_akhir'],
                'rekomendasi_sistem' => $hasil['rekomendasi']->value,
                'catatan' => $penilaian->catatan,
                'kriteria' => collect($hasil['kriteria'])->map(fn ($k) => [
                    'kode' => $k['kode'], 'kelompok' => $k['kelompok'], 'uraian' => $k['uraian'],
                    'berlaku' => $this->scoring->berlaku($k, ['jenis_pengusul' => $u->jenis_pengusul, 'is_infrastruktur' => (bool) $u->is_infrastruktur]),
                    'nilai' => $hasil['nilai'][$k['id']] ?? null, 'temuan' => $temuan[$k['id']] ?? null,
                ])->values()->all(),
            ] : null,
            'pleno' => $u->plenoUsulan->filter(fn ($p) => $p->keputusan)->map(fn ($p) => [
                'nama' => $p->pleno->nama, 'tanggal' => $p->pleno->tanggal->toDateString(),
                'keputusan' => $p->keputusan, 'keputusan_label' => $p->labelKeputusan(),
                'catatan' => $p->catatan, 'tindak_lanjut' => $p->tindak_lanjut,
            ])->values()->all(),
            'rekomendasi' => $rek ? [
                'nilai' => $rek->rekomendasi, 'label' => Rekomendasi::from($rek->rekomendasi)->label(),
                'catatan' => $rek->catatan, 'nomor_dokumen' => $rek->nomor_dokumen, 'tanggal' => $rek->tanggal->toDateString(),
                'ditetapkan_oleh' => $rek->penetap?->name, 'ditetapkan_at' => $rek->ditetapkan_at?->toIso8601String(),
            ] : null,
            'dibuat_at' => now()->toIso8601String(),
        ];
    }

    public function pushAktif(): bool
    {
        return (bool) config('psn_dashboard.psi.url_api');
    }

    /** Kirim hasil ke PSI (push). Melempar RuntimeException bila gagal; setiap percobaan dicatat. */
    public function kirim(UsulanPsn $u): array
    {
        $psi = $u->psi ?? throw new RuntimeException('Usulan ini tidak berasal dari aplikasi PSI.');
        if (! $u->rekomendasiFinal) {
            throw new RuntimeException('Rekomendasi resmi belum ditetapkan FINAL.');
        }
        if (! $this->pushAktif()) {
            throw new RuntimeException('Alamat API PSI (PSI_API_URL) belum dikonfigurasi. PSI tetap dapat mengambil hasil melalui API SIRENDA.');
        }

        $payload = $this->payload($u);
        $url = rtrim(config('psn_dashboard.psi.url_api'), '/').'/usulan/'.rawurlencode($psi->psi_id).'/hasil-penilaian';
        $psi->increment('kirim_percobaan');

        try {
            $r = Http::withToken((string) config('psn_dashboard.psi.token_keluar'))->acceptJson()
                ->timeout(config('psn_dashboard.psi.timeout_detik'))->post($url, $payload);
            $ok = $r->successful();
            $this->impor->log('KIRIM_HASIL', $psi->psi_id, $u->id, $r->status(), $ok, $ok ? 'Hasil terkirim ke PSI.' : 'PSI menolak kiriman (HTTP '.$r->status().').', $payload, null, 'KELUAR', $r->body());
        } catch (Throwable $e) {
            $ok = false;
            $this->impor->log('KIRIM_HASIL', $psi->psi_id, $u->id, null, false, 'Gagal menghubungi PSI: '.$e->getMessage(), $payload, null, 'KELUAR');
        }

        $psi->forceFill(['kirim_status' => $ok ? 'TERKIRIM' : 'GAGAL', 'kirim_terakhir_at' => now()])->save();
        AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username ?? 'sistem', 'tabel' => 'usulan_psi', 'record_id' => $psi->id,
            'psn_id' => $u->psn_id, 'aksi' => 'PUBLISH', 'nilai_baru' => ['kirim_status' => $ok ? 'TERKIRIM' : 'GAGAL', 'rekomendasi' => $payload['rekomendasi']['nilai'] ?? null]]);

        if (! $ok) {
            throw new RuntimeException('Pengiriman ke PSI gagal. Lihat riwayat integrasi untuk rinciannya; coba kirim ulang.');
        }

        return $payload;
    }
}
