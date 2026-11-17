<?php

namespace App\Services;

use App\Models\EvaluasiPsn;
use App\Models\Psn;
use App\Support\Evaluasi\FormatRingkas;
use Illuminate\Validation\Rule;

/**
 * Aturan dan penyimpanan isian satu sel kertas kerja. Dipakai bersama oleh isian langsung (PATCH sel)
 * dan unggah kertas kerja Excel, sehingga validasi, izin, dan efeknya selalu sama.
 */
class KertasKerjaIsianService
{
    protected const KOLOM = ['a6' => 'a6_tahun_penetapan', 'a8' => 'a8_sumber_pendanaan', 'a12' => 'jenis_psn', 'b4' => 'b4_manfaat', 'c1' => 'c1_tipologi',
        'd1' => 'd1_kebutuhan', 'd1_alasan' => 'd1_alasan', 'd1_alasan_lain' => 'd1_alasan_lain', 'd2' => 'd2_fasilitas', 'd2_justifikasi' => 'd2_justifikasi',
        'd3_tahapan' => 'd3_tahapan', 'd3_target' => 'd3_target', 'd3_tahun' => 'd3_tahun_selesai', 'info' => 'info_tambahan',
        'hasil' => 'hasil_pengecekan', 'tl' => 'tindak_lanjut', 'catatan' => 'catatan_analisis', 'indikasi' => 'indikasi_status'];

    public function __construct(protected EvaluasiStatusService $layanan, protected FormatRingkas $format) {}

    /** Aturan validasi nilai satu sel (nilai sudah berupa kode/angka/tanggal ISO). */
    public function aturan(string $kunci): array
    {
        $c = config('psn_dashboard.evaluasi_status');
        $ed = $this->format->editor()[$kunci];

        return [
            'nilai' => match ($ed['tipe']) {
                'angka' => $ed['komponen'] ? ['nullable', 'numeric', 'min:0', 'max:1e20']
                    : (in_array($kunci, ['a6', 'd3_tahun'], true) ? ['nullable', 'integer', 'min:'.$c['tahun_min'], 'max:'.$c['tahun_maks']] : ['nullable', 'numeric']),
                'teks' => ['nullable', 'string', 'max:'.($ed['komponen'] ? 2000 : 255)],
                'panjang' => ['nullable', 'string', 'max:5000'],
                'tanggal' => ['nullable', 'date'],
                'pilihan' => ['nullable', Rule::in(array_keys($ed['opsi']))],
                'multi' => ['nullable', 'array'],
                default => ['prohibited'],
            },
            'nilai.*' => $ed['tipe'] === 'multi' ? [Rule::in(array_map('strval', array_keys($ed['opsi'])))] : [],
            'nilai_lain' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Evaluasi PSN; dibuat (form diterima hari ini) bila belum ada. */
    public function evaluasi(Psn $psn, ?EvaluasiPsn $e, string $keterangan): EvaluasiPsn
    {
        return $e ?? $this->layanan->simpan(EvaluasiPsn::firstOrNew(['tahun_rkp' => $this->layanan->tahun(), 'psn_id' => $psn->id]),
            ['form_diterima_at' => now()->toDateString()], $keterangan);
    }

    /**
     * Simpan satu sel bernilai tunggal/majemuk (bukan rincian). Mengembalikan pesan galat aturan bisnis, atau null bila tersimpan.
     *
     * @param  mixed  $nilai  kode/angka/teks/tanggal ISO, atau larik kode untuk tipe multi
     */
    public function isi(EvaluasiPsn $e, string $kunci, $nilai, ?string $nilaiLain = null, ?string $keterangan = null): ?string
    {
        $c = config('psn_dashboard.evaluasi_status');
        $ed = $this->format->editor()[$kunci];
        $nilai = is_string($nilai) ? (trim($nilai) === '' ? null : trim($nilai)) : $nilai;
        $isian = [];
        $rincian = null;

        if ($ed['komponen']) {
            if (($e->jenis_psn ?: 'FISIK') !== $ed['jenis']) {
                return 'Kolom ini untuk PSN '.$c['jenis_psn'][$ed['jenis']].'. Ubah dulu A12 Jenis PSN.';
            }
            $ada = $e->capaian()->where('komponen', $ed['komponen'])->first();
            $baris = collect(['satuan', 'target_akhir', 'target_kumulatif', 'realisasi_kumulatif', 'keterangan'])->mapWithKeys(fn ($k) => [$k => $ada?->{$k}])->all();
            $baris[$ed['kolom']] = $nilai;
            $rincian = ['capaian' => [$ed['komponen'] => $baris]];
        } else {
            if (str_starts_with($kunci, 'pic_')) {
                $pic = array_filter(array_merge($e->pic ?? [], [substr($kunci, 4) => $nilai]), fn ($x) => filled($x));
                $isian['pic'] = $pic ?: null;
            } elseif ($ed['tipe'] === 'multi') {
                $isian[self::KOLOM[$kunci]] = array_values(array_unique(array_map('strval', $nilai ?? []))) ?: null;
                if ($kunci === 'c1') {
                    $isian['c1_lainnya'] = filled($nilaiLain) ? trim($nilaiLain) : null;
                }
            } else {
                $isian[self::KOLOM[$kunci]] = $nilai;
            }
            if ($kunci === 'd1' && $nilai === 'MASIH') {
                $isian += ['d1_alasan' => null, 'd1_alasan_lain' => null];
            }
            if ($kunci === 'tl' && $nilai === null) {
                $isian['tindak_lanjut'] = $c['tindak_lanjut_otomatis'][$e->hasil_pengecekan] ?? null;
            }
            if ($kunci === 'indikasi' && $nilai && blank($e->catatan_analisis)) {
                return 'Isi Catatan Hasil Analisis sebelum menetapkan Indikasi Status.';
            }
        }
        $this->layanan->simpan($e, $isian, $keterangan, $rincian);

        return null;
    }

    /** Ganti satu kelompok rincian (milestone | isu | lampiran) dengan daftar baris baru. */
    public function isiRincian(EvaluasiPsn $e, string $kelompok, array $baris, ?string $keterangan = null): void
    {
        $this->layanan->simpan($e, [], $keterangan, [$kelompok => $baris]);
    }
}
