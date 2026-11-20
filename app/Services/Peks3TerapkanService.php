<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PeriodeCutoff;
use App\Support\DashboardCache;
use App\Support\Legacy\LegacyImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menerapkan isi basis data cermin PEKS3 ke tabel dashboard secara atomik: dalam SATU transaksi
 * data hasil impor dihapus, LegacyImporter dijalankan ulang, dan snapshot semua cut-off dibangun ulang
 * (status TERBIT/DRAFT tidak berubah). Pembaca tetap melihat data lama sampai commit; cache dashboard
 * dibatalkan setelah commit. Usulan & penilaian (data milik dashboard) tidak disentuh.
 */
class Peks3TerapkanService
{
    public function perlu(): bool
    {
        $s = DB::table('integrasi_peks3_status')->find(1);
        if (! $s?->tertunda_sejak) {
            return false;
        }
        $cfg = config('psn_dashboard.peks3');

        return now()->diffInSeconds($s->perubahan_terakhir_at, true) >= $cfg['jeda_terapkan_detik']
            || now()->diffInSeconds($s->tertunda_sejak, true) >= $cfg['tunggu_maks_detik'];
    }

    /** @return array{status:string, durasi_detik?:float, psn?:int, peringatan?:int, pesan?:string} */
    public function terapkan(?\Closure $log = null): array
    {
        $kunci = Cache::lock('peks3:terapkan', 1800);
        if (! $kunci->get()) {
            return ['status' => 'SEDANG_BERJALAN', 'pesan' => 'Penerapan lain sedang berjalan.'];
        }

        $cermin = app(Peks3CerminService::class);
        $mulai = microtime(true);
        $batas = DB::table('integrasi_peks3_status')->where('id', 1)->value('perubahan_terakhir_at');
        try {
            $cermin->cermin(); // pastikan cermin siap sebelum menghapus apa pun

            $importer = app(LegacyImporter::class);
            $log && $importer->onLog($log);
            $laporan = DB::transaction(function () use ($importer, $log) {
                $importer->kosongkan(transaksional: true, pertahankanUsulan: true);
                // Langkah 'usulan' (daftar psn_2027 lama) dilewati: usulan & penilaiannya milik dashboard.
                $laporan = $importer->jalankan(array_values(array_diff(LegacyImporter::LANGKAH, ['usulan'])));
                // Isian menu Pengisian yang belum kembali dari PEKS3 dipulihkan sebelum snapshot dibangun (§9).
                $laporan['kiriman_balik'] = app(Peks3KeluarService::class)->pulihkan();

                $snapshot = app(SnapshotService::class);
                foreach (PeriodeCutoff::orderBy('kode')->get() as $p) {
                    $log && $log("== snapshot {$p->kode}");
                    $snapshot->buat($p->kode, $p->tanggal_cutoff->toDateString(), terbit: false, paksa: true);
                }

                return $laporan;
            });
            DashboardCache::flush();
            // Data SIRENDA (tahapan, Kebutuhan Status PSN) dapat mengubah kelengkapan & status otomatis evaluasi.
            try {
                app(EvaluasiStatusService::class)->selaraskan();
            } catch (Throwable $e) {
                report($e);
            }

            $hasil = ['status' => 'BERHASIL', 'durasi_detik' => round(microtime(true) - $mulai, 1),
                'psn' => DB::table('psn')->whereNull('deleted_at')->count(), 'peringatan' => array_sum(array_map('count', $laporan['peringatan'] ?? []))];
            $kini = now();
            // Kiriman yang masuk selama penerapan tetap tertunda untuk putaran berikutnya.
            $baruMasuk = DB::table('integrasi_peks3_status')->where('id', 1)->value('perubahan_terakhir_at') != $batas;
            DB::table('integrasi_peks3_status')->updateOrInsert(['id' => 1], ['tertunda_sejak' => $baruMasuk ? $kini : null, 'diterapkan_at' => $kini,
                'durasi_detik' => $hasil['durasi_detik'], 'hasil' => json_encode($hasil), 'galat' => null, 'updated_at' => $kini]);
            $cermin->log('TERAPKAN', null, 'BERHASIL', null, $hasil);
            AuditLog::create(['user_label' => 'sinkron-peks3', 'tabel' => 'integrasi_peks3', 'aksi' => 'UPDATE', 'sumber' => 'APLIKASI',
                'nilai_baru' => ['sinkron_peks3' => $hasil]]);

            return $hasil;
        } catch (Throwable $e) {
            $pesan = $e->getMessage();
            DB::table('integrasi_peks3_status')->updateOrInsert(['id' => 1], ['galat' => mb_substr($pesan, 0, 2000), 'updated_at' => now()]);
            $cermin->log('TERAPKAN', null, 'GAGAL', mb_substr($pesan, 0, 2000));
            report($e);

            return ['status' => 'GAGAL', 'pesan' => $pesan];
        } finally {
            $kunci->release();
        }
    }
}
