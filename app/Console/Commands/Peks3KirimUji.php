<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Uji ujung-ke-ujung dari server dashboard: membaca satu PSN dari cermin, menandatangani, dan mengirim ke API sendiri. */
class Peks3KirimUji extends Command
{
    protected $signature = 'peks3:kirim-uji {psn_id : psn.id di PEKS3} {--url= : Alamat dashboard (bawaan APP_URL)} {--status : Hanya GET /status}';

    protected $description = 'Kirim paket uji bertanda tangan ke API integrasi/peks3/v1 (memeriksa PEKS3_RAHASIA, jam, dan rute)';

    public function handle(): int
    {
        $cfg = config('psn_dashboard.peks3');
        $url = rtrim($this->option('url') ?: config('app.url'), '/').'/api/integrasi/peks3/v1';
        $kirim = function (string $metode, string $path, string $body = '') use ($cfg, $url) {
            $t = (string) time();

            return Http::withHeaders(['X-Client-Id' => $cfg['client_id'], 'X-Timestamp' => $t,
                'X-Signature' => 'sha256='.hash_hmac('sha256', $t.'.'.$body, (string) $cfg['rahasia'])])
                ->withBody($body, 'application/json')->acceptJson()->timeout(120)->send($metode, $url.$path);
        };

        if ($this->option('status')) {
            $r = $kirim('GET', '/status');
            $this->line("HTTP {$r->status()}: ".$r->body());

            return $r->successful() ? self::SUCCESS : self::FAILURE;
        }

        $id = (int) $this->argument('psn_id');
        $db = DB::connection('legacy');
        $tabel = [];
        foreach ($cfg['tabel_psn'] as $t => $k) {
            if (\Schema::connection('legacy')->hasTable($t)) {
                $tabel[$t] = $db->table($t)->where($k, $id)->get()->map(fn ($r) => (array) $r)->all();
            }
        }
        $monev = array_column($tabel['monev_header'] ?? [], 'id');
        foreach ($cfg['tabel_monev'] as $t) {
            if (\Schema::connection('legacy')->hasTable($t)) {
                $tabel[$t] = $monev ? $db->table($t)->whereIn('monev_id', $monev)->get()->map(fn ($r) => (array) $r)->all() : [];
            }
        }
        if (count($tabel['psn'] ?? []) !== 1) {
            $this->error("PSN {$id} tidak ada di basis data cermin.");

            return self::FAILURE;
        }
        $body = json_encode(['psn_id' => $id, 'versi' => now()->toIso8601ZuluString('microsecond'), 'tabel' => $tabel], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $r = $kirim('POST', '/psn', $body);
        $this->line("HTTP {$r->status()}: ".mb_substr($r->body(), 0, 1500));

        return $r->successful() ? self::SUCCESS : self::FAILURE;
    }
}
