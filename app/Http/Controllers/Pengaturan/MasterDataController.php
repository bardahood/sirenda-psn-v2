<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pengaturan > Master data klaster - sektor (sub klaster) - direktorat/unit kerja.
 * Satu sumber opsi filter global. Perubahan dicatat di audit & membatalkan cache opsi filter.
 */
class MasterDataController extends Controller
{
    public const JENIS_UNIT = ['DIREKTORAT' => 'Direktorat Bappenas', 'KL' => 'Kementerian/Lembaga', 'PEMDA' => 'Pemerintah daerah', 'BU' => 'BUMN/badan usaha', 'LAINNYA' => 'Lainnya'];

    public function index(Request $r)
    {
        $f = $r->validate(['jenis' => ['nullable', Rule::in(array_keys(self::JENIS_UNIT))], 'q' => ['nullable', 'string', 'max:100']]);

        return view('pengaturan.master', [
            'klaster' => DB::table('ref_klaster as k')->leftJoin('psn as p', fn ($j) => $j->on('p.klaster_id', '=', 'k.id')->whereNull('p.deleted_at'))
                ->groupBy('k.id', 'k.kode', 'k.nama', 'k.urutan', 'k.is_aktif')->orderBy('k.urutan')
                ->get(['k.id', 'k.kode', 'k.nama', 'k.urutan', 'k.is_aktif', DB::raw('COUNT(p.id) AS jumlah_psn')]),
            'subKlaster' => DB::table('ref_sub_klaster as s')->leftJoin('ref_klaster as k', 'k.id', '=', 's.klaster_id')->orderBy('s.kode')
                ->get(['s.id', 's.kode', 's.nama', 's.klaster_id', 'k.nama as klaster_nama']),
            'unit' => DB::table('ref_unit_kerja as u')->leftJoin('psn_unit_pengampu as pu', 'pu.unit_kerja_id', '=', 'u.id')
                ->when($f['jenis'] ?? null, fn ($q, $v) => $q->where('u.jenis', $v))
                ->when($f['q'] ?? null, fn ($q, $v) => $q->where('u.nama', 'like', "%{$v}%"))
                ->groupBy('u.id', 'u.kode', 'u.nama', 'u.jenis', 'u.is_aktif')->orderBy('u.jenis')->orderBy('u.nama')
                ->select(['u.id', 'u.kode', 'u.nama', 'u.jenis', 'u.is_aktif', DB::raw('COUNT(pu.id) AS jumlah_psn')])->paginate(30)->withQueryString(),
            'rekapJenis' => DB::table('ref_unit_kerja')->groupBy('jenis')->pluck(DB::raw('COUNT(*)'), 'jenis'),
            'filter' => $f,
            'klasterOpsi' => DB::table('ref_klaster')->orderBy('urutan')->pluck('nama', 'id'),
        ]);
    }

    public function ubahKlaster(Request $r, int $id)
    {
        $data = $r->validate(['nama' => ['required', 'string', 'max:150'], 'urutan' => ['required', 'integer', 'min:0', 'max:999'], 'is_aktif' => ['boolean']]);
        $data['is_aktif'] = $r->boolean('is_aktif');

        return $this->simpan('ref_klaster', $id, $data);
    }

    public function ubahSubKlaster(Request $r, int $id)
    {
        $data = $r->validate(['nama' => ['required', 'string', 'max:150'], 'klaster_id' => ['nullable', 'exists:ref_klaster,id']]);

        return $this->simpan('ref_sub_klaster', $id, $data);
    }

    public function ubahUnit(Request $r, int $id)
    {
        $data = $r->validate(['nama' => ['required', 'string', 'max:255'], 'jenis' => ['required', Rule::in(array_keys(self::JENIS_UNIT))], 'is_aktif' => ['boolean']]);
        $data['is_aktif'] = $r->boolean('is_aktif');

        return $this->simpan('ref_unit_kerja', $id, $data);
    }

    protected function simpan(string $tabel, int $id, array $data)
    {
        $lama = (array) (DB::table($tabel)->find($id) ?? abort(404));
        $beda = array_filter($data, fn ($v, $k) => (string) ($lama[$k] ?? '') !== (string) $v, ARRAY_FILTER_USE_BOTH);
        if ($beda) {
            DB::table($tabel)->where('id', $id)->update($beda);
            AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username, 'tabel' => $tabel, 'record_id' => $id,
                'aksi' => 'UPDATE', 'nilai_lama' => array_intersect_key($lama, $beda), 'nilai_baru' => $beda]);
            Cache::forget('psn_dashboard:filter-opsi');
        }

        return back()->with('status', $beda ? 'Perubahan tersimpan.' : 'Tidak ada perubahan.');
    }
}
