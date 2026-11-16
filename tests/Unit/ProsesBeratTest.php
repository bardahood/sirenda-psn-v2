<?php

namespace Tests\Unit;

use App\Support\ProsesBerat;
use Tests\TestCase;

class ProsesBeratTest extends TestCase
{
    public function test_batas_memori_hanya_dinaikkan(): void
    {
        $asal = ini_get('memory_limit');
        try {
            $this->assertSame(134217728, ProsesBerat::bita('128M'));
            $this->assertSame(1073741824, ProsesBerat::bita('1G'));
            $this->assertSame(-1, ProsesBerat::bita('-1'));

            config(['psn_dashboard.proses_berat.memori' => '1024M']);
            ini_set('memory_limit', '128M');
            ProsesBerat::siapkan();
            $this->assertSame('1024M', ini_get('memory_limit'));

            ini_set('memory_limit', '2048M');
            ProsesBerat::siapkan();
            $this->assertSame('2048M', ini_get('memory_limit')); // tidak diturunkan

            ini_set('memory_limit', '-1');
            ProsesBerat::siapkan();
            $this->assertSame('-1', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $asal);
        }
    }

    public function test_rute_ekspor_memakai_middleware_proses_berat(): void
    {
        foreach (['evaluasi.ekspor', 'evaluasi.kertas-kerja-xlsx', 'evaluasi.unggah.baca', 'evaluasi.unggah.terapkan', 'laporan.pengisian-xlsx'] as $rute) {
            $this->assertContains('proses.berat', app('router')->getRoutes()->getByName($rute)->gatherMiddleware(), $rute);
        }
    }
}
