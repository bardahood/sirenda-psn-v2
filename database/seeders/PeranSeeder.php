<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran & izin sesuai matriks RBAC (docs/rancangan-aplikasi.md bagian Hak Akses).
 * Format izin: {modul}.{aksi}; aksi: lihat | input | verifikasi | kelola.
 * Pembatasan "¹ terbatas pada sektor/proyek sendiri" ditegakkan oleh global
 * scope berbasis users.unit_kerja_id, bukan oleh nama izin.
 */
class PeranSeeder extends Seeder
{
    public const MODUL = ['ringkasan', 'portofolio', 'detail', 'perencanaan', 'risiko', 'kualitas', 'laporan', 'pengaturan'];

    public const MATRIKS = [
        'Super Admin' => ['*' => 'kelola'],
        'Pimpinan' => ['ringkasan' => 'lihat', 'portofolio' => 'lihat', 'detail' => 'lihat', 'perencanaan' => 'lihat', 'risiko' => 'lihat', 'kualitas' => 'lihat', 'laporan' => 'lihat'],
        'Tim Koordinasi/PMO' => ['ringkasan' => 'lihat', 'portofolio' => 'lihat', 'detail' => 'lihat', 'perencanaan' => 'kelola', 'risiko' => 'lihat', 'kualitas' => 'lihat', 'laporan' => 'kelola'],
        'Direktorat Sektor' => ['ringkasan' => 'lihat', 'portofolio' => 'lihat', 'detail' => 'verifikasi', 'perencanaan' => 'verifikasi', 'risiko' => 'input', 'kualitas' => 'lihat', 'laporan' => 'lihat'],
        'Operator K/L' => ['ringkasan' => 'lihat', 'portofolio' => 'lihat', 'detail' => 'input', 'perencanaan' => 'input', 'risiko' => 'input', 'kualitas' => 'lihat'],
    ];

    /** Aksi bersifat kumulatif: kelola mencakup verifikasi, input, dan lihat. */
    public const TINGKAT = ['lihat', 'input', 'verifikasi', 'kelola'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::MODUL as $modul) {
            foreach (self::TINGKAT as $aksi) {
                Permission::findOrCreate("{$modul}.{$aksi}");
            }
        }

        foreach (self::MATRIKS as $peran => $hak) {
            $izin = [];
            foreach (self::MODUL as $modul) {
                $tingkat = $hak[$modul] ?? $hak['*'] ?? null;
                if ($tingkat === null) {
                    continue;
                }
                foreach (array_slice(self::TINGKAT, 0, array_search($tingkat, self::TINGKAT) + 1) as $aksi) {
                    $izin[] = "{$modul}.{$aksi}";
                }
            }
            Role::findOrCreate($peran)->syncPermissions($izin);
        }
    }
}
