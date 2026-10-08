<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Psn;
use App\Services\DashboardService;
use App\Services\PortofolioService;
use App\Services\ProyekService;
use App\Support\Dashboard\FilterGlobal;
use App\Support\DashboardCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProyekController extends Controller
{
    public function __construct(protected DashboardService $dashboard, protected PortofolioService $portofolio, protected ProyekService $proyek) {}

    /** GET /api/v1/proyek -- portofolio, paginasi server; ?format=csv untuk unduh semua baris terfilter. */
    public function index(Request $r): JsonResponse|StreamedResponse
    {
        $opsi = self::opsi($r);
        $f = FilterGlobal::fromRequest($r);
        $c = $this->dashboard->cutoff($f);

        if (! $c) {
            return response()->json(['data' => [], 'meta' => ['cutoff' => null, 'filter' => $f->toArray(), 'pesan' => 'Belum ada cut-off yang diterbitkan.']]);
        }

        if ($r->query('format') === 'csv') {
            return $this->csv($c, $f, $opsi);
        }

        $perHalaman = (int) $r->query('per_halaman', 25);
        $hasil = DashboardCache::remember(
            'portofolio',
            $c->kode,
            $f->denganPeriode($c->kode)->toArray() + $opsi + ['hal' => (int) $r->query('page', 1), 'per' => $perHalaman, 'cakupan' => $r->user()->lihatTerbatas() ? 'unit:'.$r->user()->unit_kerja_id : 'semua'],
            'portofolio',
            fn () => $this->portofolio->halaman($c, $f, $opsi, $perHalaman)->toArray(),
        );

        return response()->json([
            'data' => $hasil['data'],
            'meta' => [
                'cutoff' => ['kode' => $c->kode, 'tanggal' => $c->tanggal_cutoff->toDateString()],
                'filter' => $f->toArray(),
                'opsi' => array_filter($opsi),
                'halaman' => $hasil['current_page'], 'per_halaman' => $hasil['per_page'], 'total' => $hasil['total'], 'halaman_terakhir' => $hasil['last_page'],
            ],
        ]);
    }

    /** GET /api/v1/proyek/{id} -- tanpa cache. */
    public function show(Request $r, Psn $psn): JsonResponse
    {
        Gate::authorize('view', $psn);
        $f = FilterGlobal::fromRequest($r);
        $c = $this->dashboard->cutoff($f);

        return response()->json(['data' => $this->proyek->semua($psn, $c), 'meta' => ['cutoff' => $c?->kode, 'filter' => $f->toArray()]]);
    }

    protected function csv($c, FilterGlobal $f, array $opsi): StreamedResponse
    {
        $nama = "portofolio-psn_{$c->kode}.csv";

        return response()->streamDownload(function () use ($c, $f, $opsi) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Kode', 'Nama PSN', 'Klaster', 'Tahap', 'Kategori', 'Provinsi', 'Investasi (Rp triliun)', 'Investasi anomali',
                'Rencana (%)', 'Realisasi (%)', 'Deviasi (pp)', 'Status progres', 'Risiko kritis', 'Level risiko', 'Kelengkapan (%)', 'Pembaruan terakhir'], ';');
            foreach ($this->portofolio->query($c, $f, $opsi)->cursor() as $row) {
                $b = $this->portofolio->baris($row);
                fputcsv($out, [$b['id'], $b['kode'], $b['nama'], $b['klaster'], $b['tahap'], $b['kategori'], implode(', ', $b['provinsi']),
                    $b['investasi_triliun'], $b['investasi_anomali'] ? 'ya' : '', $b['rencana_persen'], $b['realisasi_persen'], $b['deviasi_pp'],
                    $b['status_progres'], $b['is_kritis'] ? 'ya' : '', $b['risiko_level'], $b['kelengkapan_persen'], $b['pembaruan_terakhir']], ';');
            }
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Opsi tabel di luar filter global (ikut query string). */
    public static function opsi(Request $r): array
    {
        $r->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kritis' => ['nullable', 'boolean'],
            'nonaktif' => ['nullable', 'boolean'],
            'tahap' => ['nullable', Rule::in(['PERENCANAAN', 'TRANSAKSI', 'KONSTRUKSI', 'OPERASI', 'TANPA'])],
            'urut' => ['nullable', Rule::in(array_keys(PortofolioService::URUTAN))],
            'arah' => ['nullable', 'in:asc,desc'],
            'per_halaman' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return [
            'q' => $r->query('q'),
            'kritis' => $r->boolean('kritis'),
            'nonaktif' => $r->boolean('nonaktif'),
            'tahap' => $r->query('tahap'),
            'urut' => $r->query('urut', 'nama'),
            'arah' => $r->query('arah', 'asc'),
        ];
    }
}
