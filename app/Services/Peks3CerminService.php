<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Menerima kiriman SIRENDA PEKS3 ke basis data cermin (koneksi `legacy`), yang strukturnya sama dengan
 * basis data PEKS3. Penerapan ke tabel dashboard dilakukan terpisah oleh Peks3TerapkanService.
 * Kontrak: docs/integrasi-peks3.md.
 */
class Peks3CerminService
{
    /** Tabel penanda di basis data cermin; tanpa tabel ini kiriman ditolak agar tidak menulis ke basis data PEKS3 asli. */
    public const PENANDA = 'peks3_cermin';

    /** @var array<string, string[]|null> */
    protected array $kolom = [];

    protected array $peringatan = [];

    protected ?bool $siap = null;

    public function cermin(): Connection
    {
        $db = DB::connection('legacy');
        if (! Schema::connection('legacy')->hasTable(self::PENANDA)) {
            throw new RuntimeException('Basis data cermin belum disiapkan. Jalankan: php artisan peks3:siapkan-cermin');
        }
        if ($this->siap === null) {
            $myisam = $db->selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('psn', 'psn_kegiatan') AND engine <> 'InnoDB'")->n;
            $this->siap = ! $myisam ?: throw new RuntimeException('Tabel cermin masih MyISAM (tanpa transaksi). Jalankan ulang: php artisan peks3:siapkan-cermin');
        }

        return $db;
    }

    /**
     * Satu paket PSN: mengganti seluruh baris PSN tersebut di setiap tabel yang dikirim.
     *
     * @return array{psn_id:int, hasil:string, baris?:array<string,int>, peringatan?:string[]}
     */
    public function terimaPaket(array $p, ?string $ip = null): array
    {
        $this->peringatan = [];
        $cfg = config('psn_dashboard.peks3');
        $id = filter_var($p['psn_id'] ?? null, FILTER_VALIDATE_INT);
        if (! $id || $id < 1) {
            throw new InvalidArgumentException('psn_id wajib berupa bilangan bulat positif.');
        }
        $versi = $this->versi($p['versi'] ?? null);
        $dihapus = (bool) ($p['dihapus'] ?? false);
        $tabel = $dihapus ? [] : ($p['tabel'] ?? null);
        if (! $dihapus && (! is_array($tabel) || count($tabel['psn'] ?? []) !== 1)) {
            throw new InvalidArgumentException("PSN {$id}: tabel.psn wajib berisi tepat satu baris (atau kirim \"dihapus\": true).");
        }

        $lama = DB::table('integrasi_peks3_psn')->where('legacy_psn_id', $id)->first();
        if ($lama && $versi < (int) $lama->versi) {
            $this->log('PSN', $id, 'VERSI_LAMA', "Versi {$versi} lebih lama dari versi tersimpan {$lama->versi}; diabaikan.", null, $ip);

            return ['psn_id' => $id, 'hasil' => 'VERSI_LAMA'];
        }
        $sidik = hash('sha256', json_encode(['dihapus' => $dihapus, 'tabel' => $this->urutkan($tabel ?? [])], JSON_UNESCAPED_UNICODE));
        if ($lama && hash_equals($lama->sidik, $sidik)) {
            DB::table('integrasi_peks3_psn')->where('legacy_psn_id', $id)->update(['versi' => $versi, 'diterima_at' => now()]);
            $this->log('PSN', $id, 'TETAP', 'Isi paket sama dengan kiriman sebelumnya.', null, $ip);

            return ['psn_id' => $id, 'hasil' => 'TETAP'];
        }

        $tabelPsn = $cfg['tabel_psn'];
        $tabelMonev = $cfg['tabel_monev'];
        foreach (array_diff(array_keys($tabel ?? []), array_keys($tabelPsn), $tabelMonev) as $t) {
            $this->peringatan[] = "Tabel {$t} tidak dikenal, diabaikan.";
        }

        // Validasi kepemilikan baris sebelum menulis apa pun.
        $baris = [];
        foreach ($tabelPsn as $t => $kunci) {
            if ($dihapus || array_key_exists($t, $tabel)) {
                $baris[$t] = $this->daftarBaris($tabel[$t] ?? [], $t);
                foreach ($baris[$t] as $i => $r) {
                    if ((int) ($r[$kunci] ?? 0) !== $id) {
                        throw new InvalidArgumentException("PSN {$id}: {$t}[{$i}].{$kunci} harus bernilai {$id}.");
                    }
                }
            }
        }
        $monevBaru = array_map(fn ($r) => (int) ($r['id'] ?? 0), $baris['monev_header'] ?? []);
        foreach ($tabelMonev as $t) {
            if (! $dihapus && array_key_exists($t, $tabel)) {
                $baris[$t] = $this->daftarBaris($tabel[$t], $t);
                foreach ($baris[$t] as $i => $r) {
                    if (! in_array((int) ($r['monev_id'] ?? 0), $monevBaru, true)) {
                        throw new InvalidArgumentException("PSN {$id}: {$t}[{$i}].monev_id harus merujuk monev_header milik PSN ini dalam paket yang sama.");
                    }
                }
            }
        }

        $db = $this->cermin();
        $jumlah = [];
        $db->transaction(function () use ($db, $id, $baris, $tabelPsn, $tabelMonev, $dihapus, &$jumlah) {
            $monevLama = $this->ada('monev_header') ? $db->table('monev_header')->where('psn_id', $id)->pluck('id')->map(fn ($v) => (int) $v)->all() : [];
            $monevSemua = array_values(array_unique([...$monevLama, ...array_map(fn ($r) => (int) ($r['id'] ?? 0), $baris['monev_header'] ?? [])]));
            foreach ($tabelMonev as $t) {
                // Anak monev diganti bila dikirim; anak dari monev_header yang hilang ikut dihapus.
                $hapusUntuk = $dihapus || array_key_exists($t, $baris) ? $monevSemua
                    : (array_key_exists('monev_header', $baris) ? array_diff($monevLama, array_map(fn ($r) => (int) $r['id'], $baris['monev_header'])) : []);
                if ($hapusUntuk && $this->ada($t)) {
                    $db->table($t)->whereIn('monev_id', $hapusUntuk)->delete();
                }
            }
            foreach ($tabelPsn as $t => $kunci) {
                if (! array_key_exists($t, $baris)) {
                    continue;
                }
                if (! $this->ada($t)) {
                    $baris[$t] && $this->peringatan[] = "Tabel {$t} tidak ada di basis data cermin; ".count($baris[$t]).' baris diabaikan.';

                    continue;
                }
                $db->table($t)->where($kunci, $id)->delete();
                $jumlah[$t] = $this->sisipkan($t, $baris[$t]);
            }
            foreach ($tabelMonev as $t) {
                if (array_key_exists($t, $baris) && $this->ada($t)) {
                    $jumlah[$t] = $this->sisipkan($t, $baris[$t]);
                }
            }
        });

        DB::table('integrasi_peks3_psn')->updateOrInsert(['legacy_psn_id' => $id],
            ['versi' => $versi, 'sidik' => $sidik, 'jumlah_baris' => array_sum($jumlah), 'dihapus' => $dihapus, 'diterima_at' => now()]);
        $this->tandaiTertunda();
        $this->log('PSN', $id, 'DITERIMA', $dihapus ? 'PSN dihapus dari cermin.' : null, ['baris' => $jumlah, 'peringatan' => $this->peringatan], $ip);

        return array_filter(['psn_id' => $id, 'hasil' => 'DITERIMA', 'baris' => $jumlah, 'peringatan' => array_values(array_unique($this->peringatan))]);
    }

    /** Upsert baris tabel referensi/global (master, users, psn_2027, peta). */
    public function terimaReferensi(string $tabel, array $baris, ?string $ip = null): array
    {
        $this->peringatan = [];
        $kunci = config("psn_dashboard.peks3.tabel_referensi.{$tabel}") ?? throw new InvalidArgumentException("Tabel referensi {$tabel} tidak dikenal.");
        if (count($baris) > config('psn_dashboard.peks3.maks_baris_referensi')) {
            throw new InvalidArgumentException('Terlalu banyak baris; kirim bertahap maks. '.config('psn_dashboard.peks3.maks_baris_referensi').' baris per permintaan.');
        }
        $db = $this->cermin();
        if (! $this->ada($tabel)) {
            throw new InvalidArgumentException("Tabel {$tabel} tidak ada di basis data cermin.");
        }
        $baris = $this->daftarBaris($baris, $tabel);
        foreach ($baris as $i => $r) {
            foreach ($kunci as $k) {
                if (blank($r[$k] ?? null)) {
                    throw new InvalidArgumentException("{$tabel}[{$i}].{$k} wajib diisi.");
                }
            }
        }
        // Akun baru di cermin: kolom kata sandi wajib terisi tetapi tidak pernah dikirim.
        $wajib = $tabel === 'users' && in_array('password', $this->kolom('users'), true) ? ['password' => ''] : [];

        $n = 0;
        $db->transaction(function () use ($db, $tabel, $kunci, $baris, $wajib, &$n) {
            // Upsert per kelompok kolom yang sama agar kolom yang tidak dikirim tidak tertimpa null.
            foreach (collect($baris)->groupBy(fn ($r) => implode(',', array_keys($r))) as $kelompok) {
                $kelompok = $kelompok->all();
                $ubah = array_values(array_diff(array_keys($kelompok[0]), $kunci));
                foreach (array_chunk($kelompok, 500) as $c) {
                    $db->table($tabel)->upsert(array_map(fn ($r) => $r + $wajib, $c), $kunci, $ubah ?: $kunci);
                }
                $n += count($kelompok);
            }
        });

        $this->tandaiTertunda();
        $this->log('REFERENSI', null, 'DITERIMA', "{$tabel}: {$n} baris", ['tabel' => $tabel, 'baris' => $n, 'peringatan' => $this->peringatan], $ip);

        return array_filter(['tabel' => $tabel, 'baris' => $n, 'peringatan' => array_values(array_unique($this->peringatan))]);
    }

    /** Hapus dari cermin PSN yang tidak ada lagi di PEKS3 (daftar lengkap psn.id yang masih ada). */
    public function rekonsiliasi(array $idAda, ?string $ip = null): array
    {
        $idAda = array_values(array_unique(array_map('intval', $idAda)));
        $cermin = $this->cermin()->table('psn')->pluck('id')->map(fn ($v) => (int) $v)->all();
        $hapus = array_values(array_diff($cermin, $idAda));
        $maks = (float) config('psn_dashboard.peks3.maks_hapus_rekonsiliasi');
        if ($cermin && count($hapus) > $maks * count($cermin)) {
            throw new InvalidArgumentException(sprintf('Rekonsiliasi akan menghapus %d dari %d PSN (> %d%%). Kemungkinan daftar ID tidak lengkap; ditolak.',
                count($hapus), count($cermin), $maks * 100));
        }
        foreach ($hapus as $id) {
            // Penghapusan rekonsiliasi selalu menang atas versi tersimpan.
            $versi = max((int) now()->format('Uu'), (int) DB::table('integrasi_peks3_psn')->where('legacy_psn_id', $id)->value('versi') + 1);
            $this->terimaPaket(['psn_id' => $id, 'versi' => (string) $versi, 'dihapus' => true], $ip);
        }
        $this->log('REKONSILIASI', null, 'DITERIMA', count($hapus).' PSN dihapus dari cermin.', ['dihapus' => $hapus, 'dikirim' => count($idAda)], $ip);

        return ['psn_di_cermin' => count($cermin), 'dihapus' => $hapus];
    }

    public function status(): array
    {
        $s = DB::table('integrasi_peks3_status')->find(1);
        $cermin = null;
        try {
            $cermin = $this->cermin()->table('psn')->count();
        } catch (Throwable) {
        }

        return [
            'tertunda' => (bool) $s?->tertunda_sejak,
            'tertunda_sejak' => $s?->tertunda_sejak,
            'perubahan_terakhir_at' => $s?->perubahan_terakhir_at,
            'diterapkan_at' => $s?->diterapkan_at,
            'durasi_detik' => $s?->durasi_detik !== null ? (float) $s->durasi_detik : null,
            'galat_terakhir' => $s?->galat,
            'psn_di_cermin' => $cermin,
            'psn_di_dashboard' => DB::table('psn')->whereNotNull('legacy_id')->whereNull('deleted_at')->count(),
        ];
    }

    public function tandaiTertunda(): void
    {
        $kini = now();
        $ada = DB::table('integrasi_peks3_status')->where('id', 1)->exists();
        $ada
            ? DB::table('integrasi_peks3_status')->where('id', 1)->update(['tertunda_sejak' => DB::raw('COALESCE(tertunda_sejak, '.DB::getPdo()->quote($kini->toDateTimeString()).')'),
                'perubahan_terakhir_at' => $kini, 'updated_at' => $kini])
            : DB::table('integrasi_peks3_status')->insert(['id' => 1, 'tertunda_sejak' => $kini, 'perubahan_terakhir_at' => $kini, 'updated_at' => $kini]);
    }

    public function log(string $jenis, ?int $psnId, string $hasil, ?string $pesan = null, ?array $ringkasan = null, ?string $ip = null): void
    {
        DB::table('integrasi_peks3_log')->insert(['jenis' => $jenis, 'legacy_psn_id' => $psnId, 'hasil' => $hasil, 'pesan' => $pesan,
            'ringkasan' => $ringkasan ? json_encode($ringkasan, JSON_UNESCAPED_UNICODE) : null, 'ip_address' => $ip, 'created_at' => now()]);
    }

    // ------------------------------------------------------------------ bantu

    /** Versi paket -> mikrodetik epoch. Menerima bilangan (detik/milidetik/mikrodetik) atau tanggal ISO 8601. */
    public function versi($v): int
    {
        if (is_int($v) || (is_string($v) && ctype_digit($v))) {
            $n = (int) $v;

            return match (true) {
                $n >= 1e15 => $n,
                $n >= 1e12 => $n * 1000,
                default => $n * 1_000_000,
            };
        }
        if (is_string($v) && $v !== '') {
            try {
                return (int) CarbonImmutable::parse($v)->format('Uu');
            } catch (Throwable) {
            }
        }
        throw new InvalidArgumentException('versi wajib diisi (stempel waktu ISO 8601 atau bilangan epoch).');
    }

    /** @return array<int, array<string,mixed>> baris yang sudah disaring ke kolom cermin */
    protected function daftarBaris($v, string $tabel): array
    {
        if (! is_array($v) || ! array_is_list($v)) {
            throw new InvalidArgumentException("{$tabel} harus berupa daftar baris (array).");
        }
        $kolom = $this->ada($tabel) ? array_flip($this->kolom($tabel)) : null;
        $buang = array_flip(config("psn_dashboard.peks3.kolom_dibuang.{$tabel}", []));

        return array_map(function ($r, $i) use ($tabel, &$kolom, $buang) {
            if (! is_array($r) || array_is_list($r)) {
                throw new InvalidArgumentException("{$tabel}[{$i}] harus berupa objek kolom => nilai.");
            }
            $hasil = [];
            foreach ($r as $k => $nilai) {
                if (isset($buang[$k])) {
                    continue;
                }
                if ($kolom !== null && ! isset($kolom[$k])) {
                    if (! $this->tambahKolom($tabel, (string) $k)) {
                        $this->peringatan[] = "Kolom {$tabel}.{$k} tidak ada di cermin, diabaikan.";

                        continue;
                    }
                    $kolom[$k] = true;
                }
                $hasil[$k] = is_array($nilai) ? json_encode($nilai, JSON_UNESCAPED_UNICODE) : (is_bool($nilai) ? (int) $nilai : $nilai);
            }

            return $hasil;
        }, $v, array_keys($v));
    }

    /**
     * Kolom baru di SIRENDA PEKS3 ikut ditambahkan ke tabel cermin (TEXT NULL, menampung nilai apa pun), sehingga
     * data tidak hilang dan siap dipetakan ke tabel dashboard lewat migrasi + LegacyImporter. Dijalankan sebelum
     * transaksi penulisan (DDL MySQL melakukan commit implisit). Hanya nama kolom yang aman yang diterima.
     */
    protected function tambahKolom(string $tabel, string $kolom): bool
    {
        if (! config('psn_dashboard.peks3.tambah_kolom_otomatis') || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $kolom)) {
            return false;
        }
        Schema::connection('legacy')->table($tabel, fn (Blueprint $t) => $t->text($kolom)->nullable()->comment('ditambahkan otomatis dari kiriman SIRENDA PEKS3'));
        unset($this->kolom[$tabel]);
        $this->peringatan[] = "Kolom baru {$tabel}.{$kolom} ditambahkan otomatis ke cermin; tampil di dashboard setelah dipetakan.";
        $this->log('KOLOM', null, 'DITERIMA', "Kolom baru {$tabel}.{$kolom} ditambahkan ke basis data cermin.");

        return true;
    }

    protected function sisipkan(string $tabel, array $baris): int
    {
        // insert() massal mensyaratkan kolom seragam: kelompokkan per himpunan kolom.
        foreach (collect($baris)->groupBy(fn ($r) => implode(',', array_keys($r))) as $kelompok) {
            foreach ($kelompok->chunk(200) as $c) {
                DB::connection('legacy')->table($tabel)->insert($c->values()->all());
            }
        }

        return count($baris);
    }

    protected function ada(string $tabel): bool
    {
        return $this->kolom($tabel) !== [];
    }

    /** @return string[] */
    protected function kolom(string $tabel): array
    {
        return $this->kolom[$tabel] ??= Schema::connection('legacy')->getColumnListing($tabel);
    }

    protected function urutkan($v)
    {
        if (! is_array($v)) {
            return $v;
        }
        if (! array_is_list($v)) {
            ksort($v);
        }

        return array_map(fn ($x) => $this->urutkan($x), $v);
    }
}
