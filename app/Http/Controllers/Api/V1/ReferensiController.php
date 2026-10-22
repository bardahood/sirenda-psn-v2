<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\StatusProgres;
use App\Http\Controllers\Controller;
use App\Models\PeriodeCutoff;
use App\Models\Psn;
use App\Services\DashboardService;
use App\Support\Dashboard\KamusIndikator;
use App\Support\Referensi\Kementerian;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ReferensiController extends Controller
{
    /** Opsi filter global -- seluruhnya dari tabel referensi (ref_*) dan cut-off terbit. */
    public function filterOpsi(): JsonResponse
    {
        $opsi = Cache::remember('psn_dashboard:filter-opsi', 600, fn () => [
            'provinsi' => DB::table('ref_wilayah')->where('level', 1)->orderBy('nama')->get(['kode as nilai', 'nama as label']),
            'klaster' => DB::table('ref_klaster')->where('is_aktif', true)->orderBy('urutan')->get(['id as nilai', 'nama as label']),
            'direktorat' => DB::table('ref_unit_kerja')->where('jenis', 'DIREKTORAT')->where('is_aktif', true)->orderBy('nama')->get(['id as nilai', 'nama as label']),
            'dana' => collect(DashboardService::LABEL_SKEMA)->map(fn ($l, $k) => ['nilai' => strtolower($k), 'label' => $l])->values(),
            'kategori' => [['nilai' => 'psn', 'label' => 'PSN'], ['nilai' => 'pkpn', 'label' => 'PKPN']],
            'status' => collect(StatusProgres::cases())->map(fn ($s) => ['nilai' => strtolower($s->value), 'label' => $s->label()]),
            // Kementerian/lembaga induk yang mengampu minimal satu PSN (urut nama).
            'kementerian' => (function () {
                $dipakai = DB::table('psn_unit_pengampu')->distinct()->pluck('unit_kerja_id')->map(fn ($id) => (int) $id)->all();

                return collect(Kementerian::daftar())->filter(fn ($k) => array_intersect($k['unit'], $dipakai))
                    ->map(fn ($k, $slug) => ['nilai' => $slug, 'label' => $k['label']])->values();
            })(),
            'tahun' => DB::table('psn')->whereNull('deleted_at')->whereNotNull('tahun_selesai')->distinct()->orderBy('tahun_selesai')
                ->pluck('tahun_selesai')->map(fn ($t) => ['nilai' => (string) $t, 'label' => (string) $t])->values(),
        ]);

        // Daftar PSN mengikuti cakupan akses pengguna (scope model Psn), jadi tidak ikut cache bersama.
        $opsi['psn'] = Psn::query()->orderBy('nama')->orderBy('id')->get(['id', 'nama', 'kode_psn'])
            ->map(fn ($p) => ['nilai' => $p->id, 'label' => $p->nama, 'kode' => $p->kode_psn])->values();

        $opsi['periode'] = PeriodeCutoff::where('status', 'TERBIT')->orderByDesc('tanggal_cutoff')->get(['kode', 'tanggal_cutoff'])
            ->map(fn ($p) => ['nilai' => $p->kode, 'label' => $p->tanggal_cutoff->translatedFormat('F Y')]);

        return response()->json(['data' => $opsi, 'meta' => ['cutoff' => null, 'filter' => []]]);
    }

    public function kamusIndikator(): JsonResponse
    {
        return response()->json(['data' => KamusIndikator::DAFTAR, 'meta' => ['cutoff' => null, 'filter' => []]]);
    }
}
