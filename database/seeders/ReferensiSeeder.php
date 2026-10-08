<?php

namespace Database\Seeders;

use App\Support\Referensi\ReferensiImporter;
use Illuminate\Database\Seeder;

/**
 * Data referensi hasil ekstraksi tabel `master` & `pertanyaan` basis data lama
 * (database/seeders/data/referensi.json). Kecamatan/kelurahan tidak dibundel
 * (±96 ribu baris) -- diimpor oleh `php artisan legacy:import --only=referensi`.
 */
class ReferensiSeeder extends Seeder
{
    public function run(ReferensiImporter $importer): void
    {
        $data = json_decode(file_get_contents(database_path('seeders/data/referensi.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($importer->import($data) as $tabel => $jumlah) {
            $this->command?->info(sprintf('  %-22s %6d baris', $tabel, $jumlah));
        }
    }
}
