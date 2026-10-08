<?php

namespace App\Http\Controllers;

use App\Models\Psn;
use App\Models\PsnDokumen;
use App\Services\DashboardService;
use App\Services\ProyekService;
use App\Support\Dashboard\FilterGlobal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProyekController extends Controller
{
    public const TAB = [
        'profil' => 'Profil',
        'perencanaan' => 'Perencanaan',
        'kpro' => 'KP/RO',
        'progres' => 'Progres & Anggaran',
        'risiko' => 'Risiko & Isu',
        'regulasi' => 'Regulasi',
        'stakeholder' => 'Stakeholder',
        'dokumen' => 'Dokumen & Riwayat',
    ];

    /** Tab yang sudah tersedia pada Fase 3; lainnya placeholder. */
    public const TAB_TERSEDIA = ['profil', 'kpro', 'progres', 'dokumen'];

    public function index()
    {
        return view('proyek.index');
    }

    public function show(Request $r, Psn $psn, DashboardService $dashboard, ProyekService $proyek)
    {
        Gate::authorize('view', $psn);
        $tab = array_key_exists($r->query('tab'), self::TAB) ? $r->query('tab') : 'profil';
        $f = FilterGlobal::fromRequest($r);
        $c = $dashboard->cutoff($f);

        $data = match ($tab) {
            'profil' => $proyek->profil($psn),
            'kpro' => $proyek->kegiatan($psn, $c),
            'progres' => $proyek->progres($psn, $c),
            'dokumen' => [
                'dokumen' => PsnDokumen::where('psn_id', $psn->id)->with('kategori')->latest()->get(),
                'riwayat' => $proyek->jejakAudit($psn, 100),
            ],
            default => null,
        };

        return view('proyek.show', [
            'psn' => $psn,
            'header' => $proyek->header($psn, $c),
            'tab' => $tab,
            'data' => $data,
            // Filter global & opsi tabel diteruskan agar "Kembali" mempertahankan tampilan portofolio.
            'qsKembali' => http_build_query($r->except(['tab'])),
        ]);
    }
}
