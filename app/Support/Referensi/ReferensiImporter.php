<?php

namespace App\Support\Referensi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengisi tabel ref_* dari kumpulan baris bergaya tabel `master` lama:
 * ['KLST' => [['kode' => 'R.1', 'nama' => '...'], ...], 'PETA' => [...], 'KRITERIA' => [...]].
 * Dipakai oleh ReferensiSeeder (berkas JSON) maupun legacy:import (tabel master langsung).
 * Idempoten: upsert berdasarkan kode.
 */
class ReferensiImporter
{
    /** Tipe yang disimpan pada tabel generik ref_kode. */
    public const TIPE_REF_KODE = ['TYIT', 'KATD', 'RISK', 'FAST', 'KTAC', 'SDGS', 'ASCI', 'PRAN', 'TACT', 'SKIF', 'KTGR', 'PAUT'];

    /** @return array<string,int> jumlah baris per tabel */
    public function import(array $data): array
    {
        $hasil = [];

        $hasil['ref_wilayah'] = $this->wilayah($data);
        $hasil['ref_klaster'] = $this->sederhana('ref_klaster', $data['KLST'] ?? [], fn ($r, $i) => ['urutan' => $i]);
        $hasil['ref_sub_klaster'] = $this->sederhana('ref_sub_klaster', $data['SKLT'] ?? []);
        $hasil['ref_klaster_pkpn'] = $this->sederhana('ref_klaster_pkpn', $data['KSPK'] ?? []);
        $hasil['ref_program'] = $this->sederhana('ref_program', $data['PROG'] ?? []);
        $hasil['ref_status_psn'] = $this->sederhana('ref_status_psn', $data['STAT'] ?? [], fn ($r, $i) => [
            'tahap' => config("psn_dashboard.tahap.{$r['kode']}"),
            'is_aktif' => config("psn_dashboard.tahap.{$r['kode']}") !== null,
            'urutan' => $i,
        ]);
        $hasil['ref_sumber_dana'] = $this->sederhana('ref_sumber_dana', $data['DANA'] ?? [], fn ($r) => [
            'skema' => config("psn_dashboard.skema_dana.{$r['kode']}"),
        ]);
        $hasil['ref_unit_kerja'] = $this->sederhana('ref_unit_kerja', $data['UNIT'] ?? [], fn ($r) => [
            'jenis' => self::jenisUnit($r['nama']),
        ]);
        $hasil['ref_instansi'] = $this->sederhana('ref_instansi', $data['INST'] ?? []);
        $hasil['ref_penanggung_jawab'] = $this->sederhana('ref_penanggung_jawab', $data['PJWB'] ?? []);

        $refKode = [];
        foreach (self::TIPE_REF_KODE as $tipe) {
            foreach (array_values($data[$tipe] ?? []) as $i => $r) {
                $refKode[] = ['tipe' => $tipe, 'kode' => $r['kode'], 'nama' => Str::limit($r['nama'], 497), 'urutan' => $i];
            }
        }
        DB::table('ref_kode')->upsert($refKode, ['tipe', 'kode'], ['nama', 'urutan']);
        $hasil['ref_kode'] = count($refKode);

        if (! empty($data['KRITERIA'])) {
            DB::table('ref_kriteria')->upsert(
                array_map(fn ($r) => $r + ['is_aktif' => true], $data['KRITERIA']),
                ['kode'],
                ['legacy_id', 'kelompok', 'uraian', 'tipe_nilai', 'kondisional', 'urutan']
            );
            $hasil['ref_kriteria'] = count($data['KRITERIA']);
        }

        return $hasil;
    }

    protected function sederhana(string $tabel, array $rows, ?callable $tambahan = null): int
    {
        $payload = [];
        foreach (array_values($rows) as $i => $r) {
            $payload[] = ['kode' => $r['kode'], 'nama' => $r['nama']] + ($tambahan ? $tambahan($r, $i) : []);
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            DB::table($tabel)->upsert($chunk, ['kode'], array_keys($chunk[0]));
        }

        return count($payload);
    }

    /** PROV/KABP (+ KCMT/KLRH bila ada) ke ref_wilayah dengan hierarki dari pola kode. */
    protected function wilayah(array $data): int
    {
        $hcKey = collect($data['PETA'] ?? [])->pluck('hc_key', 'kode');
        $koordinat = $data['KOORDINAT_PROVINSI'] ?? [];
        $jumlah = 0;

        foreach (['PROV' => 1, 'KABP' => 2, 'KCMT' => 3, 'KLRH' => 4] as $tipe => $level) {
            $rows = [];
            foreach ($data[$tipe] ?? [] as $r) {
                $kode = $r['kode'];
                $isNasional = $tipe === 'PROV' && $kode === '00';
                $rows[] = [
                    'kode' => $kode,
                    'nama' => $r['nama'],
                    'level' => $isNasional ? 0 : $level,
                    'induk_kode' => $level > 1 ? Str::beforeLast($kode, '.') : null,
                    'hc_key' => $tipe === 'PROV' ? ($hcKey[$kode] ?? null) : null,
                    'lat' => $koordinat[$kode]['lat'] ?? null,
                    'lng' => $koordinat[$kode]['lng'] ?? null,
                ];
            }
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('ref_wilayah')->upsert($chunk, ['kode'], ['nama', 'level', 'induk_kode', 'hc_key', 'lat', 'lng']);
            }
            $jumlah += count($rows);
        }

        return $jumlah;
    }

    /** Klasifikasi heuristik master UNIT yang mencampur direktorat Bappenas, K/L, dan pemda. */
    public static function jenisUnit(string $nama): string
    {
        $n = Str::lower(trim($nama));

        return match (true) {
            Str::startsWith($n, 'direktorat jenderal') => 'KL',
            Str::startsWith($n, 'direktorat ') => 'DIREKTORAT',
            Str::startsWith($n, ['gubernur', 'bupati', 'wali', 'sekretaris daerah', 'pemerintah provinsi', 'pemerintah kab', 'pemerintah kota']) => 'PEMDA',
            Str::startsWith($n, ['menteri', 'kementerian', 'kepala ', 'badan ', 'lembaga ', 'otorita', 'sekretariat', 'deputi', 'ditjen', 'jaksa', 'panglima', 'kapolri', 'kantor staf', 'bws ', 'bbws ', 'balai']) => 'KL',
            Str::startsWith($n, ['pt ', 'pt.', 'pdam', 'perum', 'perumda', 'konsorsium']) || Str::contains($n, ['persero', 'tbk']) => 'BU',
            default => 'LAINNYA',
        };
    }
}
