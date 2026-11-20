<?php

namespace App\Http\Controllers;

use App\Exports\EvaluasiStatusExport;
use App\Exports\KertasKerjaExport;
use App\Models\AuditLog;
use App\Models\EvaluasiPsn;
use App\Models\Psn;
use App\Services\EvaluasiStatusService;
use App\Services\KertasKerjaIsianService;
use App\Services\KertasKerjaUnggahService;
use App\Support\Evaluasi\FormatRingkas;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            'tahun' => $this->layanan->tahun(), 'cutoff' => $this->layanan->cutoff(), 'riwayat' => $e?->riwayat()->with('user:id,name')->get() ?? collect(),
            'a8Profil' => app(FormatRingkas::class)->profilA([$psn->id])[$psn->id]['a8'] ?? null]);
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
                'a6_tahun_penetapan' => ['nullable', 'integer', 'min:'.$c['tahun_min'], 'max:'.$c['tahun_maks']],
                'a8_sumber_pendanaan' => ['nullable', Rule::in(array_keys($c['sumber_pendanaan']))],
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
                'd3_tahun_selesai' => ['nullable', 'integer', 'min:'.$c['tahun_min'], 'max:'.$c['tahun_maks']],
                'pic' => ['nullable', 'array'],
                'pic.nama' => ['nullable', 'string', 'max:150'], 'pic.jabatan' => ['nullable', 'string', 'max:150'],
                'pic.unit' => ['nullable', 'string', 'max:200'], 'pic.kontak' => ['nullable', 'string', 'max:150'], 'pic.tanggal' => ['nullable', 'date'],
                // Rincian kertas kerja (Format Ringkas).
                'b4_manfaat' => ['nullable', 'string', 'max:4000'],
                'c1_tipologi' => ['nullable', 'array'], 'c1_tipologi.*' => [Rule::in(array_diff(array_keys($c['tipologi_isu']), ['11']))],
                'c1_lainnya' => ['nullable', 'string', 'max:255'],
                'info_tambahan' => ['nullable', 'string', 'max:4000'],
                'capaian' => ['nullable', 'array'],
                'capaian.*.satuan' => ['nullable', 'string', 'max:100'],
                'capaian.*.target_akhir' => ['nullable', 'numeric', 'min:0', 'max:1e20'],
                'capaian.*.target_kumulatif' => ['nullable', 'numeric', 'min:0', 'max:1e20'],
                'capaian.*.realisasi_kumulatif' => ['nullable', 'numeric', 'min:0', 'max:1e20'],
                'capaian.*.keterangan' => ['nullable', 'string', 'max:2000'],
                'milestone' => ['nullable', 'array', 'max:30'],
                'milestone.*.kode' => ['required', Rule::in(array_merge(...array_map('array_keys', array_values($c['milestone']))))],
                'milestone.*.nama_lain' => ['nullable', 'string', 'max:255'],
                'milestone.*.status' => ['nullable', Rule::in(array_keys($c['status_milestone']))],
                'milestone.*.tanggal' => ['nullable', 'date'],
                'milestone.*.keterangan' => ['nullable', 'string', 'max:1000'],
                'isu' => ['nullable', 'array', 'max:'.$c['maks_isu']],
                'isu.*.uraian' => ['nullable', 'string', 'max:2000'],
                'isu.*.tipologi' => ['nullable', Rule::in(array_keys($c['tipologi_isu']))],
                'isu.*.jalur_kritis' => ['nullable', Rule::in(['0', '1'])],
                'isu.*.upaya' => ['nullable', 'string', 'max:2000'],
                'isu.*.dukungan' => ['nullable', 'string', 'max:2000'],
                'lampiran' => ['nullable', 'array', 'max:'.$c['maks_lampiran']],
                'lampiran.*.jenis' => ['nullable', Rule::in(array_keys($c['lampiran_jenis']))],
                'lampiran.*.judul' => ['nullable', 'string', 'max:255'],
                'lampiran.*.bentuk' => ['nullable', Rule::in(array_keys($c['lampiran_bentuk']))],
                'lampiran.*.tautan' => ['nullable', 'string', 'max:1000'],
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
            $d['c1_tipologi'] = array_values(array_unique($d['c1_tipologi'] ?? [])) ?: null;
            $d['tanpa_isu'] = $r->boolean('tanpa_isu');
        }
        $rincian = $u->can('evaluasi.input') && $r->boolean('bagian_rincian')
            ? collect(['capaian', 'milestone', 'isu', 'lampiran'])->filter(fn ($k) => array_key_exists($k, $d))->mapWithKeys(fn ($k) => [$k => $d[$k] ?? []])->all() : null;
        unset($d['capaian'], $d['milestone'], $d['isu'], $d['lampiran']);
        if (isset($d['pic'])) {
            $d['pic'] = array_filter($d['pic'], fn ($v) => filled($v)) ?: null;
        }
        // Alasan dibuang bila D1 diisi "Masih"; bila D1 kosong (ikuti SIRENDA) alasan dipertahankan.
        if (array_key_exists('d1_kebutuhan', $d) && $d['d1_kebutuhan'] === 'MASIH') {
            $d['d1_alasan'] = null;
            $d['d1_alasan_lain'] = null;
        }
        $ket = $d['keterangan_riwayat'] ?? null;
        unset($d['keterangan_riwayat']);
        // Tindak lanjut kosong pada formulir = ikuti pemetaan otomatis dari Hasil Pengecekan.
        if (array_key_exists('tindak_lanjut', $d) && blank($d['tindak_lanjut'])) {
            $d['tindak_lanjut'] = $c['tindak_lanjut_otomatis'][$d['hasil_pengecekan'] ?? $e->hasil_pengecekan] ?? null;
        }

        $this->layanan->simpan($e, $d, $ket, $rincian);
        $peringatan = str_starts_with((string) $e->hasil_pengecekan, 'LENGKAP') && ! $this->layanan->satu($psn)['lengkap']
            ? ' Perhatian: Hasil Pengecekan "Lengkap" padahal cek otomatis masih menemukan isian wajib yang kosong.' : '';

        return redirect()->route('evaluasi.show', $psn)->with('status', 'Evaluasi tersimpan. Status akhir: '.$c['status'][$e->status_akhir].'.'.$peringatan);
    }

    /** Kertas kerja pengendalian: sheet "Format Ringkas" terisi dari profil dan isian setiap PSN. */
    public function kertasKerja(Request $r, FormatRingkas $format)
    {
        $f = $this->filter($r);
        $daftar = $this->layanan->daftar();
        $baris = $this->saring($daftar, $f)->values();
        $hal = LengthAwarePaginator::resolveCurrentPage();
        $halaman = new LengthAwarePaginator($format->semua($baris->forPage($hal, 20)->values()), $baris->count(), 20, $hal, ['path' => $r->url(), 'query' => $r->query()]);
        // Nomor urut mengikuti posisi pada daftar tersaring.
        $halaman->getCollection()->transform(function ($b, $i) use ($halaman) {
            $b['nilai']['no'] = $halaman->firstItem() + $i;

            return $b;
        });

        return view('evaluasi.kertas-kerja', ['format' => $format, 'baris' => $halaman, 'filter' => $f, 'cfg' => config('psn_dashboard.evaluasi_status'),
            'tahun' => $this->layanan->tahun(), 'cutoff' => $this->layanan->cutoff(),
            'klaster' => $daftar->pluck('klaster')->filter()->unique()->sort()->values()]);
    }

    public function kertasKerjaXlsx(Request $r, FormatRingkas $format)
    {
        $baris = $format->semua($this->saring($this->layanan->daftar(), $this->filter($r))->values());

        return Excel::download(new KertasKerjaExport($format, $baris), 'kertas-kerja-pengendalian-psn-rkp-'.$this->layanan->tahun().'_'.now()->format('Ymd').'.xlsx');
    }

    /**
     * Isi satu sel kertas kerja secara langsung (PATCH JSON {kunci, nilai, nilai_lain?, no}). Mengembalikan HTML baris
     * yang sudah dihitung ulang (kelengkapan, rekomendasi, status akhir). Bila form PSN belum ditandai diterima,
     * isian pertama oleh pengguna berizin input sekaligus menandainya diterima hari ini.
     */
    public function sel(Request $r, Psn $psn, FormatRingkas $format, KertasKerjaIsianService $isian)
    {
        $c = config('psn_dashboard.evaluasi_status');
        $editor = $format->editor();
        $v = $r->validate(['kunci' => ['required', 'string', Rule::in(array_keys($editor))], 'no' => ['nullable', 'integer', 'min:1']]);
        $ed = $editor[$v['kunci']];
        if ($ed['tipe'] === 'rincian') {
            return response()->json(['message' => 'Kolom ini diisi melalui formulir PSN.'], 422);
        }
        abort_unless($r->user()->can("evaluasi.{$ed['izin']}"), 403, 'Anda tidak berwenang mengisi kolom ini.');
        $hasil = $this->layanan->satu($psn) ?? abort(404);
        $d = $r->validate($isian->aturan($v['kunci']), [], ['nilai' => 'nilai']);

        if (! $hasil['evaluasi']) {
            abort_unless($r->user()->can('evaluasi.input'), 422, 'Form Pengendalian PSN ini belum ditandai diterima.');
        }
        $e = $isian->evaluasi($psn, $hasil['evaluasi'], 'Form diterima (isian kertas kerja)');
        if ($galat = $isian->isi($e, $v['kunci'], $d['nilai'] ?? null, $d['nilai_lain'] ?? null)) {
            return response()->json(['message' => $galat], 422);
        }

        $baris = $format->semua($this->layanan->daftar([$psn->id]))->first();
        $baris['nilai']['no'] = $v['no'] ?? 1;

        return response()->json([
            'message' => 'Tersimpan. Status akhir: '.$c['status'][$baris['r']['status_akhir']].'.',
            'html' => view('evaluasi._baris-kk', ['b' => $baris, 'format' => $format, 'kolom' => $format->kolom(), 'editor' => $format->editor()])->render(),
        ]);
    }

    /** Unggah kertas kerja Excel: formulir. */
    public function unggah()
    {
        return view('evaluasi.unggah', ['tahun' => $this->layanan->tahun()]);
    }

    /** Baca berkas lalu tampilkan pratinjau perubahan (belum ada yang disimpan). */
    public function unggahBaca(Request $r, KertasKerjaUnggahService $unggah)
    {
        $r->validate(['berkas' => ['required', 'file', 'mimes:xlsx', 'max:10240']], [], ['berkas' => 'berkas kertas kerja']);
        try {
            $data = $unggah->pratinjau($unggah->baca($r->file('berkas')->getRealPath()), $r->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }
        $data['nama_berkas'] = $r->file('berkas')->getClientOriginalName();
        $token = (string) Str::uuid();
        Cache::put("kk-unggah:{$r->user()->id}:{$token}", $data, now()->addMinutes(30));

        return redirect()->route('evaluasi.unggah.pratinjau', ['token' => $token]);
    }

    public function unggahPratinjau(Request $r)
    {
        $token = (string) $r->query('token');
        $data = Cache::get("kk-unggah:{$r->user()->id}:{$token}");
        if (! $data) {
            return redirect()->route('evaluasi.unggah')->withErrors(['berkas' => 'Pratinjau kedaluwarsa atau tidak ditemukan. Unggah ulang berkasnya.']);
        }

        return view('evaluasi.unggah-pratinjau', ['data' => $data, 'token' => $token, 'cfg' => config('psn_dashboard.evaluasi_status'), 'tahun' => $this->layanan->tahun()]);
    }

    public function unggahTerapkan(Request $r, KertasKerjaUnggahService $unggah)
    {
        $v = $r->validate(['token' => ['required', 'uuid'], 'psn' => ['nullable', 'array'], 'psn.*' => ['integer']]);
        $kunci = "kk-unggah:{$r->user()->id}:{$v['token']}";
        $data = Cache::pull($kunci);
        if (! $data) {
            return redirect()->route('evaluasi.unggah')->withErrors(['berkas' => 'Pratinjau kedaluwarsa atau sudah diterapkan. Unggah ulang berkasnya.']);
        }
        $h = $unggah->terapkan($data, array_map('intval', $v['psn'] ?? []), $r->user());
        AuditLog::create(['user_id' => $r->user()->id, 'user_label' => $r->user()->username, 'tabel' => 'evaluasi_psn', 'aksi' => 'UPDATE',
            'nilai_baru' => ['unggah_kertas_kerja' => ['berkas' => $data['nama_berkas'] ?? null, 'psn' => $h['psn'], 'sel' => $h['sel'], 'galat' => count($h['galat'])]]]);

        return redirect()->route('evaluasi.kertas-kerja')->with('status', "Unggah kertas kerja selesai: {$h['sel']} isian pada {$h['psn']} PSN diterapkan.")
            ->with('galat_unggah', $h['galat']);
    }

    /** Grafik status harian (sheet "Grafik Status Harian"): 14 hari mulai tanggal pilihan + posisi saat ini. */
    public function grafik(Request $r)
    {
        $hari = (int) config('psn_dashboard.evaluasi_status.hari_grafik');
        $v = $r->validate(['mulai' => ['nullable', 'date_format:Y-m-d']]);
        $mulai = isset($v['mulai']) ? CarbonImmutable::parse($v['mulai']) : CarbonImmutable::today()->subDays($hari - 1);
        $daftar = $this->layanan->daftar();
        $harian = $this->layanan->harian($daftar, $mulai->addDays($hari - 1));
        $posisi = $daftar->countBy('status_akhir');
        $terakhir = DB::table('evaluasi_psn_riwayat')->whereIn('evaluasi_psn_id', $daftar->pluck('evaluasi')->filter()->pluck('id')->all() ?: [0])->max('tanggal');

        return view('evaluasi.grafik', ['harian' => $harian, 'mulai' => $mulai, 'posisi' => $posisi, 'terakhir' => $terakhir,
            'cfg' => config('psn_dashboard.evaluasi_status'), 'tahun' => $this->layanan->tahun(), 'cutoff' => $this->layanan->cutoff(),
            'cocok' => $this->layanan->harian($daftar, CarbonImmutable::today())[$hari - 1]['status']
                === collect(array_keys(array_diff_key(config('psn_dashboard.evaluasi_status.status'), ['BELUM_DITERIMA' => 1])))->mapWithKeys(fn ($k) => [$k => $posisi[$k] ?? 0])->all()]);
    }

    protected function filter(Request $r): array
    {
        $c = config('psn_dashboard.evaluasi_status');

        return $r->validate([
            'status' => ['nullable', Rule::in(array_keys($c['status']))],
            'rekomendasi' => ['nullable', Rule::in(array_keys($c['rekomendasi']))],
            'kelengkapan' => ['nullable', Rule::in(['LENGKAP', 'KURANG', 'DITERIMA_KURANG'])],
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
            ->when($f['kelengkapan'] ?? null, fn ($c, $v) => $v === 'DITERIMA_KURANG'
                ? $c->filter(fn ($b) => ! $b['lengkap'] && $b['evaluasi'])
                : $c->where('lengkap', $v === 'LENGKAP'))
            ->when($f['sumber'] ?? null, fn ($c, $v) => $c->where('sumber', $v))
            ->when($f['klaster'] ?? null, fn ($c, $v) => $c->where('klaster', $v))
            ->when($f['q'] ?? null, fn ($c, $v) => $c->filter(fn ($b) => str_contains(mb_strtolower($b['nama'].' '.$b['kode']), mb_strtolower($v))));
    }
}
