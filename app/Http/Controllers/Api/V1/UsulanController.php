<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UsulanPsn;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UsulanController extends Controller
{
    /** GET /api/v1/usulan/{id}/skor[?penilaian=id] -- tanpa cache. */
    public function skor(Request $r, UsulanPsn $usulan, ScoringService $scoring): JsonResponse
    {
        Gate::authorize('view', $usulan);
        $r->validate(['penilaian' => ['nullable', 'integer']]);

        $p = $r->query('penilaian')
            ? $usulan->penilaian()->whereKey($r->query('penilaian'))->firstOrFail()
            : $usulan->penilaian()->latest('id')->first();

        if (! $p) {
            return response()->json(['data' => null, 'meta' => ['usulan_id' => $usulan->id, 'pesan' => 'Usulan belum dinilai.']]);
        }

        $h = $scoring->hitungPenilaian($p, simpan: false);

        return response()->json([
            'data' => [
                'penilaian_id' => $p->id,
                'status' => $p->status,
                'forum' => $p->forum,
                'tanggal' => $p->tanggal?->toDateString(),
                'gate' => $h['gate'],
                'komponen' => $h['komponen'],
                'nilai_akhir' => $h['nilai_akhir'],
                'rekomendasi' => ['kode' => $h['rekomendasi']->value, 'label' => $h['rekomendasi']->label()],
                'lengkap' => $h['lengkap'],
                'peringatan' => $h['peringatan'],
                'sub_kriteria' => collect($h['kriteria'])->map(fn ($k) => [
                    'kode' => $k['kode'], 'kelompok' => $k['kelompok'], 'tipe_nilai' => $k['tipe_nilai'],
                    'berlaku' => $scoring->berlaku($k, ['jenis_pengusul' => $usulan->jenis_pengusul, 'is_infrastruktur' => $usulan->is_infrastruktur]),
                    'nilai' => $h['nilai'][$k['id']] ?? null,
                ])->all(),
            ],
            'meta' => ['usulan_id' => $usulan->id, 'bobot' => config('psn_dashboard.penilaian.bobot'), 'ambang' => config('psn_dashboard.penilaian.ambang')],
        ]);
    }
}
