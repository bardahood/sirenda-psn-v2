<?php

namespace Tests\Support;

use App\Support\Integrasi\SifKripto;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Server SIF tiruan untuk tes: memeriksa header (X-API-Key, X-Client-Id, X-Session-Key), membuka kunci sesi
 * dengan kunci privat, mendekripsi body amplop, lalu mengenkripsi field `data` respons -- seperti API SIF asli.
 */
trait SifTiruan
{
    protected string $sifPrivat;

    /** @var array<int, array{metode:string, path:string, body:?array}> */
    protected array $sifDiterima = [];

    protected function siapkanSif(): void
    {
        $kunci = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($kunci, $privat);
        $this->sifPrivat = $privat;
        $publik = tempnam(sys_get_temp_dir(), 'sif').'.pem';
        file_put_contents($publik, openssl_pkey_get_details($kunci)['key']);
        config(['psn_dashboard.psi.sif' => array_merge(config('psn_dashboard.psi.sif'), [
            'url' => 'https://sif.contoh.go.id/api/v1/external/usulan-psn', 'api_key' => 'kunci-api-uji', 'client_id' => 'konsultan-psn-uji', 'kunci_publik' => $publik,
        ])]);
    }

    /**
     * @param  array<string, \Closure>  $rute  "GET /kode" => fn (?array $body, array $query) -> [status, data, meta?] atau respons biner
     */
    protected function fakeSif(array $rute): void
    {
        Http::fake(['sif.contoh.go.id/*' => function (Request $r) use ($rute) {
            if ($r->header('X-API-Key')[0] ?? null) {
                if ($r->header('X-API-Key')[0] !== 'kunci-api-uji') {
                    return Http::response(['status' => 'error', 'message' => 'Invalid API key'], 401);
                }
            } else {
                return Http::response(['status' => 'error', 'message' => 'API key required'], 401);
            }
            $sesi = SifKripto::bukaKunci($r->header('X-Session-Key')[0] ?? '', $this->sifPrivat);
            $url = parse_url($r->url());
            parse_str($url['query'] ?? '', $query);
            $path = substr($url['path'], strlen('/api/v1/external/usulan-psn')) ?: '/';
            $body = null;
            if ($r->method() === 'POST') {
                if (! str_starts_with($r->header('Content-Type')[0] ?? '', 'application/vnd.sif.encrypted+json')) {
                    return Http::response(['status' => 'error', 'message' => 'Unsupported Media Type'], 415);
                }
                $body = json_decode(SifKripto::dekripsi(json_decode($r->body(), true)['data'], $sesi), true);
            }
            $this->sifDiterima[] = ['metode' => $r->method(), 'path' => $path, 'body' => $body, 'client' => $r->header('X-Client-Id')[0] ?? null];

            $f = $rute["{$r->method()} {$path}"] ?? null;
            if (! $f) {
                return Http::response(['status' => 'error', 'message' => 'Not found'], 404);
            }
            $hasil = $f($body, $query);
            if (! is_array($hasil)) {
                return $hasil; // respons biner (unduh berkas)
            }
            [$status, $data, $meta] = $hasil + [2 => null];
            if ($status >= 400) {
                return Http::response(['status' => 'error', 'message' => (string) $data], $status);
            }

            return Http::response(array_filter(['encrypted' => true, 'algorithm' => 'AES-256-GCM', 'format' => 'iv+ciphertext+tag', 'status' => 'success',
                'data' => SifKripto::enkripsi(json_encode($data), $sesi), 'meta' => $meta]), $status);
        }]);
    }
}
