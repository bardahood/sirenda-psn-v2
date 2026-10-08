<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PeriodeCutoff;
use App\Services\DashboardService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endpoint panel Ringkasan Eksekutif. Semua menerima filter global yang sama dan
 * mengembalikan { data, meta: { cutoff, sebelumnya, filter } }.
 */
class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboard) {}

    public function kpi(Request $r): JsonResponse
    {
        return $this->jawab($r, fn ($c, $f) => $this->dashboard->kpi($c, $f));
    }

    public function distribusi(Request $r): JsonResponse
    {
        $r->validate(['dim' => ['required', Rule::in(['klaster', 'direktorat', 'provinsi', 'dana'])]], ['dim.*' => 'Parameter dim harus klaster, direktorat, provinsi, atau dana.']);

        return $this->jawab($r, fn ($c, $f) => $this->dashboard->distribusi($c, $f, $r->query('dim')));
    }

    public function progres(Request $r): JsonResponse
    {
        return $this->jawab($r, fn ($c, $f) => $this->dashboard->progres($c, $f));
    }

    public function tren(Request $r): JsonResponse
    {
        return $this->jawab($r, fn ($c, $f) => $this->dashboard->tren($c, $f));
    }

    public function roKritis(Request $r): JsonResponse
    {
        $r->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return $this->jawab($r, fn ($c, $f) => $this->dashboard->roKritis($c, $f, (int) $r->query('limit', 10)));
    }

    public function tahapan(Request $r): JsonResponse
    {
        return $this->jawab($r, fn ($c, $f) => $this->dashboard->tahapan($c, $f));
    }

    public function statusData(Request $r): JsonResponse
    {
        return $this->jawab($r, fn ($c, $f) => $this->dashboard->statusData($c, $f));
    }

    public function trisula(Request $r): JsonResponse
    {
        return $this->jawab($r, fn ($c, $f) => $this->dashboard->trisula($c, $f));
    }

    public function timelineDp(Request $r): JsonResponse
    {
        return $this->jawab($r, fn ($c, $f) => $this->dashboard->timelineDirektifPresiden($c, $f));
    }

    public function aktivitas(Request $r): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->aktivitas(), 'meta' => ['cutoff' => null, 'filter' => []]]);
    }

    protected function jawab(Request $r, \Closure $hitung): JsonResponse
    {
        $f = FilterGlobal::fromRequest($r);
        $c = $this->dashboard->cutoff($f);

        return response()->json([
            'data' => $c ? $hitung($c, $f) : null,
            'meta' => [
                'cutoff' => $c ? $this->cutoffMeta($c) : null,
                'sebelumnya' => $c?->sebelumnya()?->kode,
                'filter' => $f->toArray(),
                'pesan' => $c ? null : 'Belum ada cut-off yang diterbitkan.',
            ],
        ]);
    }

    protected function cutoffMeta(PeriodeCutoff $c): array
    {
        return ['kode' => $c->kode, 'tanggal' => $c->tanggal_cutoff->toDateString(), 'diterbitkan_at' => $c->diterbitkan_at?->toIso8601String()];
    }
}
