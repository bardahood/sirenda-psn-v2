<?php

namespace App\Console\Commands;

use App\Services\Peks3CerminService;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Peks3SiapkanCermin extends Command
{
    protected $signature = 'peks3:siapkan-cermin {--yakin : Lewati konfirmasi}';

    protected $description = 'Tandai basis data koneksi `legacy` sebagai cermin PEKS3 (wajib sebelum menerima kiriman) dan periksa tabelnya';

    public function handle(): int
    {
        $c = config('database.connections.legacy');
        $this->line("Koneksi legacy: {$c['database']} @ ".($c['unix_socket'] ?: $c['host']));
        $this->warn('Basis data ini akan DITULIS oleh kiriman PEKS3. Pastikan ini salinan (cermin) di server dashboard, BUKAN basis data asli SIRENDA PEKS3.');
        if (! $this->option('yakin') && ! $this->confirm('Lanjutkan?')) {
            return self::FAILURE;
        }

        $s = Schema::connection('legacy');
        if (! $s->hasTable(Peks3CerminService::PENANDA)) {
            $s->create(Peks3CerminService::PENANDA, function (Blueprint $t) {
                $t->id();
                $t->string('catatan', 255);
                $t->timestamp('dibuat_at')->useCurrent();
            });
            \DB::connection('legacy')->table(Peks3CerminService::PENANDA)->insert(['catatan' => 'Cermin SIRENDA PEKS3 untuk dashboard; diisi API integrasi/peks3/v1.']);
        }

        // Tabel MyISAM (mis. psn dari dump PEKS3) tidak mendukung transaksi: paket yang gagal di tengah
        // akan meninggalkan data terhapus. Konversi ke InnoDB.
        $db = \DB::connection('legacy');
        foreach ($db->select("SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND engine <> 'InnoDB'") as $r) {
            $this->line("Konversi {$r->t} ke InnoDB ...");
            $db->statement('ALTER TABLE `'.str_replace('`', '``', $r->t).'` ENGINE=InnoDB');
        }

        $cfg = config('psn_dashboard.peks3');
        $wajib = [...array_keys($cfg['tabel_psn']), ...$cfg['tabel_monev'], ...array_keys($cfg['tabel_referensi'])];
        $hilang = array_values(array_filter($wajib, fn ($t) => ! $s->hasTable($t)));
        $this->table(['Tabel', 'Status'], array_map(fn ($t) => [$t, in_array($t, $hilang, true) ? 'TIDAK ADA' : 'ok'], $wajib));
        if ($hilang) {
            $this->warn('Tabel yang tidak ada akan diabaikan saat menerima kiriman. Impor dulu dump SQL PEKS3 ke basis data cermin.');
        }
        $this->info('Basis data cermin siap.');

        return self::SUCCESS;
    }
}
