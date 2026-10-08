<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LevelRisiko;
use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\RisikoService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class RisikoController extends Controller
{
    public function __construct(protected DashboardService $dashboard, protected RisikoService $risiko) {}

    /** GET /api/v1/risiko/ringkasan -- heatmap 5x5 harapan & aktual, pipeline regulasi, ringkasan isu. */
    public function ringkasan(Request $r): JsonResponse
    {
        $o = $this->opsi($r);

        return $this->jawab($r, fn ($c, $f) => $this->risiko->ringkasan($c, $f, $o['kategori'] ?? null));
    }

    /** GET /api/v1/risiko/register -- daftar risiko (paginasi), mendukung klik sel heatmap. */
    public function register(Request $r): JsonResponse
    {
        $o = $this->opsi($r);

        return $this->jawab($r, fn ($c, $f) => $this->risiko->register($c, $f, $o), true);
    }

    /** GET /api/v1/risiko/isu -- isu & debottlenecking terbuka (paginasi). */
    public function isu(Request $r): JsonResponse
    {
        $o = $this->opsi($r);

        return $this->jawab($r, fn ($c, $f) => $this->risiko->isu($c, $f, $o), true);
    }

    protected function opsi(Request $r): array
    {
        return $r->validate([
            'kategori' => ['nullable', 'integer'],
            'jenis' => ['nullable', 'in:harapan,aktual'],
            'kemungkinan' => ['nullable', 'integer', 'between:1,5'],
            'dampak' => ['nullable', 'integer', 'between:1,5'],
            'level' => ['nullable', Rule::in(array_column(LevelRisiko::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:100'],
            'lewat_tenggat' => ['nullable', 'boolean'],
            'termasuk_selesai' => ['nullable', 'boolean'],
        ]) + ['lewat_tenggat' => $r->boolean('lewat_tenggat'), 'termasuk_selesai' => $r->boolean('termasuk_selesai')];
    }

    protected function jawab(Request $r, \Closure $hitung, bool $berhalaman = false): JsonResponse
    {
        $f = FilterGlobal::fromRequest($r);
        $c = $this->dashboard->cutoff($f);
        $hasil = $c ? $hitung($c, $f) : null;
        $meta = ['cutoff' => $c ? ['kode' => $c->kode, 'tanggal' => $c->tanggal_cutoff->toDateString()] : null, 'filter' => $f->toArray()];

        if ($hasil instanceof LengthAwarePaginator) {
            return response()->json(['data' => $hasil->items(), 'meta' => $meta + ['halaman' => $hasil->currentPage(), 'halaman_terakhir' => $hasil->lastPage(), 'total' => $hasil->total()]]);
        }

        return response()->json(['data' => $hasil, 'meta' => $meta + ['pesan' => $c ? null : 'Belum ada cut-off yang diterbitkan.']]);
    }
}
