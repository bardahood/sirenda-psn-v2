<?php

namespace Tests\Unit;

use App\Enums\Rekomendasi;
use App\Services\ScoringService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ScoringServiceTest extends TestCase
{
    protected array $kriteria = [];

    protected function setUp(): void
    {
        // Struktur sesuai ref_kriteria: KU1-3, KP1-6 (KP4-6 kondisional pengusul), KK1-5 (KK3-4 infrastruktur), KL1, KT1.
        $id = 0;
        $tambah = function (string $kel, string $kode, string $tipe = 'SKOR_0_3', ?string $kond = null) use (&$id) {
            $this->kriteria[] = ['id' => ++$id, 'kelompok' => $kel, 'kode' => $kode, 'tipe_nilai' => $tipe, 'kondisional' => $kond];
        };
        foreach ([1, 2, 3] as $i) {
            $tambah('UTAMA', "KU{$i}", 'YA_TIDAK');
        }
        foreach ([1, 2, 3] as $i) {
            $tambah('PENDUKUNG', "KP{$i}");
        }
        $tambah('PENDUKUNG', 'KP4', 'SKOR_0_3', 'PENGUSUL_KL');
        $tambah('PENDUKUNG', 'KP5', 'SKOR_0_3', 'PENGUSUL_PEMDA');
        $tambah('PENDUKUNG', 'KP6', 'SKOR_0_3', 'PENGUSUL_BU');
        $tambah('KESIAPAN', 'KK1');
        $tambah('KESIAPAN', 'KK2');
        $tambah('KESIAPAN', 'KK3', 'SKOR_0_3', 'INFRASTRUKTUR');
        $tambah('KESIAPAN', 'KK4', 'SKOR_0_3', 'INFRASTRUKTUR');
        $tambah('KESIAPAN', 'KK5');
        $tambah('LOKASI', 'KL1');
        $tambah('TRISULA', 'KT1');
    }

    protected function svc(?float $rekom = null, ?float $timbang = null): ScoringService
    {
        $cfg = (require __DIR__.'/../../config/psn_dashboard.php')['penilaian'];
        $cfg['ambang'] = ['direkomendasikan' => $rekom, 'dipertimbangkan' => $timbang];

        return new ScoringService($cfg);
    }

    /** @param array<string,int|null> $per kode => nilai */
    protected function nilai(array $per): array
    {
        $map = array_column($this->kriteria, 'id', 'kode');

        return array_combine(array_map(fn ($k) => $map[$k], array_keys($per)), array_values($per));
    }

    protected function lengkap(array $timpa = []): array
    {
        return $this->nilai($timpa + [
            'KU1' => 1, 'KU2' => 1, 'KU3' => 1,
            'KP1' => 3, 'KP2' => 2, 'KP3' => 3, 'KP4' => 1, 'KP5' => null, 'KP6' => null,
            'KK1' => 3, 'KK2' => 3, 'KK3' => 2, 'KK4' => 1, 'KK5' => 3,
            'KL1' => 2, 'KT1' => 3,
        ]);
    }

    protected array $kl = ['jenis_pengusul' => 'KL', 'is_infrastruktur' => true];

    public function test_rumus_skor_komponen_dan_nilai_akhir(): void
    {
        $h = $this->svc()->hitung($this->kriteria, $this->lengkap(), $this->kl);

        // Pendukung: KP1-4 berlaku (pengusul K/L): (3+2+3+1)/(3x4)x100 = 75
        $this->assertSame(4, $h['komponen']['PENDUKUNG']['jumlah_sub']);
        $this->assertEquals(75.0, $h['komponen']['PENDUKUNG']['skor']);
        // Kesiapan (infrastruktur): (3+3+2+1+3)/(3x5)x100 = 80
        $this->assertEquals(80.0, $h['komponen']['KESIAPAN']['skor']);
        $this->assertEquals(66.67, $h['komponen']['LOKASI']['skor']);
        $this->assertEquals(100.0, $h['komponen']['TRISULA']['skor']);
        // 0,35x75 + 0,35x80 + 0,15x66,67 + 0,15x100 = 79,25
        $this->assertEquals(79.25, $h['nilai_akhir']);
        $this->assertTrue($h['gate']['lulus']);
        $this->assertTrue($h['lengkap']);
    }

    public function test_sub_kriteria_kondisional_mengubah_penyebut(): void
    {
        // Bukan infrastruktur: KK3 & KK4 tidak berlaku -> (3+3+3)/(3x3) = 100, nilai KK3/KK4 diabaikan.
        $h = $this->svc()->hitung($this->kriteria, $this->lengkap(), ['jenis_pengusul' => 'KL', 'is_infrastruktur' => false]);
        $this->assertSame(3, $h['komponen']['KESIAPAN']['jumlah_sub']);
        $this->assertEquals(100.0, $h['komponen']['KESIAPAN']['skor']);

        // Pengusul Pemda: KP5 berlaku dan belum dinilai -> komponen belum lengkap.
        $h = $this->svc()->hitung($this->kriteria, $this->lengkap(), ['jenis_pengusul' => 'PEMDA', 'is_infrastruktur' => true]);
        $this->assertNull($h['komponen']['PENDUKUNG']['skor']);
        $this->assertSame(['KP5'], $h['komponen']['PENDUKUNG']['belum_dinilai']);
        $this->assertSame(Rekomendasi::BelumLengkap, $h['rekomendasi']);
    }

    public function test_gate_satu_tidak_menolak_berapa_pun_nilainya(): void
    {
        // Semua sub-kriteria bernilai maksimal, ambang terisi, tetapi KU2 = Tidak.
        $max = $this->lengkap(['KU2' => 0, 'KP1' => 3, 'KP2' => 3, 'KP3' => 3, 'KP4' => 3, 'KK1' => 3, 'KK2' => 3, 'KK3' => 3, 'KK4' => 3, 'KK5' => 3, 'KL1' => 3, 'KT1' => 3]);
        $h = $this->svc(70, 50)->hitung($this->kriteria, $max, $this->kl);

        $this->assertEquals(100.0, $h['nilai_akhir']);
        $this->assertFalse($h['gate']['lulus']);
        $this->assertSame(['KU2'], $h['gate']['gagal']);
        $this->assertSame(Rekomendasi::Ditolak, $h['rekomendasi']);
    }

    public function test_gate_tidak_berlaku_meski_penilaian_lain_belum_lengkap(): void
    {
        $h = $this->svc(70, 50)->hitung($this->kriteria, $this->nilai(['KU1' => 0]), $this->kl);
        $this->assertSame(Rekomendasi::Ditolak, $h['rekomendasi']);
        $this->assertNull($h['nilai_akhir']);
    }

    public function test_gate_belum_dinilai_berarti_belum_lengkap(): void
    {
        $h = $this->svc(70, 50)->hitung($this->kriteria, $this->lengkap(['KU3' => null]), $this->kl);
        $this->assertNull($h['gate']['lulus']);
        $this->assertSame(['KU3'], $h['gate']['belum_dinilai']);
        $this->assertSame(Rekomendasi::BelumLengkap, $h['rekomendasi']);
    }

    public function test_ambang_belum_ditetapkan(): void
    {
        $h = $this->svc()->hitung($this->kriteria, $this->lengkap(), $this->kl);
        $this->assertSame(Rekomendasi::AmbangBelumDitetapkan, $h['rekomendasi']);
    }

    public function test_batas_ambang_rekomendasi(): void
    {
        $s = $this->svc(79.25, 60);
        $this->assertSame(Rekomendasi::Direkomendasikan, $s->rekomendasi(true, 79.25, true));
        $this->assertSame(Rekomendasi::Dipertimbangkan, $s->rekomendasi(true, 79.24, true));
        $this->assertSame(Rekomendasi::Dipertimbangkan, $s->rekomendasi(true, 60.0, true));
        $this->assertSame(Rekomendasi::TidakDirekomendasikan, $s->rekomendasi(true, 59.99, true));
        $this->assertSame(Rekomendasi::Ditolak, $s->rekomendasi(false, 99.0, true));
    }

    public function test_jenis_pengusul_kosong_tidak_lengkap(): void
    {
        $h = $this->svc(70, 50)->hitung($this->kriteria, $this->lengkap(), ['is_infrastruktur' => true]);
        $this->assertFalse($h['lengkap']);
        $this->assertNotEmpty($h['peringatan']);
        $this->assertSame(Rekomendasi::BelumLengkap, $h['rekomendasi']);
    }

    public function test_nilai_di_luar_rentang_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->hitung($this->kriteria, $this->lengkap(['KU1' => 2]), $this->kl);
    }

    public function test_skor_sub_kriteria_maksimal_3(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->svc()->hitung($this->kriteria, $this->lengkap(['KP1' => 4]), $this->kl);
    }
}
