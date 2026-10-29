<?php

namespace App\Services;

use App\Enums\Rekomendasi;
use App\Models\AuditLog;
use App\Models\Penilaian;
use App\Models\UsulanPsn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Hasil proses SIRENDA untuk satu usulan PSI (kontrak "sirenda.hasil.v1"): penilaian FINAL,
 * catatan/keputusan pleno, dan rekomendasi resmi. Dikirim ke SIF lewat POST /{kode_usulan}/penilaian
 * (payloadSif), dan tetap dapat diambil lewat API masuk SIRENDA GET /api/integrasi/psi/v1/usulan/{psi_id}/hasil.
 */
class PsiHasilService
{
    public function __construct(protected ScoringService $scoring, protected AlurUsulan $alur, protected PsiImportService $impor, protected SifClient $sif) {}

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
        return $this->sif->aktif();
    }

    /**
     * Body POST /{kode_usulan}/penilaian SIF: satu skor 0-100 (nilai akhir penilaian FINAL) dan catatan PMO
     * yang merangkum rekomendasi resmi, keputusan pleno, komponen skor, dan temuan per kriteria.
     */
    public function payloadSif(UsulanPsn $u): array
    {
        $h = $this->payload($u);
        $p = $h['penilaian'];
        $angka = fn ($v) => $v === null ? '-' : number_format((float) $v, 2, ',', '.');
        $baris = [];
        if ($r = $h['rekomendasi']) {
            $baris[] = "Rekomendasi PMO: {$r['label']}".($r['nomor_dokumen'] ? " (No. {$r['nomor_dokumen']})" : '').", ditetapkan {$r['tanggal']}.";
            $r['catatan'] && $baris[] = "Catatan rekomendasi: {$r['catatan']}";
        }
        foreach ($h['pleno'] as $pl) {
            $baris[] = "Keputusan pleno {$pl['nama']} ({$pl['tanggal']}): {$pl['keputusan_label']}."
                .($pl['catatan'] ? " Catatan: {$pl['catatan']}" : '').($pl['tindak_lanjut'] ? " Tindak lanjut: {$pl['tindak_lanjut']}" : '');
        }
        if ($p) {
            $baris[] = 'Nilai akhir '.$angka($p['nilai_akhir']).'; gate KU1-KU3 '.($p['gate_lulus'] === false ? 'tidak lulus ('.implode(', ', $p['gate_gagal']).')' : 'lulus').'.';
            $baris[] = 'Skor komponen: '.collect($p['skor_komponen'])->map(fn ($v, $k) => Str::headline(Str::lower($k)).' '.$angka($v))->implode('; ').'.';
            $p['catatan'] && $baris[] = "Catatan penilaian: {$p['catatan']}";
            $temuan = collect($p['kriteria'])->filter(fn ($k) => $k['berlaku'] && filled($k['temuan']))->map(fn ($k) => "{$k['kode']}: {$k['temuan']}");
            $temuan->isNotEmpty() && $baris[] = "Temuan per kriteria:\n".$temuan->implode("\n");
        }

        return ['skor_pmo' => $p['nilai_akhir'] ?? null, 'catatan_pmo' => Str::limit(implode("\n", $baris), 10000, '')];
    }

    /** Kirim hasil ke SIF. Melempar RuntimeException bila gagal; setiap percobaan dicatat. */
    public function kirim(UsulanPsn $u): array
    {
        $psi = $u->psi ?? throw new RuntimeException('Usulan ini tidak berasal dari aplikasi PSI.');
        if (! $u->rekomendasiFinal) {
            throw new RuntimeException('Rekomendasi resmi belum ditetapkan FINAL.');
        }
        if ($k = $this->sif->kendala()) {
            throw new RuntimeException($k);
        }
        $payload = $this->payloadSif($u);
        if ($payload['skor_pmo'] === null) {
            throw new RuntimeException('Nilai akhir penilaian FINAL belum tersedia, sehingga skor PMO belum dapat dikirim.');
        }
        $psi->increment('kirim_percobaan');

        try {
            $r = $this->sif->kirimPenilaian($psi->psi_id, $payload);
            $ok = true;
            $this->impor->log('KIRIM_HASIL', $psi->psi_id, $u->id, 200, true, 'Hasil penilaian terkirim ke SIF (skor '.($r['data']['skor_pmo'] ?? $payload['skor_pmo']).').', $payload, null, 'KELUAR', json_encode($r['data'] ?? null));
        } catch (Throwable $e) {
            $ok = false;
            $this->impor->log('KIRIM_HASIL', $psi->psi_id, $u->id, $e instanceof SifException ? $e->getCode() : null, false, 'Gagal mengirim ke SIF: '.$e->getMessage(), $payload, null, 'KELUAR');
        }

        $psi->forceFill(['kirim_status' => $ok ? 'TERKIRIM' : 'GAGAL', 'kirim_terakhir_at' => now()])->save();
        AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username ?? 'sistem', 'tabel' => 'usulan_psi', 'record_id' => $psi->id,
            'psn_id' => $u->psn_id, 'aksi' => 'PUBLISH', 'nilai_baru' => ['kirim_status' => $ok ? 'TERKIRIM' : 'GAGAL', 'skor_pmo' => $payload['skor_pmo'],
                'rekomendasi' => $u->rekomendasiFinal->rekomendasi]]);

        if (! $ok) {
            throw new RuntimeException('Pengiriman ke SIF gagal. Lihat tab Riwayat untuk rinciannya; coba kirim ulang.');
        }

        return $payload;
    }
}
