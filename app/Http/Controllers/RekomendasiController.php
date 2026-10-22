<?php

namespace App\Http\Controllers;

use App\Enums\Rekomendasi;
use App\Models\UsulanPsn;
use App\Models\UsulanRekomendasi;
use App\Services\PsiHasilService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Rekomendasi resmi (langkah 6) dan pengiriman hasil ke PSI (langkah 7). Hak: perencanaan.kelola.
 */
class RekomendasiController extends Controller
{
    public const PILIHAN = [Rekomendasi::Direkomendasikan, Rekomendasi::Dipertimbangkan, Rekomendasi::TidakDirekomendasikan, Rekomendasi::Ditolak];

    public function simpan(Request $r, UsulanPsn $usulan)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        $data = $r->validate([
            'rekomendasi' => ['required', Rule::in(array_map(fn ($x) => $x->value, self::PILIHAN))],
            'catatan' => ['nullable', 'string', 'max:10000'],
            'nomor_dokumen' => ['nullable', 'string', 'max:150'],
            'tanggal' => ['required', 'date'],
            'tetapkan' => ['boolean'],
        ], [], ['nomor_dokumen' => 'nomor dokumen']);

        $final = $usulan->penilaian()->where('status', 'FINAL')->latest('id')->first();
        if (! $final) {
            return back()->withErrors(['rekomendasi' => 'Rekomendasi resmi memerlukan penilaian berstatus FINAL.']);
        }
        $pleno = $usulan->plenoUsulan()->whereNotNull('keputusan')->first();
        if (config('psn_dashboard.psi.wajib_pleno_sebelum_rekomendasi') && ! $pleno) {
            return back()->withErrors(['rekomendasi' => 'Keputusan pleno untuk usulan ini belum dicatat.']);
        }

        $tetapkan = $r->boolean('tetapkan');
        DB::transaction(function () use ($usulan, $data, $final, $pleno, $tetapkan) {
            // Satu draf aktif per usulan; penetapan baru menggantikan rekomendasi FINAL sebelumnya.
            $rek = $usulan->rekomendasiResmi()->where('status', 'DRAFT')->first() ?? new UsulanRekomendasi(['usulan_id' => $usulan->id]);
            $rek->fill(collect($data)->except('tetapkan')->all() + ['penilaian_id' => $final->id, 'pleno_usulan_id' => $pleno?->id]);
            if ($tetapkan) {
                $usulan->rekomendasiResmi()->where('status', 'FINAL')->get()->each->update(['status' => 'DIGANTI']);
                $rek->fill(['status' => 'FINAL', 'ditetapkan_oleh' => Auth::id(), 'ditetapkan_at' => now()]);
                // Hasil berubah: perlu dikirim (ulang) ke PSI.
                $usulan->psi?->update(['kirim_status' => 'BELUM']);
            }
            $rek->save();
        });

        return back()->with('status', $tetapkan ? 'Rekomendasi resmi ditetapkan FINAL.'.($usulan->psi ? ' Silakan kirim hasil ke PSI.' : '') : 'Draf rekomendasi tersimpan.');
    }

    public function kirim(UsulanPsn $usulan, PsiHasilService $hasil)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        try {
            $hasil->kirim($usulan->load('psi', 'rekomendasiFinal'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['kirim' => $e->getMessage()]);
        }

        return back()->with('status', 'Hasil penilaian, catatan pleno, dan rekomendasi berhasil dikirim ke aplikasi PSI.');
    }
}
