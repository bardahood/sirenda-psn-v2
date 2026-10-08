<?php

namespace Tests\Unit;

use App\Support\Referensi\ReferensiImporter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JenisUnitTest extends TestCase
{
    public static function kasus(): array
    {
        return [
            ['Direktorat Sumber Daya Air', 'DIREKTORAT'],
            ['Direktorat Jenderal Mineral dan Batubara, Kementerian ESDM', 'KL'],
            ['Menteri Pekerjaan Umum', 'KL'],
            ['BBWS Brantas', 'KL'],
            ['Gubernur Jawa Barat', 'PEMDA'],
            ['PT Global Papua Abadi dan Konsorsium', 'BU'],
            ['Proyek Terpadu Pengembangan Mineral Kritis', 'LAINNYA'],
        ];
    }

    #[DataProvider('kasus')]
    public function test_klasifikasi_unit(string $nama, string $jenis): void
    {
        $this->assertSame($jenis, ReferensiImporter::jenisUnit($nama));
    }
}
