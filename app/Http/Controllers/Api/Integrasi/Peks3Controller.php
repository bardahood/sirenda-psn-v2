<?php

namespace App\Http\Controllers\Api\Integrasi;

use App\Http\Controllers\Controller;
use App\Services\Peks3CerminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

/** API masuk dari SIRENDA PEKS3 (docs/integrasi-peks3.md §4). */
class Peks3Controller extends Controller
{
    public function __construct(protected Peks3CerminService $cermin) {}

    /** POST /psn : satu paket, atau {"paket": [...]} maks. config peks3.maks_paket. */
    public function psn(Request $r): JsonResponse
    {
        $body = $this->body($r);
        $paket = array_key_exists('paket', $body) ? $body['paket'] : [$body];
        if (! is_array($paket) || ! array_is_list($paket) || $paket === []) {
            return $this->galat('Body harus berupa paket PSN atau {"paket": [ ... ]}.');
        }
        if (count($paket) > config('psn_dashboard.peks3.maks_paket')) {
            return $this->galat('Maksimal '.config('psn_dashboard.peks3.maks_paket').' paket per permintaan.');
        }

        $hasil = [];
        $gagal = 0;
        foreach ($paket as $i => $p) {
            try {
                $hasil[] = $this->cermin->terimaPaket(is_array($p) ? $p : [], $r->ip());
            } catch (InvalidArgumentException $e) {
                $gagal++;
                $hasil[] = ['psn_id' => $p['psn_id'] ?? null, 'hasil' => 'DITOLAK', 'pesan' => $e->getMessage()];
                $this->cermin->log('PSN', is_numeric($p['psn_id'] ?? null) ? (int) $p['psn_id'] : null, 'GAGAL', "paket[{$i}]: ".$e->getMessage(), null, $r->ip());
            } catch (RuntimeException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
            }
        }

        return response()->json(['status' => $gagal ? ($gagal === count($paket) ? 'error' : 'sebagian') : 'success',
            'message' => sprintf('%d paket diproses, %d ditolak. Perubahan tampil di dashboard dalam beberapa menit.', count($paket), $gagal),
            'data' => $hasil], $gagal === count($paket) ? 422 : 202);
    }

    /** POST /referensi : {"tabel": "master|users|psn_2027|peta", "baris": [...]} */
    public function referensi(Request $r): JsonResponse
    {
        try {
            $data = $this->cermin->terimaReferensi((string) ($this->body($r)['tabel'] ?? ''), (array) ($this->body($r)['baris'] ?? []), $r->ip());
        } catch (InvalidArgumentException $e) {
            return $this->galat($e->getMessage());
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
        }

        return response()->json(['status' => 'success', 'data' => $data], 202);
    }

    /** POST /rekonsiliasi : {"psn_id": [semua psn.id yang masih ada di PEKS3]} */
    public function rekonsiliasi(Request $r): JsonResponse
    {
        $id = $this->body($r)['psn_id'] ?? null;
        if (! is_array($id) || $id === [] || array_filter($id, fn ($v) => ! is_int($v) && ! ctype_digit((string) $v))) {
            return $this->galat('psn_id harus berupa daftar bilangan bulat yang tidak kosong.');
        }
        try {
            $data = $this->cermin->rekonsiliasi($id, $r->ip());
        } catch (InvalidArgumentException $e) {
            return $this->galat($e->getMessage());
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 503);
        }

        return response()->json(['status' => 'success', 'data' => $data], 202);
    }

    /** GET /status */
    public function status(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->cermin->status()]);
    }

    /**
     * Body JSON mentah: tidak melalui middleware TrimStrings/ConvertEmptyStringsToNull, karena string kosong
     * dan spasi adalah isi data PEKS3 yang sah (kolom NOT NULL berisi '').
     */
    protected function body(Request $r): array
    {
        $j = json_decode($r->getContent(), true);

        return is_array($j) ? $j : [];
    }

    protected function galat(string $pesan): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $pesan], 422);
    }
}
