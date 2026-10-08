<?php

namespace App\Http\Controllers;

use App\Models\Psn;
use App\Services\PengisianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Pengisian & verifikasi pemutakhiran PSN per cut-off.
 * Isi = PsnPolicy::update (detail.input + cakupan unit); verifikasi = PsnPolicy::verifikasi.
 * Risiko & isu hanya dapat diubah dengan izin risiko.input.
 */
class PengisianController extends Controller
{
    public function __construct(protected PengisianService $svc) {}

    public function index(Request $r)
    {
        $f = $r->validate([
            'periode' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'status' => ['nullable', Rule::in(array_keys(PengisianService::STATUS))],
            'q' => ['nullable', 'string', 'max:100'],
            'milik' => ['nullable', 'in:0,1'],
        ]);
        $u = $r->user();
        $p = $this->svc->periode($f['periode'] ?? null);
        // Peran terbatas default hanya melihat PSN unitnya; dapat diperluas (lihat saja) bila izin lihat mengizinkan.
        $f['milik'] = (bool) ($f['milik'] ?? ($u->ubahTerbatas() ? 1 : 0));

        return view('pengisian.index', $this->svc->daftar($p, $u, $f) + [
            'periode' => $p, 'filter' => $f, 'daftarPeriode' => $this->svc->daftarPeriode(), 'batas' => $this->svc->batasPengisian($p),
        ]);
    }

    public function show(Request $r, string $periode, Psn $psn)
    {
        Gate::authorize('view', $psn);
        $p = $this->svc->periode($periode);
        $d = $this->svc->formulir($p, $psn);
        $u = $r->user();

        return view('pengisian.show', $d + [
            'periode' => $p, 'psn' => $psn, 'batas' => $this->svc->batasPengisian($p),
            'bolehIsi' => $u->can('update', $psn) && $this->svc->dapatDiubah($p, $d['status']),
            'bolehRisiko' => $u->can('update', $psn) && $u->can('risiko.input') && $this->svc->dapatDiubah($p, $d['status']),
            'bolehVerifikasi' => $u->can('verifikasi', $psn) && ! $p->isTerbit() && $d['status'] === 'DIAJUKAN',
        ]);
    }

    public function simpan(Request $r, string $periode, Psn $psn)
    {
        Gate::authorize('update', $psn);
        $p = $this->svc->periode($periode);
        $cfg = config('psn_dashboard.pengisian');
        // Desimal format Indonesia (35,5) diterima untuk persentase.
        $r->merge(['ro' => collect($r->input('ro', []))->map(fn ($x) => is_array($x) ? collect($x)->map(fn ($v, $k) => str_ends_with($k, '_persen') && is_string($v) ? str_replace(',', '.', $v) : $v)->all() : $x)->all()]);
        $data = $r->validate([
            'ro' => ['array'],
            'ro.*.rencana_persen' => ['nullable', 'numeric', 'between:0,100'],
            'ro.*.realisasi_persen' => ['nullable', 'numeric', 'between:0,100'],
            'ro.*.realisasi_anggaran_rp' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'ro.*.permasalahan' => ['nullable', 'string', 'max:2000'],
            'bukti' => ['array'],
            'bukti.*' => ['file', 'max:'.$cfg['bukti_maks_kb'], 'mimes:'.implode(',', $cfg['bukti_ekstensi'])],
            'risiko' => ['array'],
            'risiko.*.kemungkinan_aktual' => ['nullable', 'integer', 'between:1,5', 'required_with:risiko.*.dampak_aktual'],
            'risiko.*.dampak_aktual' => ['nullable', 'integer', 'between:1,5', 'required_with:risiko.*.kemungkinan_aktual'],
            'risiko.*.status_perlakuan' => ['nullable', Rule::in(array_keys($cfg['status_perlakuan']))],
            'risiko.*.catatan' => ['nullable', 'string', 'max:2000'],
            'risiko_baru.uraian' => ['nullable', 'string', 'max:2000'],
            'risiko_baru.kategori_id' => ['nullable', Rule::exists('ref_kode', 'id')->where('tipe', 'RISK')],
            'risiko_baru.kemungkinan_harapan' => ['nullable', 'integer', 'between:1,5', 'required_with:risiko_baru.dampak_harapan'],
            'risiko_baru.dampak_harapan' => ['nullable', 'integer', 'between:1,5', 'required_with:risiko_baru.kemungkinan_harapan'],
            'risiko_baru.rencana_perlakuan' => ['nullable', 'string', 'max:2000'],
            'risiko_baru.penanggung_jawab' => ['nullable', 'string', 'max:500'],
            'isu' => ['array'],
            'isu.*.status' => ['nullable', Rule::in(array_keys($cfg['status_isu']))],
            'isu.*.pic_nama' => ['nullable', 'string', 'max:255'],
            'isu.*.tenggat' => ['nullable', 'date'],
            'isu.*.tindak_lanjut' => ['nullable', 'string', 'max:2000'],
            'isu_baru.uraian' => ['nullable', 'string', 'max:2000'],
            'isu_baru.kebutuhan_dukungan' => ['nullable', 'string', 'max:2000'],
            'isu_baru.pic_nama' => ['nullable', 'string', 'max:255'],
            'isu_baru.tenggat' => ['nullable', 'date'],
        ], [], [
            'ro.*.rencana_persen' => 'rencana (%)', 'ro.*.realisasi_persen' => 'realisasi (%)', 'ro.*.realisasi_anggaran_rp' => 'realisasi anggaran',
            'bukti.*' => 'bukti dukung', 'risiko.*.kemungkinan_aktual' => 'kemungkinan aktual', 'risiko.*.dampak_aktual' => 'dampak aktual',
            'risiko_baru.kemungkinan_harapan' => 'kemungkinan', 'risiko_baru.dampak_harapan' => 'dampak', 'isu.*.tenggat' => 'tenggat',
        ]);
        $data['bukti'] = $r->file('bukti', []);

        $n = $this->svc->simpan($p, $psn, $data, $r->user(), $r->user()->can('risiko.input'));

        if ($r->boolean('ajukan')) {
            $this->svc->ajukan($p, $psn, $r->user());

            return back()->with('status', 'Isian disimpan dan diajukan untuk verifikasi.');
        }

        return back()->with('status', sprintf('Draf tersimpan: %d KP/RO, %d risiko, %d isu diperbarui.', $n['ro'], $n['risiko'], $n['isu']));
    }

    public function verifikasi(Request $r, string $periode, Psn $psn)
    {
        Gate::authorize('verifikasi', $psn);
        $data = $r->validate(['keputusan' => ['required', Rule::in(['setuju', 'kembalikan'])], 'catatan' => ['nullable', 'string', 'max:2000']]);
        $p = $this->svc->periode($periode);
        $setuju = $data['keputusan'] === 'setuju';
        $this->svc->verifikasi($p, $psn, $r->user(), $setuju, $data['catatan'] ?? null);

        return back()->with('status', $setuju ? 'Isian diverifikasi.' : 'Isian dikembalikan kepada pengisi dengan catatan.');
    }
}
