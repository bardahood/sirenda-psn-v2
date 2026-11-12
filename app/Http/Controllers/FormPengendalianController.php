<?php

namespace App\Http\Controllers;

use App\Models\PeriodeCutoff;
use App\Models\Psn;
use App\Services\FormPengendalianService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Cetak Form Pengendalian PSN per proyek (tampilan cetak HTML dan unduhan PDF). */
class FormPengendalianController extends Controller
{
    /** GET /proyek/{psn}/form-pengendalian[?periode=YYYY-MM&jenis=fisik|nonfisik&format=pdf] */
    public function show(Request $r, Psn $psn, FormPengendalianService $form)
    {
        Gate::authorize('view', $psn);
        $q = $r->validate([
            'periode' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'jenis' => ['nullable', 'in:fisik,nonfisik'],
            'format' => ['nullable', 'in:pdf'],
        ]);
        $c = isset($q['periode'])
            ? PeriodeCutoff::where('kode', $q['periode'])->where('status', 'TERBIT')->firstOrFail()
            : $form->cutoffBawaan();

        $data = $form->data($psn, $c) + [
            'psn' => $psn,
            'jenis' => $q['jenis'] ?? null,
            'pdf' => ($q['format'] ?? null) === 'pdf',
            'periodeOpsi' => PeriodeCutoff::where('status', 'TERBIT')->orderByDesc('tanggal_cutoff')->get(['kode', 'tanggal_cutoff']),
            'dicetak' => now(),
            'oleh' => $r->user()->name,
        ];

        if ($data['pdf']) {
            $kode = Str::of(Str::limit($psn->kode_psn ?: $psn->nama, 40, ''))->ascii()->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-')->lower();
            $nama = "form-pengendalian_{$kode}".($c ? "_{$c->kode}" : '').'.pdf';

            return Pdf::loadView('proyek.form-pengendalian', $data)->setPaper('a4', 'portrait')->download($nama);
        }

        return view('proyek.form-pengendalian', $data);
    }

    /**
     * GET /form-pengendalian/lama/{id} -- tautan dari aplikasi SIRENDA lama (sirenda-psn.id) memakai psn.id lama;
     * diteruskan ke PSN v2 yang hasil impornya menyimpan legacy_id tersebut.
     */
    public function dariIdLama(Request $r, int $id)
    {
        $psn = Psn::where('legacy_id', $id)->firstOrFail();

        return redirect()->route('proyek.form-pengendalian', ['psn' => $psn] + $r->only(['periode', 'jenis', 'format']));
    }
}
