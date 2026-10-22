<?php

namespace Tests\Unit;

use App\Support\Referensi\Kementerian;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KementerianTest extends TestCase
{
    public static function kasus(): array
    {
        return [
            ['Menteri Pekerjaan Umum', 'Kementerian Pekerjaan Umum'],
            ['Direktorat Jenderal Bina Marga, Kementerian Pekerjaan Umum', 'Kementerian Pekerjaan Umum'],
            ['BBWS Brantas', 'Kementerian Pekerjaan Umum'],
            ['BWS Sulawesi I', 'Kementerian Pekerjaan Umum'],
            ['Menteri Pendidikan Tinggi, Sains, dan Teknologi', 'Kementerian Pendidikan Tinggi, Sains, dan Teknologi'],
            ['Deputi Fasilitasi dan Sinergi Pembangunan, BP BUMN', 'Badan Pengaturan BUMN'],
            ['Kepala Badan Pengaturan BUMN', 'Badan Pengaturan BUMN'],
            ['Deputi Bidang Hilirasi Investasi dan Strategis, Kementeiran Investasi dan Hilirisasi/Kepala BKPM', 'Kementerian Investasi dan Hilirisasi/BKPM'],
            ['Menteri Investasi dan Hilirisasi/Kepala BKPM', 'Kementerian Investasi dan Hilirisasi/BKPM'],
            ['Menteri Koordinator Bidang Perekonomian /Ketua Dewan Nasional KEK', 'Kementerian Koordinator Bidang Perekonomian'],
            ['Sekretariat Kementerian Koperasi', 'Kementerian Koperasi'],
            ['Direktorat Jenderal Bina Pembangunan Daerah, Kemendagri', 'Kementerian Dalam Negeri'],
            ['Kepala Otorita Ibu Kota Nusantara', 'Otorita Ibu Kota Nusantara'],
            ['Kantor Staf Presiden', 'Kantor Staf Presiden'],
        ];
    }

    #[DataProvider('kasus')]
    public function test_induk_kementerian(string $unit, string $induk): void
    {
        $this->assertSame($induk, Kementerian::induk($unit));
    }
}
