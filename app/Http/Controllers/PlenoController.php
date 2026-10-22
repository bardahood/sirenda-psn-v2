<?php

namespace App\Http\Controllers;

use App\Models\Penilaian;
use App\Models\Pleno;
use App\Models\PlenoUsulan;
use App\Models\UsulanPsn;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Sidang pleno pembahasan hasil penilaian (langkah 5 alur usulan). Melihat: perencanaan.lihat;
 * menjadwalkan, menyusun agenda, dan mencatat keputusan: perencanaan.kelola (PMO, Super Admin).
 */
class PlenoController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', UsulanPsn::class);
        $pleno = Pleno::withCount(['agenda', 'agenda as diputus_count' => fn ($q) => $q->whereNotNull('keputusan')])
            ->orderByRaw("status = 'SELESAI'")->orderByDesc('tanggal')->paginate(20);

        return view('perencanaan.pleno.index', ['pleno' => $pleno]);
    }

    public function store(Request $r)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        $p = Pleno::create($this->validasi($r));

        return redirect()->route('perencanaan.pleno.show', $p)->with('status', 'Sidang pleno dijadwalkan. Tambahkan usulan yang akan dibahas.');
    }

    public function show(Pleno $pleno, ScoringService $scoring)
    {
        Gate::authorize('viewAny', UsulanPsn::class);
        // Agenda dibatasi cakupan pengguna (global scope unit kerja); usulan terhapus tetap tampil.
        $pleno->load([
            'agenda' => fn ($q) => $q->whereIn('usulan_id', UsulanPsn::query()->withTrashed()->select('id')),
            'agenda.usulan' => fn ($q) => $q->withoutGlobalScopes()->with(['klaster', 'psi']), 'agenda.penilaian',
        ]);

        // Kandidat agenda: usulan dengan penilaian FINAL yang belum ada di pleno ini.
        $sudah = $pleno->agenda()->pluck('usulan_id');
        $kandidat = UsulanPsn::query()->whereHas('penilaian', fn ($q) => $q->where('status', 'FINAL'))
            ->whereNotIn('id', $sudah)->when($pleno->tahun_rkp, fn ($q, $t) => $q->where('tahun_rkp', $t))
            ->with('penilaianTerakhir')->orderBy('nama')->get();

        return view('perencanaan.pleno.show', ['pleno' => $pleno, 'kandidat' => $kandidat, 'keputusan' => config('psn_dashboard.psi.keputusan_pleno')]);
    }

    public function update(Request $r, Pleno $pleno)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        $pleno->update($this->validasi($r));

        return back()->with('status', 'Data sidang pleno diperbarui.');
    }

    public function tambahAgenda(Request $r, Pleno $pleno)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        abort_if($pleno->isSelesai(), 409, 'Sidang pleno sudah selesai.');
        $data = $r->validate(['usulan_id' => ['required', 'array', 'min:1'], 'usulan_id.*' => ['integer', 'exists:usulan_psn,id']], [], ['usulan_id' => 'usulan']);

        $n = 0;
        DB::transaction(function () use ($data, $pleno, &$n) {
            $urutan = (int) $pleno->agenda()->max('urutan');
            foreach ($data['usulan_id'] as $id) {
                $final = Penilaian::where('usulan_id', $id)->where('status', 'FINAL')->latest('id')->first();
                if (! $final || $pleno->agenda()->where('usulan_id', $id)->exists()) {
                    continue;
                }
                PlenoUsulan::create(['pleno_id' => $pleno->id, 'usulan_id' => $id, 'penilaian_id' => $final->id, 'urutan' => ++$urutan]);
                $n++;
            }
        });

        return back()->with('status', $n ? "{$n} usulan ditambahkan ke agenda pleno." : 'Tidak ada usulan yang ditambahkan (penilaian harus FINAL dan belum ada di agenda).');
    }

    public function hapusAgenda(Pleno $pleno, PlenoUsulan $agenda)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        abort_unless($agenda->pleno_id === $pleno->id, 404);
        abort_if($pleno->isSelesai() || $agenda->keputusan, 409, 'Agenda yang sudah diputus tidak dapat dihapus.');
        $agenda->delete();

        return back()->with('status', 'Usulan dikeluarkan dari agenda.');
    }

    /** Catat keputusan & catatan pleno untuk satu atau beberapa usulan sekaligus. */
    public function keputusan(Request $r, Pleno $pleno)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        abort_if($pleno->isSelesai(), 409, 'Sidang pleno sudah selesai; keputusan terkunci.');
        $data = $r->validate([
            'agenda' => ['required', 'array'],
            'agenda.*.keputusan' => ['nullable', Rule::in(array_keys(config('psn_dashboard.psi.keputusan_pleno')))],
            'agenda.*.catatan' => ['nullable', 'string', 'max:5000', 'required_if:agenda.*.keputusan,DISETUJUI_CATATAN,DITUNDA,DITOLAK'],
            'agenda.*.tindak_lanjut' => ['nullable', 'string', 'max:5000'],
        ], ['agenda.*.catatan.required_if' => 'Catatan pleno wajib diisi untuk keputusan selain "Disetujui".']);

        $n = 0;
        foreach ($data['agenda'] as $id => $isi) {
            $a = $pleno->agenda()->whereKey($id)->first();
            if (! $a) {
                continue;
            }
            $a->fill(['keputusan' => $isi['keputusan'] ?? null, 'catatan' => $isi['catatan'] ?? null, 'tindak_lanjut' => $isi['tindak_lanjut'] ?? null]);
            if ($a->isDirty()) {
                $a->save();
                $n++;
            }
        }

        return back()->with('status', "Keputusan pleno tersimpan ({$n} usulan diperbarui).");
    }

    public function selesai(Pleno $pleno)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        $belum = $pleno->agenda()->whereNull('keputusan')->count();
        if ($belum || ! $pleno->agenda()->exists()) {
            return back()->withErrors(['pleno' => $belum ? "Masih ada {$belum} usulan tanpa keputusan." : 'Agenda pleno masih kosong.']);
        }
        $pleno->update(['status' => 'SELESAI']);

        return back()->with('status', 'Sidang pleno ditandai selesai; keputusan terkunci.');
    }

    protected function validasi(Request $r): array
    {
        return $r->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'tempat' => ['nullable', 'string', 'max:255'],
            'pimpinan' => ['nullable', 'string', 'max:255'],
            'tahun_rkp' => ['nullable', 'integer', 'min:2024', 'max:2100'],
            'catatan_umum' => ['nullable', 'string', 'max:10000'],
        ], [], ['nama' => 'nama sidang', 'tahun_rkp' => 'tahun RKP', 'pimpinan' => 'pimpinan sidang']);
    }
}
