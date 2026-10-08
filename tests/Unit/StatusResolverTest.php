<?php

namespace Tests\Unit;

use App\Enums\LevelRisiko;
use App\Enums\StatusProgres;
use App\Services\StatusResolver;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StatusResolverTest extends TestCase
{
    protected StatusResolver $resolver;

    protected CarbonImmutable $cutoff;

    protected function setUp(): void
    {
        $this->resolver = new StatusResolver(require __DIR__.'/../../config/psn_dashboard.php');
        $this->cutoff = CarbonImmutable::parse('2026-09-30');
    }

    public static function batasDeviasi(): array
    {
        return [
            'di atas rencana' => [10.0, StatusProgres::OnTrack],
            'tepat rencana' => [0.0, StatusProgres::OnTrack],
            'sedikit di atas -5' => [-4.99, StatusProgres::OnTrack],
            'tepat -5 => Berisiko' => [-5.0, StatusProgres::Berisiko],
            'di antara' => [-12.5, StatusProgres::Berisiko],
            'tepat -20 => Berisiko' => [-20.0, StatusProgres::Berisiko],
            'sedikit di bawah -20' => [-20.01, StatusProgres::Terlambat],
            'jauh di bawah' => [-60.0, StatusProgres::Terlambat],
        ];
    }

    #[DataProvider('batasDeviasi')]
    public function test_status_dari_deviasi(float $deviasi, StatusProgres $harapan): void
    {
        $this->assertSame($harapan, $this->resolver->statusDariDeviasi($deviasi));
    }

    public function test_deviasi_dihitung_realisasi_dikurangi_rencana(): void
    {
        $update = $this->cutoff->subDays(3);
        $this->assertSame(StatusProgres::OnTrack, $this->resolver->statusProgres(50, 46, $update, $this->cutoff));     // -4
        $this->assertSame(StatusProgres::Berisiko, $this->resolver->statusProgres(50, 45, $update, $this->cutoff));    // -5
        $this->assertSame(StatusProgres::Terlambat, $this->resolver->statusProgres(50, 29.9, $update, $this->cutoff)); // -20,1
    }

    public function test_floating_point_tidak_menggeser_batas(): void
    {
        // 30.1 - 35.1 menghasilkan -5.000000000000004 pada float.
        $this->assertSame(StatusProgres::Berisiko, $this->resolver->statusProgres(35.1, 30.1, $this->cutoff, $this->cutoff));
        $this->assertSame(StatusProgres::Berisiko, $this->resolver->statusProgres(40.3, 20.3, $this->cutoff, $this->cutoff));
    }

    public function test_tanpa_data_bila_pembaruan_lebih_dari_35_hari(): void
    {
        $this->assertSame(StatusProgres::OnTrack, $this->resolver->statusProgres(10, 10, $this->cutoff->subDays(35), $this->cutoff));
        $this->assertSame(StatusProgres::TanpaData, $this->resolver->statusProgres(10, 10, $this->cutoff->subDays(36), $this->cutoff));
        $this->assertSame(StatusProgres::TanpaData, $this->resolver->statusProgres(10, 10, null, $this->cutoff));
    }

    public function test_tanpa_data_bila_rencana_atau_realisasi_kosong(): void
    {
        $this->assertSame(StatusProgres::TanpaData, $this->resolver->statusProgres(null, 10, $this->cutoff, $this->cutoff));
        $this->assertSame(StatusProgres::TanpaData, $this->resolver->statusProgres(10, null, $this->cutoff, $this->cutoff));
    }

    public static function batasSkor(): array
    {
        return [
            [1, LevelRisiko::Rendah], [4, LevelRisiko::Rendah],
            [5, LevelRisiko::Sedang], [9, LevelRisiko::Sedang],
            [10, LevelRisiko::Tinggi], [16, LevelRisiko::Tinggi],
            [17, LevelRisiko::SangatTinggi], [25, LevelRisiko::SangatTinggi],
        ];
    }

    #[DataProvider('batasSkor')]
    public function test_level_dari_skor(int $skor, LevelRisiko $harapan): void
    {
        $this->assertSame($harapan, $this->resolver->levelDariSkor($skor));
    }

    public function test_level_dari_kemungkinan_dan_dampak(): void
    {
        $this->assertSame([LevelRisiko::Tinggi, 12], $this->resolver->levelRisiko(3, 4));
        $this->assertSame([LevelRisiko::SangatTinggi, 25], $this->resolver->levelRisiko(5, 5));
    }

    public function test_level_dari_label_bila_skala_kosong(): void
    {
        $this->assertSame([LevelRisiko::Sedang, 9], $this->resolver->levelRisiko(null, null, 'Sedang'));
        $this->assertSame([null, null], $this->resolver->levelRisiko(null, 3, null));
        // Skala menang atas label bila keduanya ada.
        $this->assertSame([LevelRisiko::Rendah, 2], $this->resolver->levelRisiko(1, 2, 'Sangat Tinggi'));
    }

    public function test_skala_di_luar_1_sampai_5_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->skorRisiko(0, 3);
    }

    public function test_skor_di_luar_rentang_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->levelDariSkor(26);
    }

    public function test_risiko_kritis_mulai_dari_tinggi(): void
    {
        $this->assertFalse($this->resolver->isRisikoKritis(LevelRisiko::Sedang));
        $this->assertTrue($this->resolver->isRisikoKritis(LevelRisiko::Tinggi));
        $this->assertTrue($this->resolver->isRisikoKritis(LevelRisiko::SangatTinggi));
        $this->assertFalse($this->resolver->isRisikoKritis(null));
    }

    public function test_delta_jumlah_dalam_persen_naik_hijau(): void
    {
        $this->assertSame(['nilai' => 10.0, 'satuan' => '%', 'arah' => 'naik', 'baik' => true], $this->resolver->delta(110, 100));
    }

    public function test_delta_persen_dalam_pp(): void
    {
        $this->assertSame(['nilai' => -2.5, 'satuan' => 'pp', 'arah' => 'turun', 'baik' => false], $this->resolver->delta(40, 42.5, 'persen'));
    }

    public function test_delta_k4_turun_hijau(): void
    {
        $d = $this->resolver->delta(8, 10, 'jumlah', 'turun');
        $this->assertSame(-20.0, $d['nilai']);
        $this->assertTrue($d['baik']);
        $this->assertFalse($this->resolver->delta(12, 10, 'jumlah', 'turun')['baik']);
    }

    public function test_delta_tidak_tersedia_tanpa_pembanding(): void
    {
        $this->assertNull($this->resolver->delta(10, null)['nilai']);
        $this->assertNull($this->resolver->delta(10, 0)['nilai']);
        $this->assertSame('tetap', $this->resolver->delta(5, 5)['arah']);
        $this->assertNull($this->resolver->delta(5, 5)['baik']);
    }
}
