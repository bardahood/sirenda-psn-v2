<?php

namespace App\Support\Akurasi;

use App\Models\PeriodeCutoff;
use App\Services\DashboardService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Support\Facades\DB;

/**
 * Membandingkan keluaran DashboardService (K1-K4, P1-P7) dengan KueriAcuan untuk
 * sejumlah kombinasi filter. Dipakai oleh `php artisan psn:uji-akurasi` dan uji otomatis.
 */
class UjiAkurasi
{
    public function __construct(protected DashboardService $dashboard) {}

    /** Kombinasi filter bawaan: tanpa filter, tiap dimensi tunggal (nilai terbanyak), dan gabungan. */
    public function kombinasiBawaan(PeriodeCutoff $c): array
    {
        $prov = DB::table('psn_lokasi')->whereNull('deleted_at')->groupBy('provinsi_kode')->orderByRaw('COUNT(*) DESC')->value('provinsi_kode');
        $klaster = DB::table('psn')->whereNull('deleted_at')->whereNotNull('klaster_id')->groupBy('klaster_id')->orderByRaw('COUNT(*) DESC')->value('klaster_id');
        $dit = DB::table('psn_unit_pengampu as u')->join('ref_unit_kerja as r', 'r.id', '=', 'u.unit_kerja_id')->where('r.jenis', 'DIREKTORAT')
            ->groupBy('u.unit_kerja_id')->orderByRaw('COUNT(*) DESC')->value('u.unit_kerja_id');

        return array_values(array_filter([
            '',
            $prov ? "prov={$prov}" : null,
            $klaster ? "klaster={$klaster}" : null,
            $dit ? "dit={$dit}" : null,
            'kat=pkpn',
            'kat=psn',
            'dana=apbn',
            'dana=kpbu,lainnya',
            'status=terlambat,tanpa_data',
            $prov && $klaster ? "prov={$prov}&klaster={$klaster}&dana=apbn" : null,
        ], fn ($v) => $v !== null));
    }

    /**
     * @param  string[]  $kombinasi  query string filter
     * @return array<int, array{indikator: string, filter: string, aplikasi: mixed, acuan: mixed, cocok: bool}>
     */
    public function jalankan(PeriodeCutoff $c, array $kombinasi): array
    {
        $hasil = [];
        foreach ($kombinasi as $qs) {
            parse_str($qs, $q);
            $f = FilterGlobal::fromRequest(request()->duplicate($q + ['periode' => $c->kode]));
            $acuan = new KueriAcuan($c, $f);
            $label = $qs === '' ? '(tanpa filter)' : $qs;
            $catat = function (string $ind, $aplikasi, $nilaiAcuan) use (&$hasil, $label) {
                $hasil[] = ['indikator' => $ind, 'filter' => $label, 'aplikasi' => $aplikasi, 'acuan' => $nilaiAcuan, 'cocok' => $this->sama($aplikasi, $nilaiAcuan)];
            };

            $kpi = collect($this->dashboard->kpi($c, $f))->keyBy('kode');
            $catat('K1', (int) $kpi['K1']['nilai'], $acuan->k1());
            $catat('K2', $kpi['K2']['nilai'], $acuan->k2());
            $catat('K3', $kpi['K3']['nilai'], $acuan->k3());
            $catat('K4', (int) $kpi['K4']['nilai'], $acuan->k4());

            $catat('P1', collect($this->dashboard->distribusi($c, $f, 'klaster'))->pluck('jumlah', 'label')->all(), $acuan->p1());

            $pr = $this->dashboard->progres($c, $f);
            $catat('P2', ['rencana' => $pr['P2']['rencana_persen'], 'realisasi' => $pr['P2']['realisasi_persen']], $acuan->p2());
            $catat('P3', $pr['P3']['persen'], $acuan->p3());
            $catat('P4', ['tercapai' => $pr['P4']['tercapai'], 'total' => $pr['P4']['total']], $acuan->p4());

            $tren = collect($this->dashboard->tren($c, $f)['bulan'])->filter(fn ($b) => $b['cutoff'] !== null)
                ->mapWithKeys(fn ($b) => [$b['bulan'] => ['rencana' => $b['rencana_persen'], 'realisasi' => $b['realisasi_persen']]])->all();
            $catat('P5', $tren, $acuan->p5());

            $dana = collect($this->dashboard->distribusi($c, $f, 'dana'))->filter(fn ($d) => $d['jumlah'] > 0)
                ->mapWithKeys(fn ($d) => [strtoupper($d['kode']) => ['investasi' => $d['investasi_triliun'], 'jumlah' => $d['jumlah']]])->all();
            $catat('P6', $dana, $acuan->p6());

            $catat('P7', collect($this->dashboard->distribusi($c, $f, 'provinsi'))->pluck('jumlah', 'kode')->sortKeys()->all(), $acuan->p7());
        }

        return $hasil;
    }

    /** Sama persis untuk bilangan bulat; toleransi 0,05 untuk nilai berdesimal (pembulatan 1 desimal). */
    protected function sama($a, $b): bool
    {
        if (is_array($a) || is_array($b)) {
            $a = (array) $a;
            $b = (array) $b;
            ksort($a);
            ksort($b);
            if (array_keys($a) != array_keys($b)) {
                return false;
            }
            foreach ($a as $k => $v) {
                if (! $this->sama($v, $b[$k])) {
                    return false;
                }
            }

            return true;
        }
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return abs((float) $a - (float) $b) <= 0.05 + 1e-9;
    }
}
