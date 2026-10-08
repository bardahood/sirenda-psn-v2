<?php

namespace App\Support\Dashboard;

/**
 * Teks tooltip ⓘ (rumus & sumber) per kartu/panel. Sumber kebenaran definisi:
 * docs/kamus-indikator.md -- perbarui keduanya bersamaan.
 */
final class KamusIndikator
{
    public const DAFTAR = [
        'K1' => ['nama' => 'Total PSN', 'rumus' => 'Jumlah PSN aktif sesuai filter (tidak termasuk status Keluar dari PSN). Δ dalam % terhadap cut-off sebelumnya.', 'sumber' => 'Snapshot profil PSN'],
        'K2' => ['nama' => 'Total Investasi', 'rumus' => 'Σ nilai investasi PSN aktif, dalam Rp triliun. Nilai yang ditandai anomali (salah satuan) dikecualikan. Δ dalam %.', 'sumber' => 'Snapshot profil PSN (psn.nilai_investasi_rp)'],
        'K3' => ['nama' => 'Progres Fisik Rata-rata', 'rumus' => 'Σ(progres × investasi) ÷ Σ investasi atas PSN yang memiliki data progres (metode dapat diubah ke rata-rata sederhana). Δ dalam pp.', 'sumber' => 'Snapshot KP/RO (kegiatan_target)'],
        'K4' => ['nama' => 'Risiko Kritis', 'rumus' => 'Jumlah PSN berstatus Terlambat ATAU memiliki risiko residual ≥ Tinggi. Penurunan berarti membaik.', 'sumber' => 'Snapshot progres & register risiko'],
        'P1' => ['nama' => 'Distribusi Klaster', 'rumus' => 'Jumlah PSN aktif per klaster; 8 teratas, sisanya digabung "Lainnya". Klik batang untuk memfilter.', 'sumber' => 'Snapshot profil PSN'],
        'P2' => ['nama' => 'Progres Fisik vs Target', 'rumus' => 'Realisasi fisik tertimbang investasi dibanding rencana s.d. bulan cut-off.', 'sumber' => 'Snapshot KP/RO'],
        'P3' => ['nama' => 'Realisasi Anggaran', 'rumus' => 'Σ realisasi anggaran ÷ Σ pagu tahun berjalan.', 'sumber' => 'Snapshot KP/RO (pagu & realisasi)'],
        'P4' => ['nama' => 'RO Tercapai', 'rumus' => 'Jumlah KP/RO dengan realisasi ≥ rencana ÷ jumlah KP/RO bertarget tahun berjalan.', 'sumber' => 'Snapshot KP/RO'],
        'P5' => ['nama' => 'Tren Bulanan', 'rumus' => 'Rencana vs realisasi fisik kumulatif per cut-off terbit, Januari–Desember.', 'sumber' => 'Snapshot bulanan'],
        'P6' => ['nama' => 'Sumber Pendanaan', 'rumus' => 'Σ investasi per skema pendanaan. PSN dengan beberapa sumber tanpa rincian nilai dihitung penuh di tiap skema.', 'sumber' => 'Indikasi sumber pendanaan PSN'],
        'P7' => ['nama' => 'Sebaran Provinsi', 'rumus' => 'Jumlah PSN per provinsi. PSN multi-lokasi dihitung di setiap provinsinya, sehingga total dapat melebihi jumlah PSN.', 'sumber' => 'Lokasi PSN'],
        'P8' => ['nama' => 'Kontribusi Trisula', 'rumus' => 'Metodologi belum ditetapkan. Ditampilkan jumlah indikator Trisula per kategori sebagai gambaran sementara.', 'sumber' => 'Indikator Trisula PSN'],
        'RO_KRITIS' => ['nama' => 'RO Critical Path Berisiko', 'rumus' => '10 KP/RO critical path berstatus Berisiko atau Terlambat dengan deviasi (realisasi − rencana) terburuk.', 'sumber' => 'Snapshot KP/RO'],
        'TAHAPAN' => ['nama' => 'Tahapan Status', 'rumus' => 'Jumlah PSN aktif per tahap: Perencanaan, Transaksi, Konstruksi, Operasi/Selesai.', 'sumber' => 'Status PSN'],
        'TIMELINE_DP' => ['nama' => 'Klaster Direktif Presiden menuju 2029', 'rumus' => 'Jumlah PSN klaster Direktif Presiden menurut tahun target selesai ("Belum ada" = tahun selesai belum diisi).', 'sumber' => 'Profil PSN (tahun_selesai)'],
        'STATUS_DATA' => ['nama' => 'Status Data', 'rumus' => 'Kelengkapan = field wajib terisi ÷ field wajib × 100. Belum terverifikasi = pengisian berstatus selain Diverifikasi.', 'sumber' => 'Snapshot kelengkapan & pengisian per cut-off'],
    ];
}
