<?php

namespace App\Console\Commands;

use App\Models\Pleno;
use App\Models\UsulanPsi;
use App\Models\UsulanPsn;
use App\Models\UsulanSurat;
use App\Services\PsiImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi usulan CONTOH seakan dikirim aplikasi PSI (melalui layanan impor yang sama dengan API),
 * untuk uji tampilan & pelatihan. Semua psi_id berawalan "CONTOH-" dan dapat dihapus dengan --hapus.
 */
class PsiContoh extends Command
{
    protected $signature = 'psi:contoh {--hapus : Hapus seluruh usulan CONTOH-}';

    protected $description = 'Mengisi/menghapus usulan contoh dari aplikasi PSI (berbasis docs/contoh/psi-usulan.json)';

    public function handle(PsiImportService $impor): int
    {
        if ($this->option('hapus')) {
            $ids = UsulanPsi::withTrashed()->where('psi_id', 'like', 'CONTOH-%')->pluck('usulan_id');
            DB::transaction(function () use ($ids) {
                UsulanPsn::withoutGlobalScopes()->withTrashed()->whereIn('id', $ids)->forceDelete();
                UsulanSurat::withTrashed()->where('psi_surat_id', 'like', 'CONTOH-%')->forceDelete();
                Pleno::withTrashed()->where('nama', 'like', 'CONTOH%')->forceDelete();
            });
            $this->info("{$ids->count()} usulan contoh (beserta pleno CONTOH) dihapus.");

            return self::SUCCESS;
        }

        $dasar = json_decode(file_get_contents(base_path('docs/contoh/psi-usulan.json')), true);
        $variasi = [
            ['CONTOH-USL-2026-0001', 'CONTOH — Pembangunan Jalan Tol Lingkar Kota A', 'Konektivitas dan Infrastruktur Logistik Jalan', 'Kementerian/Lembaga', '32', 12.5e12],
            ['CONTOH-USL-2026-0002', 'CONTOH — Bendungan Serbaguna Sungai B', 'Swasembada Air', 'Kementerian/Lembaga', '33', 4.2e12],
            ['CONTOH-USL-2026-0003', 'CONTOH — Kawasan Industri Terpadu C', 'Industrialisasi', 'Pemerintah Daerah', '64', 8.9e12],
            ['CONTOH-USL-2026-0004', 'CONTOH — Pelabuhan Hub Logistik D', 'Klaster Yang Tidak Ada', 'Badan Hukum Lain', '99', 15e12],
        ];
        foreach ($variasi as [$id, $nama, $klaster, $jenis, $prov, $nilai]) {
            $p = $dasar;
            $p['psi_id'] = $id;
            $p['data_umum']['nama'] = $nama;
            $p['data_umum']['klaster'] = $klaster;
            $p['data_umum']['lokasi'] = [['provinsi_kode' => $prov]];
            $p['data_umum']['nilai_investasi_rp'] = $nilai;
            $p['pengusul']['jenis_instansi'] = $jenis;
            $h = $impor->terima($p, '127.0.0.1');
            $this->line(sprintf('%-22s %-12s %s%s', $id, $h['baru'] ? 'DITERIMA' : ($h['diabaikan'] ? 'DIABAIKAN' : 'DIPERBARUI'), $nama,
                $h['peringatan'] ? ' — '.count($h['peringatan']).' peringatan' : ''));
        }

        return self::SUCCESS;
    }
}
