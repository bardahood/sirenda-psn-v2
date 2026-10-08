<?php

namespace App\Http\Controllers;

use App\Enums\Rekomendasi;
use App\Models\AuditLog;
use App\Models\Penilaian;
use App\Models\PenilaianSkor;
use App\Models\PeriodeCutoff;
use App\Models\UsulanPsn;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Halaman Perencanaan & Penilaian Usulan PSN.
 */
class PerencanaanController extends Controller
{
    public function __construct(protected ScoringService $scoring) {}

    public function index(Request $r)
    {
        Gate::authorize('viewAny', UsulanPsn::class);
        $f = $r->validate([
            'instansi' => ['nullable', 'integer'],
            'klaster' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['BELUM_DINILAI', 'DRAFT', 'FINAL'])],
            'rekomendasi' => ['nullable', Rule::in(array_column(Rekomendasi::cases(), 'value'))],
            'tahun' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        // Penilaian terakhir per usulan untuk pengurutan berdasarkan nilai akhir.
        $terakhir = DB::table('penilaian')->whereNull('deleted_at')->selectRaw('usulan_id, MAX(id) AS id')->groupBy('usulan_id');

        $usulan = UsulanPsn::query()
            ->with(['klaster', 'unitKerja', 'pengusulInstansi', 'lokasi.provinsi'])
            ->leftJoinSub($terakhir, 'pt', 'pt.usulan_id', '=', 'usulan_psn.id')
            ->leftJoin('penilaian as p', 'p.id', '=', 'pt.id')
            ->select('usulan_psn.*', 'p.id as penilaian_id', 'p.status as penilaian_status', 'p.nilai_akhir', 'p.rekomendasi', 'p.gate_lulus')
            ->when($f['instansi'] ?? null, fn ($q, $v) => $q->where('usulan_psn.pengusul_instansi_id', $v))
            ->when($f['klaster'] ?? null, fn ($q, $v) => $q->where('usulan_psn.klaster_id', $v))
            ->when($f['tahun'] ?? null, fn ($q, $v) => $q->where('usulan_psn.tahun_rkp', $v))
            ->when($f['rekomendasi'] ?? null, fn ($q, $v) => $q->where('p.rekomendasi', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('usulan_psn.nama', 'like', "%{$v}%"))
            ->when($f['status'] ?? null, fn ($q, $v) => $v === 'BELUM_DINILAI' ? $q->whereNull('p.id') : $q->where('p.status', $v))
            ->orderByRaw('p.nilai_akhir IS NULL')->orderByDesc('p.nilai_akhir')->orderBy('usulan_psn.nama')
            ->paginate(25)->withQueryString();

        return view('perencanaan.index', [
            'usulan' => $usulan,
            'filter' => $f,
            'opsi' => [
                'instansi' => DB::table('ref_instansi')->whereIn('id', UsulanPsn::query()->whereNotNull('pengusul_instansi_id')->select('pengusul_instansi_id'))->orderBy('nama')->pluck('nama', 'id'),
                'klaster' => DB::table('ref_klaster')->orderBy('urutan')->pluck('nama', 'id'),
                'tahun' => UsulanPsn::query()->distinct()->orderByDesc('tahun_rkp')->pluck('tahun_rkp'),
            ],
        ]);
    }

    public function create()
    {
        Gate::authorize('create', UsulanPsn::class);

        return view('perencanaan.form', ['usulan' => new UsulanPsn(['tahun_rkp' => now()->year + 1]), 'opsi' => $this->opsiForm()]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', UsulanPsn::class);
        $data = $this->validasiUsulan($r);

        $usulan = DB::transaction(function () use ($data) {
            $u = UsulanPsn::create(collect($data)->except('provinsi')->all() + ['status' => 'DIAJUKAN']);
            foreach ($data['provinsi'] ?? [] as $kode) {
                $u->lokasi()->create(['provinsi_kode' => $kode]);
            }

            return $u;
        });

        return redirect()->route('perencanaan.show', $usulan)->with('status', 'Usulan berhasil ditambahkan.');
    }

    public function show(Request $r, UsulanPsn $usulan)
    {
        Gate::authorize('view', $usulan);
        $usulan->load(['klaster', 'unitKerja', 'pengusulInstansi', 'lokasi.provinsi', 'penilaian' => fn ($q) => $q->latest('id')->with('penilai')]);

        $penilaian = $r->query('penilaian')
            ? $usulan->penilaian->firstWhere('id', (int) $r->query('penilaian')) ?? abort(404)
            : $usulan->penilaian->first();

        $hasil = $penilaian ? $this->scoring->hitungPenilaian($penilaian, simpan: false) : null;
        $temuan = $penilaian ? DB::table('penilaian_skor')->where('penilaian_id', $penilaian->id)->pluck('temuan', 'kriteria_id') : collect();

        return view('perencanaan.show', [
            'usulan' => $usulan,
            'penilaian' => $penilaian,
            'hasil' => $hasil,
            'temuan' => $temuan,
            'berlaku' => $hasil ? collect($hasil['kriteria'])->mapWithKeys(fn ($k) => [$k['id'] => $this->scoring->berlaku($k, ['jenis_pengusul' => $usulan->jenis_pengusul, 'is_infrastruktur' => $usulan->is_infrastruktur])]) : collect(),
            'petaUsulan' => $this->petaUsulan($usulan),
            'perbandingan' => $this->perbandingan($usulan),
        ]);
    }

    public function storePenilaian(Request $r, UsulanPsn $usulan)
    {
        Gate::authorize('update', $usulan);
        $data = $r->validate(['forum' => ['required', 'string', 'max:100'], 'tanggal' => ['required', 'date']], [], ['forum' => 'forum penilaian']);

        $p = $usulan->penilaian()->create($data + ['penilai_id' => Auth::id(), 'status' => 'DRAFT']);
        $this->scoring->hitungPenilaian($p);
        $usulan->update(['status' => 'DINILAI']);

        return redirect()->route('perencanaan.show', [$usulan, 'penilaian' => $p->id])->with('status', 'Sesi penilaian dibuat. Silakan isi skor.');
    }

    public function simpanSkor(Request $r, Penilaian $penilaian)
    {
        Gate::authorize('nilai', $penilaian);
        $kriteria = DB::table('ref_kriteria')->where('is_aktif', true)->get()->keyBy('id');

        $aturan = ['nilai' => ['array'], 'temuan' => ['array'], 'temuan.*' => ['nullable', 'string', 'max:2000']];
        foreach ($kriteria as $k) {
            $aturan["nilai.{$k->id}"] = ['nullable', 'integer', 'min:0', 'max:'.($k->tipe_nilai === 'YA_TIDAK' ? 1 : 3)];
        }
        $data = $r->validate($aturan, ['nilai.*.max' => 'Nilai :attribute melebihi batas.'], collect($kriteria)->mapWithKeys(fn ($k) => ["nilai.{$k->id}" => $k->kode])->all());

        DB::transaction(function () use ($data, $kriteria, $penilaian) {
            foreach ($kriteria as $id => $k) {
                $nilai = $data['nilai'][$id] ?? null;
                $temuan = $data['temuan'][$id] ?? null;
                $skor = PenilaianSkor::firstOrNew(['penilaian_id' => $penilaian->id, 'kriteria_id' => $id]);
                if (! $skor->exists && $nilai === null && $temuan === null) {
                    continue;
                }
                $skor->fill(['nilai' => $nilai === null ? null : (int) $nilai, 'temuan' => $temuan])->save();
            }
            $this->scoring->hitungPenilaian($penilaian);
        });

        return redirect()->route('perencanaan.show', [$penilaian->usulan_id, 'penilaian' => $penilaian->id])->with('status', 'Skor tersimpan dan nilai dihitung ulang.');
    }

    public function finalisasi(Penilaian $penilaian)
    {
        Gate::authorize('finalisasi', $penilaian);
        $hasil = $this->scoring->hitungPenilaian($penilaian);

        // Penilaian yang gugur di gate boleh difinalkan walau sub-kriteria lain belum diisi.
        if ($hasil['rekomendasi'] === Rekomendasi::BelumLengkap) {
            return back()->withErrors(['finalisasi' => 'Penilaian belum lengkap: isi seluruh kriteria yang berlaku dan jenis pengusul terlebih dahulu.']);
        }

        $penilaian->update(['status' => 'FINAL']);
        $this->audit($penilaian, 'VERIFY', ['status' => 'FINAL', 'nilai_akhir' => $hasil['nilai_akhir'], 'rekomendasi' => $hasil['rekomendasi']->value]);

        return back()->with('status', 'Penilaian ditetapkan FINAL dengan rekomendasi: '.$hasil['rekomendasi']->label().'.');
    }

    public function bukaKembali(Penilaian $penilaian)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        $penilaian->update(['status' => 'DRAFT']);
        $this->audit($penilaian, 'RETURN', ['status' => 'DRAFT']);

        return back()->with('status', 'Penilaian dibuka kembali (DRAFT).');
    }

    /** Sebaran PSN eksisting di provinsi lokasi usulan (snapshot terbit terbaru). */
    protected function petaUsulan(UsulanPsn $usulan): array
    {
        $c = PeriodeCutoff::terbaru();
        $prov = DB::table('ref_wilayah')->where('level', 1)->get(['kode', 'nama', 'lat', 'lng'])->keyBy('kode');
        $hitung = collect();
        $sejenis = collect();
        if ($c) {
            DB::table('snapshot_psn')->where('periode_cutoff_id', $c->id)->where('is_aktif', true)->get(['provinsi_kode', 'klaster_id'])
                ->each(function ($r) use (&$hitung, &$sejenis, $usulan) {
                    foreach (json_decode($r->provinsi_kode ?? '[]', true) as $k) {
                        $hitung[$k] = ($hitung[$k] ?? 0) + 1;
                        if ($usulan->klaster_id && (int) $r->klaster_id === (int) $usulan->klaster_id) {
                            $sejenis[$k] = ($sejenis[$k] ?? 0) + 1;
                        }
                    }
                });
        }
        $lokasiUsulan = $usulan->lokasi->pluck('provinsi_kode')->unique()->values();

        return [
            'provinsi' => $prov->filter(fn ($p) => $p->lat !== null)->map(fn ($p) => [
                'kode' => $p->kode, 'label' => $p->nama, 'lat' => (float) $p->lat, 'lng' => (float) $p->lng,
                'jumlah' => $hitung[$p->kode] ?? 0, 'sejenis' => $sejenis[$p->kode] ?? 0, 'usulan' => $lokasiUsulan->contains($p->kode),
            ])->values()->all(),
            'lokasi_usulan' => $lokasiUsulan->map(fn ($k) => ['kode' => $k, 'label' => $prov[$k]->nama ?? $k, 'psn' => $hitung[$k] ?? 0, 'sejenis' => $sejenis[$k] ?? 0])->all(),
            'cutoff' => $c?->kode,
        ];
    }

    /** Nilai akhir usulan lain pada tahun RKP yang sama (penilaian terakhir). */
    protected function perbandingan(UsulanPsn $usulan): array
    {
        $terakhir = DB::table('penilaian')->whereNull('deleted_at')->selectRaw('usulan_id, MAX(id) AS id')->groupBy('usulan_id');

        return UsulanPsn::query()->where('tahun_rkp', $usulan->tahun_rkp)
            ->joinSub($terakhir, 'pt', 'pt.usulan_id', '=', 'usulan_psn.id')->join('penilaian as p', 'p.id', '=', 'pt.id')
            ->whereNotNull('p.nilai_akhir')->orderByDesc('p.nilai_akhir')->limit(15)
            ->get(['usulan_psn.id', 'usulan_psn.nama', 'p.nilai_akhir', 'p.rekomendasi'])
            ->map(fn ($r) => ['id' => $r->id, 'label' => $r->nama, 'nilai' => (float) $r->nilai_akhir, 'ini' => $r->id === $usulan->id, 'ditolak' => $r->rekomendasi === Rekomendasi::Ditolak->value])
            ->all();
    }

    protected function validasiUsulan(Request $r): array
    {
        $data = $r->validate([
            'nama' => ['required', 'string', 'max:500'],
            'tahun_rkp' => ['required', 'integer', 'min:2025', 'max:2100'],
            'klaster_id' => ['nullable', 'exists:ref_klaster,id'],
            'unit_kerja_id' => ['nullable', Rule::exists('ref_unit_kerja', 'id')->where('jenis', 'DIREKTORAT')],
            'pengusul_instansi_id' => ['nullable', 'exists:ref_instansi,id'],
            'pengusul_teks' => ['nullable', 'string', 'max:255'],
            'jenis_pengusul' => ['required', Rule::in(array_keys(UsulanPsn::JENIS_PENGUSUL))],
            'is_infrastruktur' => ['boolean'],
            'nilai_investasi_rp' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999999'],
            'provinsi' => ['array'],
            'provinsi.*' => [Rule::exists('ref_wilayah', 'kode')->where('level', 1)],
        ], [], [
            'nama' => 'nama usulan', 'tahun_rkp' => 'tahun RKP', 'jenis_pengusul' => 'jenis pengusul', 'unit_kerja_id' => 'direktorat pengampu',
        ]);
        $data['is_infrastruktur'] = $r->boolean('is_infrastruktur');

        // Pengguna dengan hak terbatas hanya dapat mengusulkan untuk direktorat/unitnya sendiri.
        if ($r->user()->ubahTerbatas()) {
            $data['unit_kerja_id'] = $r->user()->unit_kerja_id;
        }

        return $data;
    }

    protected function opsiForm(): array
    {
        return [
            'klaster' => DB::table('ref_klaster')->orderBy('urutan')->pluck('nama', 'id'),
            'direktorat' => DB::table('ref_unit_kerja')->where('jenis', 'DIREKTORAT')->orderBy('nama')->pluck('nama', 'id'),
            'instansi' => DB::table('ref_instansi')->orderBy('nama')->pluck('nama', 'id'),
            'provinsi' => DB::table('ref_wilayah')->where('level', 1)->orderBy('nama')->pluck('nama', 'kode'),
        ];
    }

    protected function audit(Penilaian $p, string $aksi, array $nilai): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username, 'tabel' => 'penilaian', 'record_id' => $p->id,
            'psn_id' => $p->auditPsnId(), 'aksi' => $aksi, 'nilai_baru' => $nilai]);
    }
}
