<?php

namespace App\Services;

use App\Enums\Rekomendasi;
use App\Models\Penilaian;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penilaian usulan PSN (Permen PPN/Bappenas No. 4/2025, format Rapat Pleno II).
 *
 * - GATE: Kriteria Utama KU1-KU3 bernilai Ya(1)/Tidak(0). Satu saja "Tidak" =>
 *   rekomendasi DITOLAK berapa pun nilainya (hard gate, tidak dapat dilunakkan).
 * - Skor komponen = Σ skor ÷ (3 × jumlah sub-kriteria yang berlaku) × 100,
 *   hanya dihitung bila seluruh sub-kriteria yang berlaku sudah dinilai.
 * - Nilai akhir = Σ bobot × skor komponen (bobot di config psn_dashboard.penilaian).
 * - Sub-kriteria kondisional (jenis pengusul / usulan infrastruktur) yang tidak
 *   berlaku dikeluarkan dari penyebut.
 */
class ScoringService
{
    public const KOMPONEN = ['PENDUKUNG', 'KESIAPAN', 'LOKASI', 'TRISULA'];

    protected array $cfg;

    public function __construct(?array $config = null)
    {
        $this->cfg = $config ?? config('psn_dashboard.penilaian');
    }

    /**
     * @param  array<int, array{id:int, kelompok:string, kode:string, tipe_nilai:string, kondisional:?string}>  $kriteria
     * @param  array<int, ?int>  $nilai  kriteria_id => nilai
     * @param  array{jenis_pengusul?: ?string, is_infrastruktur?: bool}  $konteks
     */
    public function hitung(array $kriteria, array $nilai, array $konteks = []): array
    {
        $peringatan = [];
        if (empty($konteks['jenis_pengusul'])) {
            $peringatan[] = 'Jenis pengusul belum diisi; sub-kriteria khusus pengusul (KP4-KP6) belum dapat ditentukan.';
        }

        $berlaku = array_values(array_filter($kriteria, fn ($k) => $this->berlaku($k, $konteks)));
        foreach ($berlaku as $k) {
            $this->validasi($k, $nilai[$k['id']] ?? null);
        }

        // Gate Kriteria Utama.
        $utama = array_filter($berlaku, fn ($k) => $k['kelompok'] === 'UTAMA');
        $gagal = array_values(array_map(fn ($k) => $k['kode'], array_filter($utama, fn ($k) => ($nilai[$k['id']] ?? null) === 0)));
        $belumGate = array_values(array_map(fn ($k) => $k['kode'], array_filter($utama, fn ($k) => ($nilai[$k['id']] ?? null) === null)));
        $gateLulus = $gagal ? false : ($belumGate || ! $utama ? null : true);

        // Komponen skor.
        $komponen = [];
        foreach (self::KOMPONEN as $kel) {
            $sub = array_filter($berlaku, fn ($k) => $k['kelompok'] === $kel);
            $belum = array_values(array_map(fn ($k) => $k['kode'], array_filter($sub, fn ($k) => ($nilai[$k['id']] ?? null) === null)));
            $jumlah = array_sum(array_map(fn ($k) => (int) ($nilai[$k['id']] ?? 0), $sub));
            $n = count($sub);
            $komponen[$kel] = [
                'bobot' => $this->cfg['bobot'][$kel],
                'jumlah_sub' => $n,
                'total_skor' => $jumlah,
                'skor' => $n > 0 && ! $belum ? round($jumlah / ($this->cfg['skor_maks_sub_kriteria'] * $n) * 100, 2) : null,
                'belum_dinilai' => $belum,
            ];
        }

        $semuaKomponen = ! array_filter($komponen, fn ($k) => $k['skor'] === null);
        $lengkap = $gateLulus !== null && $semuaKomponen && ! empty($konteks['jenis_pengusul']);
        $nilaiAkhir = $semuaKomponen
            ? round(array_sum(array_map(fn ($k) => $k['bobot'] * $k['skor'], $komponen)), 2)
            : null;

        return [
            'gate' => ['lulus' => $gateLulus, 'gagal' => $gagal, 'belum_dinilai' => $belumGate],
            'komponen' => $komponen,
            'nilai_akhir' => $nilaiAkhir,
            'lengkap' => $lengkap,
            'rekomendasi' => $this->rekomendasi($gateLulus, $nilaiAkhir, $lengkap),
            'peringatan' => $peringatan,
        ];
    }

    public function rekomendasi(?bool $gateLulus, ?float $nilaiAkhir, bool $lengkap): Rekomendasi
    {
        // Hard gate: tidak bergantung pada kelengkapan maupun nilai.
        if ($gateLulus === false) {
            return Rekomendasi::Ditolak;
        }
        if (! $lengkap || $nilaiAkhir === null) {
            return Rekomendasi::BelumLengkap;
        }

        $ambang = $this->cfg['ambang'];
        if ($ambang['direkomendasikan'] === null || $ambang['dipertimbangkan'] === null) {
            return Rekomendasi::AmbangBelumDitetapkan;
        }

        return match (true) {
            $nilaiAkhir >= $ambang['direkomendasikan'] => Rekomendasi::Direkomendasikan,
            $nilaiAkhir >= $ambang['dipertimbangkan'] => Rekomendasi::Dipertimbangkan,
            default => Rekomendasi::TidakDirekomendasikan,
        };
    }

    public function berlaku(array $k, array $konteks): bool
    {
        $jenis = $konteks['jenis_pengusul'] ?? null;

        return match ($k['kondisional']) {
            null, '' => true,
            'PENGUSUL_KL' => $jenis === 'KL',
            'PENGUSUL_PEMDA' => $jenis === 'PEMDA',
            'PENGUSUL_BU' => $jenis === 'BUMN_SWASTA',
            'INFRASTRUKTUR' => (bool) ($konteks['is_infrastruktur'] ?? false),
            default => true,
        };
    }

    protected function validasi(array $k, ?int $v): void
    {
        if ($v === null) {
            return;
        }
        $maks = $k['tipe_nilai'] === 'YA_TIDAK' ? 1 : $this->cfg['skor_maks_sub_kriteria'];
        if ($v < 0 || $v > $maks) {
            throw new InvalidArgumentException("Nilai {$k['kode']} harus 0-{$maks}, diberikan {$v}.");
        }
    }

    /** Hitung dari basis data dan simpan hasilnya sebagai cache di baris penilaian. */
    public function hitungPenilaian(Penilaian $p, bool $simpan = true): array
    {
        $p->loadMissing('usulan');
        $kriteria = DB::table('ref_kriteria')->where('is_aktif', true)->orderBy('urutan')
            ->get(['id', 'kelompok', 'kode', 'tipe_nilai', 'kondisional', 'uraian'])->map(fn ($k) => (array) $k)->all();
        $nilai = DB::table('penilaian_skor')->where('penilaian_id', $p->id)->pluck('nilai', 'kriteria_id')
            ->map(fn ($v) => $v === null ? null : (int) $v)->all();

        $hasil = $this->hitung($kriteria, $nilai, [
            'jenis_pengusul' => $p->usulan->jenis_pengusul,
            'is_infrastruktur' => (bool) $p->usulan->is_infrastruktur,
        ]);

        if ($simpan) {
            $p->forceFill([
                'gate_lulus' => $hasil['gate']['lulus'],
                'skor_pendukung' => $hasil['komponen']['PENDUKUNG']['skor'],
                'skor_kesiapan' => $hasil['komponen']['KESIAPAN']['skor'],
                'skor_lokasi' => $hasil['komponen']['LOKASI']['skor'],
                'skor_trisula' => $hasil['komponen']['TRISULA']['skor'],
                'nilai_akhir' => $hasil['nilai_akhir'],
                'rekomendasi' => $hasil['rekomendasi']->value,
            ])->save();
        }

        return $hasil + ['kriteria' => $kriteria, 'nilai' => $nilai];
    }
}
