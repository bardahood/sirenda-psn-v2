<?php

namespace App\Services;

use App\Models\UsulanPsn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tahap alur usulan PSN, diturunkan dari data (tidak disimpan):
 * DITERIMA -> PENILAIAN -> DINILAI -> PLENO -> REKOMENDASI -> TERKIRIM (khusus usulan PSI).
 * DITARIK bila pengusul menarik usulan di aplikasi PSI.
 */
class AlurUsulan
{
    public const TAHAP = [
        'DITERIMA' => 'Diterima',
        'PENILAIAN' => 'Sedang dinilai',
        'DINILAI' => 'Penilaian final',
        'PLENO' => 'Dibahas pleno',
        'REKOMENDASI' => 'Rekomendasi ditetapkan',
        'TERKIRIM' => 'Terkirim ke PSI',
        'DITARIK' => 'Ditarik pengusul',
    ];

    /** Langkah alur bisnis (untuk stepper), urut. */
    public const LANGKAH = [
        'DITERIMA' => ['Usulan diterima', 'Data usulan masuk dari aplikasi PSI (atau diinput di SIRENDA).'],
        'DINILAI' => ['Penilaian', 'Skor kriteria diisi dan ditetapkan FINAL.'],
        'PLENO' => ['Pleno', 'Hasil penilaian dibahas dan keputusan pleno dicatat.'],
        'REKOMENDASI' => ['Rekomendasi', 'Rekomendasi resmi ditetapkan.'],
        'TERKIRIM' => ['Kirim ke PSI', 'Hasil penilaian, catatan pleno, dan rekomendasi dikirim ke PSI.'],
    ];

    /** Ekspresi SQL tahap untuk query yang memuat usulan_psn (alias tabel) + left join usulan_psi (alias ps). */
    public static function sql(string $u = 'usulan_psn', string $ps = 'ps'): string
    {
        return "CASE
            WHEN {$ps}.status_psi = 'DITARIK' THEN 'DITARIK'
            WHEN {$ps}.kirim_status = 'TERKIRIM' THEN 'TERKIRIM'
            WHEN EXISTS (SELECT 1 FROM usulan_rekomendasi r WHERE r.usulan_id = {$u}.id AND r.status = 'FINAL' AND r.deleted_at IS NULL) THEN 'REKOMENDASI'
            WHEN EXISTS (SELECT 1 FROM pleno_usulan pu WHERE pu.usulan_id = {$u}.id AND pu.keputusan IS NOT NULL) THEN 'PLENO'
            WHEN EXISTS (SELECT 1 FROM penilaian pn WHERE pn.usulan_id = {$u}.id AND pn.status = 'FINAL' AND pn.deleted_at IS NULL) THEN 'DINILAI'
            WHEN EXISTS (SELECT 1 FROM penilaian pn WHERE pn.usulan_id = {$u}.id AND pn.deleted_at IS NULL) THEN 'PENILAIAN'
            ELSE 'DITERIMA' END";
    }

    public function tahap(UsulanPsn $u): string
    {
        return (string) UsulanPsn::query()->whereKey($u->id)->leftJoin('usulan_psi as ps', 'ps.usulan_id', '=', 'usulan_psn.id')
            ->selectRaw(self::sql().' AS tahap')->value('tahap');
    }

    /** Jumlah usulan per tahap untuk query dasar (setelah filter selain tahap). */
    public function rekap(Builder $dasar): array
    {
        $q = (clone $dasar)->toBase()->reorder();
        $q->columns = null; // buang kolom daftar agar sah di mode ONLY_FULL_GROUP_BY
        $hitung = $q->selectRaw(self::sql().' AS tahap, COUNT(*) AS n')->groupBy(DB::raw('tahap'))->pluck('n', 'tahap');

        return collect(self::TAHAP)->map(fn ($l, $k) => (int) ($hitung[$k] ?? 0))->all();
    }

    /** Status tiap langkah stepper: selesai | aktif | menunggu (langkah TERKIRIM hanya untuk usulan PSI). */
    public function langkah(string $tahap, bool $dariPsi): array
    {
        // Jumlah langkah yang sudah selesai untuk tiap tahap.
        $selesai = ['DITERIMA' => 1, 'PENILAIAN' => 1, 'DINILAI' => 2, 'PLENO' => 3, 'REKOMENDASI' => 4, 'TERKIRIM' => 5, 'DITARIK' => 1][$tahap] ?? 1;
        $hasil = [];
        foreach (array_keys(self::LANGKAH) as $i => $k) {
            if ($k === 'TERKIRIM' && ! $dariPsi) {
                continue;
            }
            $hasil[] = ['kode' => $k, 'judul' => self::LANGKAH[$k][0], 'uraian' => self::LANGKAH[$k][1],
                'status' => $i < $selesai ? 'selesai' : ($i === $selesai && $tahap !== 'DITARIK' ? 'aktif' : 'menunggu')];
        }

        return $hasil;
    }
}
