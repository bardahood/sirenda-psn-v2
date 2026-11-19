<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Peks3Keluar;
use App\Services\Peks3CerminService;
use App\Services\Peks3KeluarService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pengaturan > Integrasi Data: pemantauan sinkron SIRENDA PEKS3 -> dashboard (dan ringkas usulan SIF/PSI)
 * tanpa membuka basis data. Kunci rahasia tidak pernah ditampilkan, hanya status terpasang/tidak.
 */
class IntegrasiController extends Controller
{
    public const JENIS = ['PSN' => 'Paket PSN', 'REFERENSI' => 'Referensi', 'REKONSILIASI' => 'Rekonsiliasi', 'TERAPKAN' => 'Penerapan', 'KOLOM' => 'Kolom baru'];

    public const HASIL = ['DITERIMA' => 'Diterima', 'TETAP' => 'Tidak berubah', 'VERSI_LAMA' => 'Versi lama', 'BERHASIL' => 'Berhasil', 'GAGAL' => 'Gagal atau ditolak'];

    public function index(Request $r, Peks3CerminService $cermin)
    {
        $cfg = config('psn_dashboard.peks3');
        $status = $cermin->status();

        $cerminSiap = true;
        $cerminPesan = null;
        try {
            $cermin->cermin();
        } catch (Throwable $e) {
            $cerminSiap = false;
            $cerminPesan = $e->getMessage();
        }

        $detak = Cache::get('peks3:detak');
        $kirimTerakhir = DB::table('integrasi_peks3_log')->where('jenis', '!=', 'TERAPKAN')->max('created_at');
        $rekap = DB::table('integrasi_peks3_log')->where('created_at', '>=', now()->subDay())
            ->groupBy('hasil')->selectRaw('hasil, COUNT(*) AS n')->pluck('n', 'hasil');
        $terapkanTerakhir = DB::table('integrasi_peks3_log')->where('jenis', 'TERAPKAN')->orderByDesc('id')->first();

        $jenis = array_key_exists($r->query('jenis'), self::JENIS) ? $r->query('jenis') : null;
        $hasil = array_key_exists($r->query('hasil'), self::HASIL) ? $r->query('hasil') : null;
        $log = DB::table('integrasi_peks3_log')
            ->when($jenis, fn ($q) => $q->where('jenis', $jenis))
            ->when($hasil, fn ($q) => $q->where('hasil', $hasil))
            ->when($r->filled('psn'), fn ($q) => $q->where('legacy_psn_id', (int) $r->query('psn')))
            ->orderByDesc('id')->paginate(50)->withQueryString();

        $psi = Schema::hasTable('usulan_psi') ? [
            'otomatis' => (bool) config('psn_dashboard.psi.sif.sinkron_otomatis'),
            'url_terpasang' => filled(config('psn_dashboard.psi.sif.url')),
            'usulan' => DB::table('usulan_psi')->count(),
            'terakhir' => DB::table('usulan_psi')->max('diperbarui_at'),
        ] : null;

        return view('pengaturan.integrasi', [
            'aktif' => (bool) $cfg['aktif'],
            'kunciTerpasang' => strlen((string) $cfg['rahasia']) >= 32,
            'ipDiizinkan' => $cfg['ip_diizinkan'],
            'cerminSiap' => $cerminSiap,
            'cerminPesan' => $cerminPesan,
            'detak' => $detak ? Carbon::parse($detak) : null,
            'status' => $status,
            'kirimTerakhir' => $kirimTerakhir,
            'rekap' => $rekap,
            'terapkanTerakhir' => $terapkanTerakhir,
            'log' => $log,
            'filter' => ['jenis' => $jenis, 'hasil' => $hasil, 'psn' => $r->query('psn')],
            'psi' => $psi,
            'jeda' => $cfg['jeda_terapkan_detik'],
            'urlApi' => url('/api/integrasi/peks3/v1'),
            'balik' => $this->balik(),
        ]);
    }

    /** Ringkasan antrean kiriman balik Dashboard -> PEKS3 (§9) dan operasi yang perlu keputusan admin. */
    protected function balik(): ?array
    {
        if (! Schema::hasTable('peks3_keluar')) {
            return null;
        }
        $cfg = config('psn_dashboard.peks3.balik');

        return [
            'aktif' => (bool) $cfg['aktif'], 'url_terpasang' => filled($cfg['url']),
            'rekap' => Peks3Keluar::groupBy('status')->selectRaw('status, COUNT(*) AS n')->pluck('n', 'status'),
            'terakhir' => Peks3Keluar::max('dikirim_at'),
            'perlu' => Peks3Keluar::with(['user:id,username', 'psn:id,nama'])->whereIn('status', ['KONFLIK', 'DITOLAK', 'GAGAL'])->orderByDesc('id')->limit(50)->get(),
        ];
    }

    /** Kirim ulang operasi kiriman balik; timpa=1 mengabaikan pemeriksaan konflik (isian dashboard menimpa PEKS3). */
    public function balikUlang(Request $r, Peks3Keluar $op, Peks3KeluarService $layanan)
    {
        abort_unless(in_array($op->status, ['KONFLIK', 'DITOLAK', 'GAGAL'], true), 422, 'Operasi ini tidak sedang menunggu keputusan.');
        $timpa = $r->boolean('timpa');
        $layanan->kirimUlang($op, $timpa);
        $this->catatBalik($op, $timpa ? 'kirim_ulang_timpa' : 'kirim_ulang');

        return back()->with('status', 'Perubahan dijadwalkan untuk dikirim ulang ke SIRENDA PEKS3.');
    }

    /** Ikuti data PEKS3: isian dashboard untuk operasi ini tidak dikirim dan tidak dipulihkan lagi. */
    public function balikAbaikan(Peks3Keluar $op, Peks3KeluarService $layanan)
    {
        abort_unless(in_array($op->status, ['KONFLIK', 'DITOLAK', 'GAGAL'], true), 422, 'Operasi ini tidak sedang menunggu keputusan.');
        $layanan->abaikan($op);
        $this->catatBalik($op, 'abaikan');

        return back()->with('status', 'Isian dashboard diabaikan; data SIRENDA PEKS3 dipakai pada sinkron berikutnya.');
    }

    protected function catatBalik(Peks3Keluar $op, string $aksi): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username, 'tabel' => 'peks3_keluar', 'record_id' => $op->id, 'psn_id' => $op->psn_id,
            'aksi' => 'UPDATE', 'sumber' => 'APLIKASI', 'nilai_baru' => ['kiriman_balik' => $aksi]]);
    }

    /**
     * Minta penerapan pada putaran penjadwal berikutnya (tanpa menjalankan proses panjang di permintaan web,
     * agar tidak terputus batas waktu PHP di hosting).
     */
    public function terapkan()
    {
        abort_unless(config('psn_dashboard.peks3.aktif'), 422, 'Sinkron PEKS3 belum aktif (PEKS3_AKTIF=false).');

        $kini = now();
        $lewat = $kini->copy()->subSeconds(config('psn_dashboard.peks3.jeda_terapkan_detik') + 1);
        DB::table('integrasi_peks3_status')->updateOrInsert(['id' => 1], [
            'tertunda_sejak' => DB::table('integrasi_peks3_status')->where('id', 1)->value('tertunda_sejak') ?? $kini,
            'perubahan_terakhir_at' => $lewat,
            'updated_at' => $kini,
        ]);
        AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username, 'tabel' => 'integrasi_peks3', 'aksi' => 'UPDATE',
            'sumber' => 'APLIKASI', 'nilai_baru' => ['minta_terapkan' => $kini->toDateTimeString()]]);

        return back()->with('status', 'Penerapan dijadwalkan. Data dashboard diperbarui dalam satu sampai dua menit, setelah penjadwal berikutnya berjalan.');
    }
}
