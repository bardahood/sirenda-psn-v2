<?php

namespace App\Support\Dashboard;

/**
 * Teks tooltip ⓘ (rumus & sumber) per kartu/panel. Sumber kebenaran definisi:
 * docs/kamus-indikator.md -- perbarui keduanya bersamaan.
 */
final class KamusIndikator
{
    public const DAFTAR = [
        'K1' => ['nama' => 'Total PSN', 'rumus' => 'Jumlah seluruh PSN tercatat sesuai filter, bersumber RKP maupun Permenko, termasuk yang berstatus Keluar dari PSN (rincian aktif/keluar ditampilkan di kartu). Indikator lain menghitung PSN aktif saja. Δ dalam % terhadap cut-off sebelumnya.', 'sumber' => 'Snapshot profil PSN'],
        'K2' => ['nama' => 'Total Investasi', 'rumus' => 'Σ nilai investasi PSN aktif, dalam Rp triliun. Nilai yang ditandai anomali (salah satuan) dikecualikan. Δ dalam %.', 'sumber' => 'Snapshot profil PSN (psn.nilai_investasi_rp)'],
        'K3' => ['nama' => 'Progres Fisik Rata-rata', 'rumus' => 'Σ(progres × investasi) ÷ Σ investasi atas PSN yang memiliki data progres (metode dapat diubah ke rata-rata sederhana). Δ dalam pp.', 'sumber' => 'Snapshot KP/RO (kegiatan_target)'],
        'K4' => ['nama' => 'Risiko Kritis', 'rumus' => 'Jumlah PSN berstatus Terlambat ATAU memiliki risiko residual ≥ Tinggi. Penurunan berarti membaik.', 'sumber' => 'Snapshot progres & register risiko'],
        'P1' => ['nama' => 'Distribusi Klaster', 'rumus' => 'Jumlah PSN aktif per klaster; 8 teratas, sisanya digabung "Lainnya". Klik batang untuk memfilter.', 'sumber' => 'Snapshot profil PSN'],
        'P2' => ['nama' => 'Progres Fisik vs Target', 'rumus' => 'Realisasi fisik tertimbang investasi dibanding rencana s.d. bulan cut-off.', 'sumber' => 'Snapshot KP/RO'],
        'P3' => ['nama' => 'Realisasi Anggaran', 'rumus' => 'Σ realisasi anggaran ÷ Σ pagu tahun berjalan. Realisasi per KP/RO = jumlah realisasi bulanan yang dilaporkan s.d. bulan cut-off; bila belum ada, nilai tahunan data lama.', 'sumber' => 'Snapshot KP/RO (pagu & realisasi)'],
        'P4' => ['nama' => 'RO Tercapai', 'rumus' => 'Jumlah KP/RO dengan realisasi ≥ rencana ÷ jumlah KP/RO bertarget tahun berjalan.', 'sumber' => 'Snapshot KP/RO'],
        'P5' => ['nama' => 'Tren Bulanan', 'rumus' => 'Rencana vs realisasi fisik kumulatif per cut-off terbit, Januari–Desember.', 'sumber' => 'Snapshot bulanan'],
        'P6' => ['nama' => 'Sumber Pendanaan', 'rumus' => 'Σ investasi per skema pendanaan. PSN dengan beberapa sumber tanpa rincian nilai dihitung penuh di tiap skema.', 'sumber' => 'Indikasi sumber pendanaan PSN'],
        'P7' => ['nama' => 'Sebaran Provinsi', 'rumus' => 'Jumlah PSN per provinsi. PSN multi-lokasi dihitung di setiap provinsinya, sehingga total dapat melebihi jumlah PSN.', 'sumber' => 'Lokasi PSN'],
        'P8' => ['nama' => 'Kontribusi Trisula', 'rumus' => 'Metodologi belum ditetapkan. Ditampilkan jumlah indikator Trisula per kategori sebagai gambaran sementara.', 'sumber' => 'Indikator Trisula PSN'],
        'RO_KRITIS' => ['nama' => 'RO Critical Path Berisiko', 'rumus' => '10 KP/RO critical path berstatus Berisiko atau Terlambat dengan deviasi (realisasi − rencana) terburuk.', 'sumber' => 'Snapshot KP/RO'],
        'TAHAPAN' => ['nama' => 'Tahapan Status', 'rumus' => 'Jumlah PSN aktif per tahap: Perencanaan, Transaksi, Konstruksi, Operasi/Selesai.', 'sumber' => 'Status PSN'],
        'TIMELINE_DP' => ['nama' => 'Klaster Direktif Presiden menuju 2029', 'rumus' => 'Jumlah PSN klaster Direktif Presiden menurut tahun target selesai ("Belum ada" = tahun selesai belum diisi).', 'sumber' => 'Profil PSN (tahun_selesai)'],
        'K5' => ['nama' => 'PKPN', 'rumus' => 'Jumlah PSN aktif berkategori PKPN (Program Kerja Prioritas Nasional) sesuai filter, beserta nilai investasinya dan porsinya terhadap total investasi PSN aktif. Δ dalam % terhadap cut-off sebelumnya.', 'sumber' => 'Snapshot profil PSN (kategori PKPN dari klaster PKPN di SIRENDA)'],
        'IKH_RKP' => ['nama' => 'Perjalanan Daftar PSN dalam RKP', 'rumus' => 'Jumlah PSN dalam daftar setiap penetapan RKP beserta perubahannya. Permenko 12/2024 sampai Perpres 117/2025 = angka dokumen resmi. Perpres 68/2026 = PSN bersumber RKP di SIRENDA (termasuk yang kini keluar). R-RKP 2027 = daftar tersebut dikurangi PSN keluar di SIRENDA dan indikasi Dikeluarkan di menu Evaluasi, ditambah usulan PSN baru berekomendasi final. Tidak mengikuti filter.', 'sumber' => 'Dokumen Perpres/Permenko, SIRENDA PEKS3, menu Evaluasi & Perencanaan'],
        'IKH_SKEMA' => ['nama' => 'Porsi Skema Pendanaan', 'rumus' => 'Σ nilai investasi PSN aktif per kelompok: Non-APBN (tanpa APBN/APBD), APBN & Non-APBN (campuran), APBN/APBD (hanya APBN dan/atau APBD). Setiap PSN tepat di satu kelompok sehingga jumlahnya = total investasi. Baris "Rincian nominal per sumber" = Σ nominal per sumber dana yang dirinci K/L di SIRENDA, dipisah APBN/APBD dan Non-APBN, hanya dari PSN yang Σ nominalnya tidak melebihi nilai investasi (toleransi 1%).', 'sumber' => 'Indikasi sumber pendanaan PSN (SIRENDA)'],
        'IKH_WILAYAH' => ['nama' => 'Sebaran menurut Wilayah', 'rumus' => 'Jumlah PSN aktif, PSN berkategori PKPN, dan Σ nilai investasi per wilayah. PSN yang lokasinya di lebih dari satu wilayah atau tanpa lokasi dihitung di Nasional & Lintas Pulau, sehingga jumlah seluruh wilayah = jumlah PSN.', 'sumber' => 'Lokasi PSN (SIRENDA)'],
        'IKH_SELESAI' => ['nama' => 'PSN Selesai menurut Kebutuhan Status', 'rumus' => 'PSN aktif berstatus Kumulatif Proyek Selesai dikelompokkan menurut isian K/L "Kebutuhan Status PSN" di SIRENDA: Tidak lagi memerlukan, Masih memerlukan, atau Perlu konfirmasi (belum diisi atau pernyataan tidak tegas). Bila isian SIRENDA kosong, dipakai isian D1 menu Evaluasi.', 'sumber' => 'SIRENDA PEKS3 (Kebutuhan Status PSN) & menu Evaluasi'],
        'IKH_STATUS' => ['nama' => 'Status Tahapan & Target Selesai', 'rumus' => 'Jumlah PSN aktif per status tahapan SIRENDA. Target selesai = tahun selesai PSN yang belum Kumulatif Selesai, dibanding batas RPJMN 2029 (s.d. 2027, 2028–2029, setelah 2029, belum diisi).', 'sumber' => 'Status & target tahun selesai PSN (SIRENDA)'],
        'STATUS_DATA' => ['nama' => 'Status Data', 'rumus' => 'Kelengkapan = field wajib terisi ÷ field wajib × 100. Belum terverifikasi = pengisian berstatus selain Diverifikasi.', 'sumber' => 'Snapshot kelengkapan & pengisian per cut-off'],
    ];
}
