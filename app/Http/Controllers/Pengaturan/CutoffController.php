<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\PeriodeCutoff;
use App\Services\SnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Pengaturan > Cut-off & Snapshot: bangun, terbitkan, bangun ulang (Super Admin). */
class CutoffController extends Controller
{
    public function index()
    {
        $periode = PeriodeCutoff::orderByDesc('tanggal_cutoff')->get();
        $jumlah = DB::table('snapshot_psn')->groupBy('periode_cutoff_id')->selectRaw('periode_cutoff_id, COUNT(*) AS n')->pluck('n', 'periode_cutoff_id');
        $pengisian = DB::table('pengisian_psn')->groupBy('periode_cutoff_id', 'status')->selectRaw('periode_cutoff_id, status, COUNT(*) AS n')->get()->groupBy('periode_cutoff_id');

        return view('pengaturan.cutoff', compact('periode', 'jumlah', 'pengisian') + ['usulanKode' => now()->subMonthNoOverflow()->format('Y-m')]);
    }

    public function bangun(Request $r, SnapshotService $snapshot)
    {
        $data = $r->validate([
            'kode' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'tanggal' => ['nullable', 'date'],
            'terbit' => ['boolean'],
            'paksa' => ['boolean'],
        ], ['kode.regex' => 'Format periode harus YYYY-MM.']);

        try {
            $h = $snapshot->buat($data['kode'], $data['tanggal'] ?? null, $r->boolean('terbit'), $r->boolean('paksa'));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['kode' => $e->getMessage()]);
        }

        return back()->with('status', sprintf('Snapshot %s (%s) berstatus %s: %d PSN (%d aktif), %d KP/RO, %d risiko.',
            $h['cutoff'], $h['tanggal'], $h['status'], $h['psn'], $h['psn_aktif'], $h['kegiatan'], $h['risiko']));
    }
}
