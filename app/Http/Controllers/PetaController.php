<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PetaController extends Controller
{
    public function index()
    {
        return view('peta.index', ['geojson' => file_exists(public_path(config('psn_dashboard.peta.geojson'))) ? asset(config('psn_dashboard.peta.geojson')) : null]);
    }

    /** GET /api/v1/peta -- agregat PSN per provinsi + koordinat titik tengah. */
    public function data(Request $r, DashboardService $d): JsonResponse
    {
        $f = FilterGlobal::fromRequest($r);
        $c = $d->cutoff($f);
        $per = $c ? collect($d->distribusi($c, $f, 'provinsi'))->keyBy('kode') : collect();
        $koor = DB::table('ref_wilayah')->where('level', 1)->get(['kode', 'nama', 'lat', 'lng']);

        return response()->json([
            'data' => $koor->map(fn ($p) => [
                'kode' => $p->kode, 'label' => $p->nama, 'lat' => $p->lat !== null ? (float) $p->lat : null, 'lng' => $p->lng !== null ? (float) $p->lng : null,
                'jumlah' => $per[$p->kode]['jumlah'] ?? 0,
            ])->sortByDesc('jumlah')->values(),
            'meta' => [
                'cutoff' => $c ? ['kode' => $c->kode, 'tanggal' => $c->tanggal_cutoff->toDateString()] : null,
                'filter' => $f->toArray(),
                'nasional' => $per['00']['jumlah'] ?? 0,
                'catatan' => 'PSN multi-lokasi dihitung di setiap provinsinya.',
            ],
        ]);
    }
}
