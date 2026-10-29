<?php

namespace App\Http\Controllers;

use App\Models\UsulanDokumen;
use App\Models\UsulanPsn;
use App\Services\SifClient;
use App\Services\SifSinkronService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;

/** Tindakan pengguna terkait API SIF (Dit. PSI): ambil usulan dan unduh berkas usulan. */
class IntegrasiSifController extends Controller
{
    /** POST /perencanaan/sinkron-psi -- ambil usulan terbaru dari SIF. */
    public function sinkron(SifClient $sif, SifSinkronService $sinkron)
    {
        Gate::authorize('kelola', UsulanPsn::class);
        if ($k = $sif->kendala()) {
            return back()->withErrors(['sinkron' => $k]);
        }
        try {
            $h = $sinkron->sinkron();
        } catch (RuntimeException $e) {
            return back()->withErrors(['sinkron' => 'Gagal mengambil daftar usulan dari SIF: '.$e->getMessage()]);
        }
        $pesan = sprintf('Usulan dari aplikasi PSI: %d baru, %d diperbarui, %d tidak berubah.', $h['baru'], $h['diperbarui'], $h['tetap']);

        return $h['gagal']
            ? back()->with('status', $pesan)->withErrors(['sinkron' => "{$h['gagal']} usulan gagal diambil: ".implode('; ', array_slice($h['galat'], 0, 3))])
            : back()->with('status', $pesan);
    }

    /**
     * GET /perencanaan/{usulan}/dokumen/{dokumen} -- berkas di SIF dilindungi X-API-Key, jadi diunduh
     * oleh server SIRENDA lalu diteruskan ke pengguna yang berhak melihat usulan.
     */
    public function unduh(UsulanPsn $usulan, UsulanDokumen $dokumen, SifClient $sif)
    {
        Gate::authorize('view', $usulan);
        abort_unless($dokumen->usulan_id === $usulan->id && $dokumen->url, 404);
        if (! $sif->milikSif($dokumen->url)) {
            return redirect()->away($dokumen->url);
        }
        try {
            $r = $sif->unduh($dokumen->url);
        } catch (RuntimeException $e) {
            return back()->withErrors(['unduh' => $e->getMessage()]);
        }
        $nama = Str::of($dokumen->nama_berkas ?: 'berkas')->replaceMatches('/[^\w.\- ]+/u', '_')->limit(150, '');

        return response($r->body(), 200, [
            'Content-Type' => $r->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$nama.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
