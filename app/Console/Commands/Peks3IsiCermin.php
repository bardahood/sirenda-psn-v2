<?php

namespace App\Console\Commands;

use App\Support\ProsesBerat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi basis data cermin PEKS3 (koneksi `legacy`) dengan menyalin seluruh tabel dari basis data lain
 * di SERVER MYSQL YANG SAMA, tanpa dump/berkas/kata sandi tambahan. Basis data sumber hanya dibaca.
 * Setelah selesai, cermin ditandai & tabel MyISAM dikonversi ke InnoDB (peks3:siapkan-cermin).
 *
 *   php artisan peks3:isi-cermin sirendapsn_bappenas
 */
class Peks3IsiCermin extends Command
{
    protected $signature = 'peks3:isi-cermin {sumber : Nama basis data sumber di server yang sama, mis. sirendapsn_bappenas} {--yakin : Lewati konfirmasi}';

    protected $description = 'Isi basis data cermin PEKS3 dengan menyalin tabel dari basis data lain di server yang sama';

    public function handle(): int
    {
        ProsesBerat::siapkan();

        $db = DB::connection('legacy');
        $tujuan = (string) $db->getDatabaseName();
        $sumber = (string) $this->argument('sumber');

        if (! preg_match('/^[A-Za-z0-9_]+$/', $sumber)) {
            $this->error('Nama basis data sumber hanya boleh huruf, angka, dan garis bawah.');

            return self::FAILURE;
        }
        if (strcasecmp($sumber, $tujuan) === 0) {
            $this->error("Sumber dan cermin sama ({$tujuan}). Isi LEGACY_DB_DATABASE di .env dengan nama basis data cermin, mis. sirendapsn_peks3, lalu jalankan php artisan config:clear.");

            return self::FAILURE;
        }
        if (strcasecmp($tujuan, (string) DB::connection()->getDatabaseName()) === 0) {
            $this->error('LEGACY_DB_DATABASE menunjuk ke basis data dashboard. Isi dengan nama basis data cermin.');

            return self::FAILURE;
        }

        $tabel = array_map(fn ($r) => $r->t, $db->select(
            "SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE' ORDER BY table_name", [$sumber]));
        if (! in_array('psn', $tabel, true)) {
            $this->error("Basis data {$sumber} tidak ditemukan, tidak dapat dibaca pengguna LEGACY_DB_USERNAME, atau tidak berisi tabel psn.");

            return self::FAILURE;
        }
        $psn = $db->selectOne("SELECT COUNT(*) AS n FROM `{$sumber}`.`psn`")->n;
        $adaIsi = $db->selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'", [$tujuan])->n;

        $this->line("Sumber : {$sumber} (".count($tabel)." tabel, {$psn} PSN), hanya dibaca");
        $this->line("Cermin : {$tujuan}".($adaIsi ? " (sudah berisi {$adaIsi} tabel; tabel bernama sama akan DIGANTI)" : ' (kosong)'));
        if (! $this->option('yakin') && ! $this->confirm('Lanjutkan menyalin?', true)) {
            return self::FAILURE;
        }

        // Data lama dapat berisi tanggal 0000-00-00 dsb.; salin apa adanya tanpa mode ketat.
        $db->statement("SET SESSION sql_mode = ''");
        $db->statement('SET FOREIGN_KEY_CHECKS = 0');
        $q = fn ($n) => '`'.str_replace('`', '``', $n).'`';
        $mulai = microtime(true);
        $bar = $this->output->createProgressBar(count($tabel));
        foreach ($tabel as $t) {
            $kolom = array_map(fn ($r) => $q($r->c), $db->select(
                "SELECT column_name AS c FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND extra NOT LIKE '%GENERATED%' ORDER BY ordinal_position", [$sumber, $t]));
            $daftar = implode(', ', $kolom);
            $db->statement("DROP TABLE IF EXISTS {$q($tujuan)}.{$q($t)}");
            $db->statement("CREATE TABLE {$q($tujuan)}.{$q($t)} LIKE {$q($sumber)}.{$q($t)}");
            $db->statement("INSERT INTO {$q($tujuan)}.{$q($t)} ({$daftar}) SELECT {$daftar} FROM {$q($sumber)}.{$q($t)}");
            $bar->advance();
        }
        $bar->finish();
        $db->statement('SET FOREIGN_KEY_CHECKS = 1');
        $this->newLine();
        $this->info(sprintf('%d tabel disalin dalam %.1f detik. PSN di cermin: %d.', count($tabel), microtime(true) - $mulai,
            $db->selectOne("SELECT COUNT(*) AS n FROM {$q($tujuan)}.`psn`")->n));

        return $this->call('peks3:siapkan-cermin', ['--yakin' => true]);
    }
}
