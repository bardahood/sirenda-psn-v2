<?php

namespace App\Services;

use App\Support\Integrasi\SifKripto;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien API SIF (Portal PSN dan KPBU, Dit. PSI) untuk PMO: daftar & detail usulan, unduh berkas,
 * dan kirim hasil penilaian. Setiap request memakai kunci sesi AES-256 baru yang dibungkus RSA-OAEP
 * (X-Session-Key); body dan field `data` respons terenkripsi AES-256-GCM. Lihat docs/integrasi-psi.md §2.
 */
class SifClient
{
    protected ?string $kunciPublik = null;

    public function aktif(): bool
    {
        $c = config('psn_dashboard.psi.sif');

        return filled($c['url']) && filled($c['api_key']) && filled($c['client_id']) && $this->bacaKunci() !== null;
    }

    /** Alasan klien belum dapat dipakai (untuk pesan ke pengguna), null bila siap. */
    public function kendala(): ?string
    {
        $c = config('psn_dashboard.psi.sif');
        $kurang = array_keys(array_filter(['SIF_BASE_URL' => $c['url'], 'SIF_API_KEY' => $c['api_key'], 'SIF_CLIENT_ID' => $c['client_id']], fn ($v) => blank($v)));
        if ($this->bacaKunci() === null) {
            $kurang[] = 'SIF_KUNCI_PUBLIK (berkas tidak terbaca: '.$c['kunci_publik'].')';
        }

        return $kurang ? 'Integrasi SIF belum dikonfigurasi: '.implode(', ', $kurang).'.' : null;
    }

    /** GET / : daftar usulan (satu halaman). */
    public function daftar(int $halaman = 1, bool $belumDinilai = false): array
    {
        return $this->panggil('GET', '', array_filter(['page' => $halaman, 'per_page' => config('psn_dashboard.psi.sif.per_halaman'), 'belum_dinilai' => $belumDinilai ? 1 : null]));
    }

    /** GET /{kode_usulan} : detail usulan + files. */
    public function detail(string $kode): array
    {
        return $this->panggil('GET', '/'.rawurlencode($kode))['data'] ?? [];
    }

    /** POST /{kode_usulan}/penilaian */
    public function kirimPenilaian(string $kode, array $body): array
    {
        return $this->panggil('POST', '/'.rawurlencode($kode).'/penilaian', [], $body);
    }

    /** Unduh berkas (PDF/lampiran) dari URL SIF; biner, tidak melalui amplop enkripsi. */
    public function unduh(string $url): Response
    {
        if (! $this->milikSif($url)) {
            throw new RuntimeException('URL berkas bukan alamat SIF.');
        }
        $r = $this->http(SifKripto::kunciSesi())->get($url);
        if ($r->failed()) {
            throw new RuntimeException("Berkas tidak dapat diunduh dari SIF (HTTP {$r->status()}).");
        }

        return $r;
    }

    public function milikSif(?string $url): bool
    {
        $dasar = rtrim((string) config('psn_dashboard.psi.sif.url'), '/');

        return $dasar !== '' && $url !== null && str_starts_with($url, $dasar.'/');
    }

    /**
     * @return array respons dengan field `data` yang sudah didekripsi
     *
     * @throws SifException
     */
    public function panggil(string $metode, string $path, array $query = [], ?array $body = null): array
    {
        if ($k = $this->kendala()) {
            throw new SifException($k, 0);
        }
        $kunciSesi = SifKripto::kunciSesi();
        $url = rtrim(config('psn_dashboard.psi.sif.url'), '/').$path;
        $http = $this->http($kunciSesi);

        $r = $body !== null
            ? $http->withBody(json_encode(SifKripto::amplop($body, $kunciSesi)), 'application/vnd.sif.encrypted+json')->send($metode, $url, ['query' => $query])
            : $http->send($metode, $url, ['query' => $query]);

        $json = $r->json() ?? [];
        if ($r->failed()) {
            throw new SifException(sprintf('SIF menolak permintaan (HTTP %d): %s', $r->status(), $json['message'] ?? substr($r->body(), 0, 300)), $r->status());
        }
        if (($json['encrypted'] ?? false) && is_string($json['data'] ?? null)) {
            $json['data'] = json_decode(SifKripto::dekripsi($json['data'], $kunciSesi), true);
        }

        return $json;
    }

    protected function http(string $kunciSesi)
    {
        $c = config('psn_dashboard.psi.sif');

        return Http::acceptJson()->timeout($c['timeout_detik'])->withHeaders([
            'X-API-Key' => $c['api_key'],
            'X-Client-Id' => $c['client_id'],
            'X-Session-Key' => SifKripto::bungkusKunci($kunciSesi, $this->bacaKunci()),
        ]);
    }

    protected function bacaKunci(): ?string
    {
        if ($this->kunciPublik !== null) {
            return $this->kunciPublik;
        }
        $path = (string) config('psn_dashboard.psi.sif.kunci_publik');
        $path = $path === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) ? $path : base_path($path);

        return $path !== '' && is_readable($path) ? $this->kunciPublik = file_get_contents($path) : null;
    }
}
