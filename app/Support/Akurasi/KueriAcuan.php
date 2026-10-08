<?php

namespace App\Support\Akurasi;

use App\Models\PeriodeCutoff;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Support\Facades\DB;

/**
 * Query SQL acuan yang ditulis manual untuk uji akurasi K1-K4 dan P1-P7.
 *
 * Sengaja TIDAK memakai DashboardService/FilterGlobal::cocok/terapkanSql:
 * - K1, K2, P1, P6, P7 dihitung langsung dari tabel inti (psn, psn_lokasi,
 *   psn_sumber_dana, ref_status_psn) -- menguji snapshot + agregasi sekaligus.
 * - K3, K4, P2, P5 dihitung dari kolom snapshot_psn dengan SQL agregat.
 * - P3, P4 dihitung dari tingkat KP/RO (snapshot_kegiatan), jalur yang berbeda
 *   dari service (yang menjumlah kolom tingkat PSN).
 * Filter diterjemahkan ke klausa EXISTS atas tabel inti.
 *
 * Catatan: acuan berbasis tabel inti mencerminkan kondisi terkini; jalankan
 * segera setelah snapshot dibangun agar keduanya sebanding.
 */
class KueriAcuan
{
    /** @var array<int, mixed> */
    protected array $ikat = [];

    public function __construct(protected PeriodeCutoff $c, protected FilterGlobal $f) {}

    /** Subquery id PSN aktif pada cut-off yang lolos filter (dari tabel inti). */
    protected function psnAktif(): string
    {
        $this->ikat = [$this->c->tanggal_cutoff->copy()->endOfDay()->toDateTimeString()];
        $w = ['(p.deleted_at IS NULL OR p.deleted_at > ?)', 'COALESCE(s.is_aktif, 1) = 1'];

        if ($this->f->prov) {
            $w[] = 'EXISTS (SELECT 1 FROM psn_lokasi l WHERE l.psn_id = p.id AND l.deleted_at IS NULL AND l.provinsi_kode IN ('.$this->tanda($this->f->prov).'))';
        }
        if ($this->f->klaster) {
            $w[] = 'p.klaster_id IN ('.$this->tanda($this->f->klaster).')';
        }
        if ($this->f->dit) {
            $w[] = 'EXISTS (SELECT 1 FROM psn_unit_pengampu u WHERE u.psn_id = p.id AND u.unit_kerja_id IN ('.$this->tanda($this->f->dit).'))';
        }
        if ($this->f->kat) {
            $w[] = $this->f->kat === 'PKPN' ? 'p.klaster_pkpn_id IS NOT NULL' : 'p.klaster_pkpn_id IS NULL';
        }
        if ($this->f->dana) {
            $kode = array_keys(array_filter(config('psn_dashboard.skema_dana'), fn ($s) => in_array($s, $this->f->dana, true)));
            $w[] = $kode
                ? 'EXISTS (SELECT 1 FROM psn_sumber_dana d JOIN ref_sumber_dana r ON r.id = d.sumber_dana_id WHERE d.psn_id = p.id AND r.kode IN ('.$this->tanda($kode).'))'
                : '1 = 0';
        }
        if ($this->f->status) {
            $this->ikat[] = $this->c->id; // placeholder periode_cutoff_id mendahului daftar status
            $w[] = 'EXISTS (SELECT 1 FROM snapshot_psn sx WHERE sx.psn_id = p.id AND sx.periode_cutoff_id = ? AND sx.status_progres IN ('.$this->tanda($this->f->status).'))';
        }

        return 'SELECT p.id FROM psn p LEFT JOIN ref_status_psn s ON s.id = p.status_psn_id WHERE '.implode(' AND ', $w);
    }

    protected function tanda(array $nilai): string
    {
        array_push($this->ikat, ...$nilai);

        return implode(', ', array_fill(0, count($nilai), '?'));
    }

    protected function satu(string $sql, array $ikatTambahan = []): ?object
    {
        $sub = $this->psnAktif();

        return DB::selectOne(str_replace('{AKTIF}', $sub, $sql), [...$this->ikat, ...$ikatTambahan]);
    }

    protected function banyak(string $sql, array $ikatTambahan = []): array
    {
        $sub = $this->psnAktif();

        return DB::select(str_replace('{AKTIF}', $sub, $sql), [...$this->ikat, ...$ikatTambahan]);
    }

    // ------------------------------------------------------------------ K1-K4

    public function k1(): int
    {
        return (int) $this->satu('SELECT COUNT(*) AS n FROM ({AKTIF}) a')->n;
    }

    public function k2(): float
    {
        return round((float) $this->satu('SELECT COALESCE(SUM(p.nilai_investasi_rp), 0) / 1e12 AS n FROM psn p WHERE p.id IN ({AKTIF}) AND p.investasi_anomali = 0')->n, 1);
    }

    public function k3(): ?float
    {
        $r = $this->satu('SELECT SUM(s.progres_realisasi_persen * s.nilai_investasi_rp) / SUM(s.nilai_investasi_rp) AS n
            FROM snapshot_psn s WHERE s.psn_id IN ({AKTIF}) AND s.periode_cutoff_id = ?
              AND s.progres_realisasi_persen IS NOT NULL AND s.nilai_investasi_rp > 0', [$this->c->id]);

        return $r->n === null ? null : round((float) $r->n, 1);
    }

    /** K4 dari komponennya (status & level risiko), bukan dari kolom is_kritis. */
    public function k4(): int
    {
        return (int) $this->satu("SELECT COUNT(*) AS n FROM snapshot_psn s WHERE s.psn_id IN ({AKTIF}) AND s.periode_cutoff_id = ?
            AND (s.status_progres = 'TERLAMBAT' OR s.risiko_level_maks IN ('Tinggi', 'Sangat Tinggi'))", [$this->c->id])->n;
    }

    // ------------------------------------------------------------------ P1-P7

    /** @return array<string,int> label => jumlah (8 teratas + Lainnya) */
    public function p1(): array
    {
        $baris = $this->banyak("SELECT COALESCE(k.nama, 'Tanpa klaster') AS label, COUNT(*) AS n
            FROM psn p LEFT JOIN ref_klaster k ON k.id = p.klaster_id WHERE p.id IN ({AKTIF})
            GROUP BY p.klaster_id, k.nama");
        // Urutan: jumlah menurun, seri dipecah dengan nama menaik (collation PHP, bukan basis data).
        usort($baris, fn ($a, $b) => [(int) $b->n, $a->label] <=> [(int) $a->n, $b->label]);
        $top = config('psn_dashboard.p1_top_n');
        $hasil = [];
        foreach (array_slice($baris, 0, $top) as $r) {
            $hasil[$r->label] = (int) $r->n;
        }
        if (count($baris) > $top) {
            $hasil['Lainnya'] = array_sum(array_map(fn ($r) => (int) $r->n, array_slice($baris, $top)));
        }

        return $hasil;
    }

    /** @return array{rencana: ?float, realisasi: ?float} */
    public function p2(?PeriodeCutoff $c = null): array
    {
        $r = $this->satu('SELECT SUM(s.progres_rencana_persen * s.nilai_investasi_rp) / SUM(s.nilai_investasi_rp) AS rencana,
                SUM(s.progres_realisasi_persen * s.nilai_investasi_rp) / SUM(s.nilai_investasi_rp) AS realisasi
            FROM snapshot_psn s WHERE s.psn_id IN ({AKTIF}) AND s.periode_cutoff_id = ?
              AND s.progres_rencana_persen IS NOT NULL AND s.progres_realisasi_persen IS NOT NULL AND s.nilai_investasi_rp > 0', [($c ?? $this->c)->id]);

        return [
            'rencana' => $r->rencana === null ? null : round((float) $r->rencana, 1),
            'realisasi' => $r->realisasi === null ? null : round((float) $r->realisasi, 1),
        ];
    }

    /** Persen realisasi anggaran dari tingkat KP/RO. */
    public function p3(): ?float
    {
        $r = $this->satu('SELECT SUM(g.realisasi_anggaran_rp) AS real_, SUM(g.pagu_rp) AS pagu FROM snapshot_kegiatan g
            WHERE g.psn_id IN ({AKTIF}) AND g.periode_cutoff_id = ?', [$this->c->id]);

        return (float) $r->pagu > 0 ? round((float) $r->real_ / (float) $r->pagu * 100, 1) : null;
    }

    /** @return array{tercapai: int, total: int} dari tingkat KP/RO */
    public function p4(): array
    {
        $r = $this->satu('SELECT COALESCE(SUM(g.is_tercapai), 0) AS tercapai, COUNT(*) AS total FROM snapshot_kegiatan g
            WHERE g.psn_id IN ({AKTIF}) AND g.periode_cutoff_id = ?', [$this->c->id]);

        return ['tercapai' => (int) $r->tercapai, 'total' => (int) $r->total];
    }

    /** @return array<int, array{rencana: ?float, realisasi: ?float}> bulan => nilai, untuk cut-off terbit tahun berjalan */
    public function p5(): array
    {
        $hasil = [];
        foreach (PeriodeCutoff::where('status', 'TERBIT')->whereYear('tanggal_cutoff', $this->c->tanggal_cutoff->year)
            ->where('tanggal_cutoff', '<=', $this->c->tanggal_cutoff)->get() as $p) {
            $hasil[$p->tanggal_cutoff->month] = (new self($p, $this->f))->p2($p);
        }

        return $hasil;
    }

    /** @return array<string, array{investasi: float, jumlah: int}> skema => nilai */
    public function p6(): array
    {
        $kasus = collect(config('psn_dashboard.skema_dana'))->map(fn ($s, $k) => "WHEN '{$k}' THEN '{$s}'")->implode(' ');
        $baris = $this->banyak("SELECT x.skema, COUNT(*) AS n, SUM(x.investasi) / 1e12 AS investasi FROM (
                SELECT DISTINCT p.id, CASE r.kode {$kasus} ELSE 'LAINNYA' END AS skema,
                       CASE WHEN p.investasi_anomali = 1 THEN 0 ELSE COALESCE(p.nilai_investasi_rp, 0) END AS investasi
                FROM psn p JOIN psn_sumber_dana d ON d.psn_id = p.id JOIN ref_sumber_dana r ON r.id = d.sumber_dana_id
                WHERE p.id IN ({AKTIF})
            ) x GROUP BY x.skema");

        return collect($baris)->mapWithKeys(fn ($r) => [$r->skema => ['investasi' => round((float) $r->investasi, 1), 'jumlah' => (int) $r->n]])->all();
    }

    /** @return array<string,int> kode provinsi => jumlah PSN (multi-lokasi dihitung di tiap provinsi) */
    public function p7(): array
    {
        return collect($this->banyak('SELECT l.provinsi_kode AS kode, COUNT(DISTINCT l.psn_id) AS n FROM psn_lokasi l
            WHERE l.deleted_at IS NULL AND l.psn_id IN ({AKTIF}) GROUP BY l.provinsi_kode'))
            ->mapWithKeys(fn ($r) => [$r->kode => (int) $r->n])->sortKeys()->all();
    }
}
