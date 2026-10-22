<?php

namespace App\Http\Controllers\Api\Integrasi;

use App\Http\Controllers\Controller;
use App\Models\UsulanPsi;
use App\Services\PsiHasilService;
use App\Services\PsiImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API masuk untuk aplikasi PSI (tanpa sesi; token Bearer). Kontrak lengkap: docs/integrasi-psi.md.
 */
class PsiController extends Controller
{
    public function __construct(protected PsiImportService $impor, protected PsiHasilService $hasil) {}

    /** POST /api/integrasi/psi/v1/usulan -- kirim/perbarui satu usulan (idempoten per psi_id + versi). */
    public function terima(Request $r): JsonResponse
    {
        $h = $this->impor->terima($r->json()->all(), $r->ip());

        return response()->json([
            'data' => [
                'psi_id' => $r->json('psi_id'),
                'sirenda_id' => $h['usulan']->id,
                'status' => $h['diabaikan'] ? 'DIABAIKAN_VERSI_LAMA' : ($h['baru'] ? 'DITERIMA' : 'DIPERBARUI'),
                'peringatan' => $h['peringatan'],
                'url_hasil' => route('integrasi.psi.hasil', $r->json('psi_id')),
            ],
        ], $h['baru'] ? 201 : 200);
    }

    /** POST /api/integrasi/psi/v1/usulan/{psi_id}/tarik -- pengusul menarik usulan. */
    public function tarik(Request $r, string $psiId): JsonResponse
    {
        $data = $r->validate(['alasan' => ['nullable', 'string', 'max:2000']]);
        $psi = $this->impor->tarik($psiId, $data['alasan'] ?? null, $r->ip());

        return response()->json(['data' => ['psi_id' => $psiId, 'sirenda_id' => $psi->usulan_id, 'status' => 'DITARIK']]);
    }

    /** GET /api/integrasi/psi/v1/usulan/{psi_id}/hasil -- PSI mengambil hasil penilaian, pleno, rekomendasi. */
    public function ambilHasil(Request $r, string $psiId): JsonResponse
    {
        $psi = UsulanPsi::where('psi_id', $psiId)->firstOrFail();
        $payload = $this->hasil->payload($psi->usulan()->withoutGlobalScopes()->firstOrFail());
        $this->impor->log('AMBIL_HASIL', $psiId, $psi->usulan_id, 200, true, 'Hasil diambil PSI (tahap '.$payload['tahap'].').', null, $r->ip());

        return response()->json(['data' => $payload]);
    }

    /** GET /api/integrasi/psi/v1/referensi -- kode referensi agar PSI dapat memetakan isian. */
    public function referensi(): JsonResponse
    {
        return response()->json(['data' => [
            'klaster' => DB::table('ref_klaster')->where('is_aktif', true)->orderBy('urutan')->get(['kode', 'nama']),
            'provinsi' => DB::table('ref_wilayah')->where('level', 1)->orderBy('kode')->get(['kode', 'nama']),
            'jenis_instansi' => array_keys(config('psn_dashboard.psi.jenis_instansi')),
            'jenis_dokumen' => PsiImportService::JENIS_DOKUMEN,
            'keputusan_pleno' => config('psn_dashboard.psi.keputusan_pleno'),
        ]]);
    }
}
