<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Dashboard Monev PSN
|--------------------------------------------------------------------------
| Semua ambang, bobot, pemetaan kode, dan TTL cache disimpan di sini --
| jangan di-hardcode di service/controller. Definisi indikator lengkap ada di
| docs/kamus-indikator.md. Butir bertanda TODO menunggu keputusan pemilik produk
| (lihat docs/rancangan-aplikasi.md bagian "Keputusan terbuka").
*/

return [

    // Status progres proyek/RO. Deviasi = realisasi - rencana (poin persentase).
    'status_progres' => [
        'on_track_min_deviasi' => -5,    // deviasi > -5 pp  => ON_TRACK
        'terlambat_max_deviasi' => -20,  // deviasi < -20 pp => TERLAMBAT; di antaranya BERISIKO
        'tanpa_data_hari' => 35,         // tidak ada pembaruan > 35 hari dari cut-off => TANPA_DATA
    ],

    // Level risiko residual dari skor kemungkinan (1-5) x dampak (1-5).
    'risiko' => [
        'level' => [
            'Rendah' => [1, 4],
            'Sedang' => [5, 9],
            'Tinggi' => [10, 16],
            'Sangat Tinggi' => [17, 25],
        ],
        // Untuk risiko yang hanya punya label (data lama), label dipetakan ke
        // skor representatif agar dapat diurutkan & dihitung pada K4.
        'skor_dari_label' => [
            'Rendah' => 4,
            'Sedang' => 9,
            'Tinggi' => 16,
            'Sangat Tinggi' => 25,
        ],
        'kritis_min_level' => 'Tinggi',
    ],

    // K3: metode rata-rata progres fisik -- 'tertimbang' (bobot investasi) atau 'sederhana'.
    'k3_metode' => 'tertimbang',

    // Pemetaan kode STAT lama ke 4 tahap dashboard. Kode tidak terdaftar = tidak aktif.
    'tahap' => [
        '1' => 'OPERASI',     // Kumulatif Proyek Selesai
        '2' => 'OPERASI',     // Proyek Beroperasi Sebagian
        '3' => 'KONSTRUKSI',
        '4' => 'TRANSAKSI',
        '5' => 'PERENCANAAN', // Proyek Dalam Tahap Penyiapan
        // '6' Proyek Keluar dari PSN => tidak aktif, tidak dihitung pada K1
    ],

    // Pengelompokan sumber dana (master DANA) untuk P6.
    // TODO: master lama tidak memiliki kode KPBU -- konfirmasi pemetaan C/D.
    'skema_dana' => [
        'A' => 'APBN',
        'B' => 'APBD',
        'C' => 'LAINNYA', // BUMN
        'D' => 'KPBU',    // Badan Usaha/Swasta -- asumsi sementara
        'E' => 'LAINNYA',
    ],

    // Kategori PSN/PKPN: PSN dianggap PKPN bila klaster_pkpn terisi (asumsi, TODO konfirmasi).
    'kategori_pkpn_dari_klaster_pkpn' => true,

    // Validasi kewajaran nilai investasi (Rupiah penuh). Di luar rentang => investasi_anomali.
    'investasi' => [
        'min_rp' => 1_000_000,                   // < Rp1 juta: hampir pasti salah satuan
        'max_rp' => 2_000_000_000_000_000,       // > Rp2.000 triliun: hampir pasti salah satuan
        'kecualikan_anomali_dari_k2' => true,
    ],

    // P1: jumlah klaster teratas sebelum digabung menjadi "Lainnya".
    'p1_top_n' => 8,

    // Penilaian usulan PSN (Permen PPN/Bappenas No. 4/2025).
    'penilaian' => [
        'bobot' => [
            'PENDUKUNG' => 0.35,
            'KESIAPAN' => 0.35,
            'LOKASI' => 0.15,
            'TRISULA' => 0.15,
        ],
        'skor_maks_sub_kriteria' => 3,
        // TODO: ambang rekomendasi belum ditetapkan.
        'ambang' => [
            'direkomendasikan' => null,
            'dipertimbangkan' => null,
        ],
    ],

    // Kualitas data: field wajib per bagian profil. Kunci bagian = kode TYIT
    // pada ref_kode (item narasi) atau 'gambaran_umum' (kolom tabel psn).
    'field_wajib' => [
        'gambaran_umum' => ['nama', 'klaster_id', 'status_psn_id', 'deskripsi', 'output', 'nilai_investasi_rp', 'tahun_selesai'],
        'item_profil' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'],
        'relasi' => ['psn_lokasi', 'psn_sumber_dana', 'psn_unit_pengampu', 'kegiatan', 'risiko'],
    ],

    // Hak akses. Peran pada daftar ini hanya MELIHAT PSN yang diampu unit kerjanya
    // (L¹). Peran pada daftar ubah_terbatas hanya boleh input/verifikasi PSN
    // dalam cakupannya (I¹/V¹). Cakupan = psn_unit_pengampu.unit_kerja_id = users.unit_kerja_id.
    'rbac' => [
        'peran_lihat_terbatas' => ['Operator K/L'],
        'peran_ubah_terbatas' => ['Operator K/L', 'Direktorat Sektor'],
    ],

    // Snapshot per cut-off.
    'snapshot' => [
        // Bila KP/RO hanya punya target volume tahunan (tanpa % rencana), rencana
        // s.d. bulan cut-off diasumsikan linear: bulan/12 x 100%. (Keputusan Q-04)
        'rencana_linear_jika_kosong' => true,
        // Bobot agregasi progres KP/RO ke tingkat PSN: 'pagu' (fallback sederhana) atau 'sederhana'.
        'bobot_progres_kegiatan' => 'pagu',
        // Jadwal bulanan membuat snapshot DRAFT bulan lalu; terbitkan manual kecuali true.
        'terbit_otomatis' => false,
    ],

    // Peta sebaran. GeoJSON provinsi belum ditetapkan (Q-12): bila berkas berikut ada,
    // halaman /peta menampilkan choropleth (properti kode provinsi = 'kode_prop');
    // jika tidak, simbol lingkaran proporsional di titik tengah provinsi.
    'peta' => [
        'geojson' => 'geo/provinsi.geojson',
        'kode_prop' => 'kode',
        'tile_url' => env('PETA_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'tile_atribusi' => env('PETA_TILE_ATRIBUSI', '&copy; kontributor OpenStreetMap'),
    ],

    // TTL cache dalam detik. Cache per cut-off dibatalkan saat snapshot baru diterbitkan.
    'cache' => [
        'dashboard' => 86400,
        'portofolio' => 300,
        'kualitas_data' => 86400,
    ],
];
