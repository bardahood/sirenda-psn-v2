<?php

namespace App\Support\Referensi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengelompokan unit pengampu (ref_unit_kerja) ke kementerian/lembaga induk untuk filter global "kl".
 * Master unit belum memiliki hierarki (induk_id kosong), jadi induk diturunkan dari nama unit
 * dengan aturan di config('psn_dashboard.kementerian'). Kunci filter = slug nama induk.
 */
final class Kementerian
{
    /** @return array<string, array{label: string, unit: int[]}> slug => induk, urut nama */
    public static function daftar(): array
    {
        return Cache::remember('psn_dashboard:kementerian', 600, function () {
            $hasil = [];
            $unit = DB::table('ref_unit_kerja')->whereIn('jenis', config('psn_dashboard.kementerian.jenis_unit'))->orderBy('id')->get(['id', 'nama']);
            foreach ($unit as $u) {
                $label = self::induk($u->nama);
                $slug = Str::slug($label);
                $hasil[$slug] ??= ['label' => $label, 'unit' => []];
                $hasil[$slug]['unit'][] = (int) $u->id;
            }
            uasort($hasil, fn ($a, $b) => strcmp($a['label'], $b['label']));

            return $hasil;
        });
    }

    /** @param  string[]  $slug  @return int[] id unit anggota kementerian terpilih (kosong bila tidak dikenal) */
    public static function unit(array $slug): array
    {
        $daftar = self::daftar();
        $id = [];
        foreach ($slug as $s) {
            array_push($id, ...($daftar[$s]['unit'] ?? []));
        }

        return array_values(array_unique($id));
    }

    public static function induk(string $namaUnit): string
    {
        $nama = trim(preg_replace('/\s+/', ' ', $namaUnit));
        $aturan = config('psn_dashboard.kementerian.aturan');
        foreach ($aturan as $pola => $target) {
            if (preg_match($pola, $nama)) {
                return $target;
            }
        }

        // "Ditjen X, Kementerian Y" -> "Kementerian Y" (hanya bila bagian akhir memang nama K/L).
        $akhir = trim(Str::afterLast($nama, ','));
        if ($akhir !== $nama && preg_match('/^(Kemen|Kementeiran|Badan|BP\b)/i', $akhir)) {
            $nama = $akhir;
        }
        $nama = preg_replace(['/^Kepala\s+/i', '/^Menteri\b/i', '/^Sekretariat (Kementerian)\b/i'], ['', 'Kementerian', '$1'], $nama);
        foreach ($aturan as $pola => $target) {
            if (preg_match($pola, $nama)) {
                return $target;
            }
        }

        return $nama;
    }
}
