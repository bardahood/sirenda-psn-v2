<?php

namespace App\Console\Commands;

use App\Models\Psn;
use App\Models\User;
use App\Models\UsulanPsn;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Profil kinerja endpoint lewat kernel HTTP (tanpa jaringan): P50/P95 waktu respons
 * dan jumlah query per permintaan, tanpa cache (cache dikosongkan tiap permintaan)
 * dan dengan cache. Target: setiap endpoint panel < 500 ms dengan cache.
 */
class PsnProfilKinerja extends Command
{
    protected $signature = 'psn:profil-kinerja
        {--ulang=10 : Jumlah pengulangan per endpoint}
        {--pengguna= : Username yang dipakai (bawaan: Super Admin pertama)}
        {--batas=500 : Batas P95 (ms) untuk mode dengan cache}';

    protected $description = 'Ukur waktu respons (P50/P95) dan jumlah query setiap endpoint dashboard';

    public function handle(Kernel $kernel): int
    {
        $user = $this->option('pengguna')
            ? User::where('username', $this->option('pengguna'))->firstOrFail()
            : User::role('Super Admin')->where('is_active', true)->where('wajib_ganti_password', false)->firstOrFail();
        $psn = Psn::withoutGlobalScopes()->whereHas('kegiatan')->value('id') ?? Psn::withoutGlobalScopes()->value('id');
        $usulan = UsulanPsn::withoutGlobalScopes()->value('id');

        $endpoint = array_filter([
            '/api/v1/dashboard/kpi', '/api/v1/dashboard/distribusi?dim=klaster', '/api/v1/dashboard/distribusi?dim=provinsi',
            '/api/v1/dashboard/distribusi?dim=dana', '/api/v1/dashboard/distribusi?dim=direktorat', '/api/v1/dashboard/progres',
            '/api/v1/dashboard/tren', '/api/v1/dashboard/ro-kritis', '/api/v1/dashboard/tahapan', '/api/v1/dashboard/status-data',
            '/api/v1/dashboard/trisula', '/api/v1/dashboard/timeline-dp', '/api/v1/dashboard/aktivitas',
            '/api/v1/proyek', '/api/v1/proyek?urut=deviasi&prov=32', $psn ? "/api/v1/proyek/{$psn}" : null,
            '/api/v1/kualitas-data', '/api/v1/peta', $usulan ? "/api/v1/usulan/{$usulan}/skor" : null,
            '/dashboard', '/proyek', $psn ? "/proyek/{$psn}?tab=progres" : null, '/kualitas-data', '/perencanaan',
        ]);

        $n = max(1, (int) $this->option('ulang'));
        $baris = [];
        $gagal = 0;
        foreach ($endpoint as $url) {
            $dingin = $this->ukur($kernel, $user, $url, $n, true);
            $hangat = $this->ukur($kernel, $user, $url, $n, false);
            $lolos = $dingin['status'] === 200 && $hangat['p95'] < (int) $this->option('batas');
            $gagal += $lolos ? 0 : 1;
            $baris[] = [$url, $dingin['status'], $dingin['p50'], $dingin['p95'], $dingin['query'], $hangat['p50'], $hangat['p95'], $hangat['query'], $dingin['status'] !== 200 ? 'GAGAL (HTTP)' : ($lolos ? 'OK' : 'LEWAT BATAS')];
        }

        $this->table(['Endpoint', 'HTTP', 'Tanpa cache P50 (ms)', 'P95', 'Query', 'Cache P50 (ms)', 'P95', 'Query', 'Target'], $baris);
        $this->line(sprintf('Data: %d PSN, %d baris snapshot. Pengulangan: %d. Pengguna: %s.',
            DB::table('psn')->count(), DB::table('snapshot_psn')->count(), $n, $user->username));

        return $gagal ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{status: int, p50: float, p95: float, query: int} */
    protected function ukur(Kernel $kernel, User $user, string $url, int $n, bool $kosongkanCache): array
    {
        $waktu = [];
        $query = 0;
        $status = 0;
        DB::listen(function () use (&$query) {
            $query++;
        });
        for ($i = 0; $i < $n; $i++) {
            if ($kosongkanCache) {
                Cache::flush();
            }
            $query = 0;
            Auth::guard('web')->setUser($user);
            $req = Request::create($url, 'GET', server: ['HTTP_ACCEPT' => str_starts_with($url, '/api/') ? 'application/json' : 'text/html']);
            $mulai = hrtime(true);
            $res = $kernel->handle($req);
            $waktu[] = (hrtime(true) - $mulai) / 1e6;
            $status = $res->getStatusCode();
            $kernel->terminate($req, $res);
        }
        sort($waktu);
        $q = fn (float $p) => round($waktu[(int) min(count($waktu) - 1, ceil($p * count($waktu)) - 1)], 1);

        return ['status' => $status, 'p50' => $q(0.5), 'p95' => $q(0.95), 'query' => $query];
    }
}
