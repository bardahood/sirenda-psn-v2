<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Membangkitkan docs/kamus-data.md dari information_schema (komentar kolom di
 * migration menjadi sumber deskripsi). Jalankan ulang setiap kali skema berubah.
 */
class KamusData extends Command
{
    protected $signature = 'docs:kamus-data {--path=docs/kamus-data.md}';

    protected $description = 'Bangkitkan kamus data (tabel, kolom, tipe, relasi) dari skema basis data';

    protected const ABAIKAN = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    public function handle(): int
    {
        $db = DB::getDatabaseName();
        $tabel = DB::select('SELECT table_name AS n, table_type AS t, table_comment AS c FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name', [$db]);
        $fk = collect(DB::select('SELECT table_name AS t, column_name AS c, referenced_table_name AS rt, referenced_column_name AS rc
            FROM information_schema.key_column_usage WHERE table_schema = ? AND referenced_table_name IS NOT NULL', [$db]))
            ->keyBy(fn ($r) => "{$r->t}.{$r->c}");

        $md = ['# Kamus Data SIRENDA PSN v2', '', '> Dibangkitkan otomatis oleh `php artisan docs:kamus-data` dari skema basis data. Jangan diedit manual.', ''];
        foreach ($tabel as $t) {
            if (in_array($t->n, self::ABAIKAN, true)) {
                continue;
            }
            $md[] = "## `{$t->n}`".($t->t === 'VIEW' ? ' (view)' : '');
            $md[] = '';
            $md[] = '| Kolom | Tipe | Null | Relasi | Keterangan |';
            $md[] = '|---|---|---|---|---|';
            foreach (DB::select('SELECT column_name AS n, column_type AS ty, is_nullable AS nu, column_key AS k, column_comment AS c
                FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position', [$db, $t->n]) as $c) {
                $rel = ($r = $fk->get("{$t->n}.{$c->n}")) ? "→ `{$r->rt}.{$r->rc}`" : ($c->k === 'PRI' ? 'PK' : '');
                $md[] = sprintf('| `%s` | %s | %s | %s | %s |', $c->n, $c->ty, $c->nu === 'YES' ? 'ya' : '', $rel, str_replace('|', '\|', $c->c));
            }
            $md[] = '';
        }

        file_put_contents(base_path($this->option('path')), implode("\n", $md));
        $this->info('Ditulis: '.$this->option('path'));

        return self::SUCCESS;
    }
}
