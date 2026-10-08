<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\KualitasDataService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KualitasDataController extends Controller
{
    public function __construct(protected DashboardService $dashboard, protected KualitasDataService $kualitas) {}

    public function index(Request $r): JsonResponse
    {
        $f = FilterGlobal::fromRequest($r);
        $c = $this->dashboard->cutoff($f);

        return response()->json([
            'data' => $c ? $this->kualitas->ringkasan($c, $f) + ['aktivitas' => $this->kualitas->aktivitas()] : null,
            'meta' => [
                'cutoff' => $c ? ['kode' => $c->kode, 'tanggal' => $c->tanggal_cutoff->toDateString()] : null,
                'sebelumnya' => $c?->sebelumnya()?->kode,
                'filter' => $f->toArray(),
                'pesan' => $c ? null : 'Belum ada cut-off yang diterbitkan.',
            ],
        ]);
    }
}
