<?php

namespace App\Http\Controllers;

use App\Exports\EvaluasiStatusExport;
use App\Models\EvaluasiPsn;
use App\Models\Psn;
use App\Services\EvaluasiStatusService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/** Evaluasi keberlanjutan status PSN: pemantauan, daftar, dan isian per PSN (docs/evaluasi-status-psn.md). */
class EvaluasiStatusController extends Controller
{
    public function __construct(protected EvaluasiStatusService $layanan) {}

    public function index(Request $r)
    {
        $f = $this->filter($r);
        $daftar = $this->layanan->daftar();
        $alur = $this->layanan->alur($daftar);
        $harian = $this->layanan->harian($daftar);

        $baris = $this->saring($daftar, $f)->values();
        $hal = LengthAwarePaginator::resolveCurrentPage();
        $halaman = new LengthAwarePaginator($baris->forPage($hal, 25)->values(), $baris->count(), 25, $hal,
            ['path' => $r->url(), 'query' => $r->query()]);

        return view('evaluasi.index', [
            'cfg' => config('psn_dashboard.evaluasi_status'), 'tahun' => $this->layanan->tahun(), 'cutoff' => $this->layanan->cutoff(),
            'alur' => $alur, 'harian' => $harian, 'daftar' => $halaman, 'filter' => $f,
            'klaster' => $daftar->pluck('klaster')->filter()->unique()->sort()->values(),
        ]);
    }

    public function ekspor(Request $r)
    {
        $baris = $this->saring($this->layanan->daftar(), $this->filter($r))->values();

        return Excel::download(new EvaluasiStatusExport($baris), 'evaluasi-status-psn-rkp-'.$this->layanan->tahun().'_'.now()->format('Ymd').'.xlsx');
    }

    public function show(Psn $psn)
    {
        $hasil = $this->layanan->satu($psn) ?? abort(404, 'PSN tidak aktif pada cut-off terbit terakhir.');
        $e = $hasil['evaluasi'];

        return view('evaluasi.show', ['psn' => $psn, 'h' => $hasil, 'e' => $e, 'cfg' => config('psn_dashboard.evaluasi_status'),
            'tahun' => $this->layanan->tahun(), 'cutoff' => $this->layanan->cutoff(), 'riwayat' => $e?->riwayat()->with('user:id,name')->get() ?? collect()]);
    }

    public function terima(Request $r, Psn $psn)
    {
        $this->layanan->satu($psn) ?? abort(404);
        $d = $r->validate(['form_diterima_at' => ['nullable', 'date', 'before_or_equal:today']]);
        $e = EvaluasiPsn::firstOrNew(['tahun_rkp' => $this->layanan->tahun(), 'psn_id' => $psn->id]);
        if (! $e->exists) {
            $this->layanan->simpan($e, ['form_diterima_at' => $d['form_diterima_at'] ?? now()->toDateString()], 'Form diterima');
        }

        return redirect()->route('evaluasi.show', $psn)->with('status', 'Form Pengendalian ditandai diterima; status: Proses Pengecekan.');
    }

    public function update(Request $r, Psn $psn)
    {
        $hasil = $this->layanan->satu($psn) ?? abort(404);
        $e = $hasil['evaluasi'] ?? abort(422, 'Tandai form diterima terlebih dahulu.');
        $c = config('psn_dashboard.evaluasi_status');
        $u = $r->user();

        $aturan = [];
        if ($u->can('evaluasi.input')) {
            $aturan += [
                'form_diterima_at' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
                'a6_tahun_penetapan' => ['nullable', 'integer', 'min:2000', 'max:'.$this->layanan->tahun()],
                'jenis_psn' => ['nullable', Rule::in(array_keys($c['jenis_psn']))],
                'tanpa_isu' => ['boolean'],
                'd1_kebutuhan' => ['nullable', Rule::in(array_keys($c['d1']))],
                'd1_alasan' => ['nullable', 'required_if:d1_kebutuhan,TIDAK', Rule::in(array_keys($c['d1_alasan']))],
                'd1_alasan_lain' => ['nullable', 'required_if:d1_alasan,LAINNYA', 'string', 'max:2000'],
                'd2_fasilitas' => ['nullable', 'array'],
                'd2_fasilitas.*' => [Rule::in(array_keys($c['d2_fasilitas']))],
                'd2_justifikasi' => ['nullable', 'string', 'max:4000'],
                'd3_tahapan' => ['nullable', Rule::in(array_keys($c['tahapan']))],
                'd3_target' => ['nullable', 'string', 'max:2000'],
                'd3_tahun_selesai' => ['nullable', 'integer', 'min:2020', 'max:2100'],
                'pic' => ['nullable', 'array'],
                'pic.nama' => ['nullable', 'string', 'max:150'], 'pic.jabatan' => ['nullable', 'string', 'max:150'],
                'pic.unit' => ['nullable', 'string', 'max:200'], 'pic.kontak' => ['nullable', 'string', 'max:150'], 'pic.tanggal' => ['nullable', 'date'],
            ];
        }
        if ($u->can('evaluasi.verifikasi')) {
            $aturan += [
                'hasil_pengecekan' => ['nullable', Rule::in(array_keys($c['hasil_pengecekan']))],
                'tindak_lanjut' => ['nullable', Rule::in(array_keys($c['tindak_lanjut']))],
                'catatan_analisis' => ['nullable', 'string', 'max:5000'],
            ];
        }
        if ($u->can('evaluasi.kelola')) {
            // Indikasi Status hanya diisi bila Catatan Hasil Analisis sudah ada (aturan kertas kerja).
            $aturan['indikasi_status'] = ['nullable', Rule::in(array_keys($c['indikasi']))];
        }
        $aturan['keterangan_riwayat'] = ['nullable', 'string', 'max:255'];

        $d = $r->validate($aturan, [], [
            'd1_alasan' => 'alasan D1', 'd1_alasan_lain' => 'alasan lainnya', 'a6_tahun_penetapan' => 'tahun penetapan (A6)', 'd3_tahun_selesai' => 'target tahun selesai (D3)',
        ]);
        $catatan = array_key_exists('catatan_analisis', $d) ? $d['catatan_analisis'] : $e->catatan_analisis;
        if (! empty($d['indikasi_status']) && blank($catatan)) {
            return back()->withInput()->withErrors(['indikasi_status' => 'Isi Catatan Hasil Analisis sebelum menetapkan Indikasi Status.']);
        }
        // Kotak centang tidak terkirim bila kosong: bagian isian PJ dikirim utuh dengan penanda bagian_input.
        if ($u->can('evaluasi.input') && $r->boolean('bagian_input')) {
            $d['d2_fasilitas'] = array_values(array_unique($d['d2_fasilitas'] ?? [])) ?: null;
            $d['tanpa_isu'] = $r->boolean('tanpa_isu');
        }
        if (isset($d['pic'])) {
            $d['pic'] = array_filter($d['pic'], fn ($v) => filled($v)) ?: null;
        }
        if (array_key_exists('d1_kebutuhan', $d) && $d['d1_kebutuhan'] !== 'TIDAK') {
            $d['d1_alasan'] = null;
            $d['d1_alasan_lain'] = null;
        }
        $ket = $d['keterangan_riwayat'] ?? null;
        unset($d['keterangan_riwayat']);
        // Tindak lanjut kosong pada formulir = ikuti pemetaan otomatis dari Hasil Pengecekan.
        if (array_key_exists('tindak_lanjut', $d) && blank($d['tindak_lanjut'])) {
            $d['tindak_lanjut'] = $c['tindak_lanjut_otomatis'][$d['hasil_pengecekan'] ?? $e->hasil_pengecekan] ?? null;
        }

        $this->layanan->simpan($e, $d, $ket);
        $peringatan = str_starts_with((string) $e->hasil_pengecekan, 'LENGKAP') && ! $this->layanan->satu($psn)['lengkap']
            ? ' Perhatian: Hasil Pengecekan "Lengkap" padahal cek otomatis masih menemukan isian wajib yang kosong.' : '';

        return redirect()->route('evaluasi.show', $psn)->with('status', 'Evaluasi tersimpan. Status akhir: '.$c['status'][$e->status_akhir].'.'.$peringatan);
    }

    protected function filter(Request $r): array
    {
        $c = config('psn_dashboard.evaluasi_status');

        return $r->validate([
            'status' => ['nullable', Rule::in(array_keys($c['status']))],
            'rekomendasi' => ['nullable', Rule::in(array_keys($c['rekomendasi']))],
            'kelengkapan' => ['nullable', Rule::in(['LENGKAP', 'KURANG'])],
            'sumber' => ['nullable', Rule::in(array_values(config('psn_dashboard.sumber_psn')))],
            'klaster' => ['nullable', 'string', 'max:200'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
    }

    protected function saring(Collection $daftar, array $f): Collection
    {
        return $daftar
            ->when($f['status'] ?? null, fn ($c, $v) => $c->where('status_akhir', $v))
            ->when($f['rekomendasi'] ?? null, fn ($c, $v) => $c->where('rekomendasi', $v))
            ->when($f['kelengkapan'] ?? null, fn ($c, $v) => $c->where('lengkap', $v === 'LENGKAP'))
            ->when($f['sumber'] ?? null, fn ($c, $v) => $c->where('sumber', $v))
            ->when($f['klaster'] ?? null, fn ($c, $v) => $c->where('klaster', $v))
            ->when($f['q'] ?? null, fn ($c, $v) => $c->filter(fn ($b) => str_contains(mb_strtolower($b['nama'].' '.$b['kode']), mb_strtolower($v))));
    }
}
