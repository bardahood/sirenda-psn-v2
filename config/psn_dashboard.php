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
        'F' => 'LAINNYA', // Danantara -- muncul di dump 28 Okt 2026; disamakan dengan BUMN (Q-03)
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

    // Kelompok pulau untuk peta ringkas dashboard (prefiks kode provinsi Kemendagri), urutan barat -> timur.
    'pulau' => [
        'Sumatera' => ['11', '12', '13', '14', '15', '16', '17', '18', '19', '21'],
        'Jawa' => ['31', '32', '33', '34', '35', '36'],
        'Bali & Nusa Tenggara' => ['51', '52', '53'],
        'Kalimantan' => ['61', '62', '63', '64', '65'],
        'Sulawesi' => ['71', '72', '73', '74', '75', '76'],
        'Maluku' => ['81', '82'],
        'Papua' => ['91', '92', '93', '94', '95', '96'],
    ],

    // Kelas ukuran simbol provinsi pada peta ringkas dashboard (jumlah PSN, batas bawah inklusif).
    'peta_kelas' => [
        ['min' => 51, 'label' => '> 50 PSN'],
        ['min' => 21, 'label' => '21 – 50 PSN'],
        ['min' => 6, 'label' => '6 – 20 PSN'],
        ['min' => 1, 'label' => '1 – 5 PSN'],
    ],

    // Jumlah baris panel ringkas dashboard.
    'dashboard_top' => ['direktorat' => 5, 'dp_proyek' => 6],

    // Integrasi usulan PSN dengan aplikasi permohonan Dit. PSI (Fase 8). Lihat docs/integrasi-psi.md.
    // ETL dari SIRENDA lama (legacy:import).
    'legacy' => [
        // Koreksi tahun realisasi bulanan KP/RO (psn_kegiatan_detil). Aplikasi lama menyimpan seluruh baris
        // dengan tahun 2029 (dump 7 & 28 Okt 2026), padahal isiannya realisasi 2026 (dibuat Sep-Okt 2026,
        // terisi s.d. bulan berjalan). Setiap baris yang dikoreksi dicatat di laporan ETL. Kosongkan [] untuk mematikan.
        'koreksi_tahun_kegiatan_detil' => [2029 => 2026],
    ],

    // Filter "Kementerian/Lembaga": unit pengampu berjenis berikut dikelompokkan ke kementerian/lembaga induknya.
    // Nama induk diturunkan dari nama unit (bagian setelah koma terakhir bila berupa nama K/L; "Menteri X" -> "Kementerian X";
    // awalan "Kepala " dibuang), lalu disatukan dengan aturan regex di bawah (dicek pada nama unit, lalu nama turunan).
    'kementerian' => [
        'jenis_unit' => ['KL'],
        'aturan' => [
            '/^B?BWS\b/i' => 'Kementerian Pekerjaan Umum',
            '/\bBP BUMN\b|^Badan Pengaturan BUMN/i' => 'Badan Pengaturan BUMN',
            '/^Kement(erian|eiran) Investasi dan Hilirisasi/i' => 'Kementerian Investasi dan Hilirisasi/BKPM',
            '/^Kementerian Koordinator Bidang Perekonomian/i' => 'Kementerian Koordinator Bidang Perekonomian',
            '/^Kementerian Lingkungan Hidup/i' => 'Kementerian Lingkungan Hidup',
            '/^Kemendagri$/i' => 'Kementerian Dalam Negeri',
        ],
    ],
    'psi' => [
        // Autentikasi utama: JWT RS256 ditandatangani kunci privat klien PSI, diverifikasi dengan kunci publiknya.
        // PSI_KUNCI_PUBLIK = path berkas PEM (relatif terhadap root proyek atau absolut), mis. storage/app/private/kunci/psi-public.pem.
        'client_id' => env('PSI_CLIENT_ID'),
        'kunci_publik' => env('PSI_KUNCI_PUBLIK'),
        'jwt_audiens' => env('PSI_JWT_AUDIENS'),   // bila diisi dan token membawa "aud", nilainya harus sama
        'jwt_umur_maks_detik' => 300,              // exp - iat maksimum
        'jwt_toleransi_detik' => 60,               // toleransi selisih jam server
        // Alternatif/transisi: token statis (Authorization: Bearer ...). Keduanya kosong = API masuk nonaktif (503).
        'token_masuk' => env('PSI_TOKEN_MASUK'),
        // Pembatasan alamat IP pemanggil (pisahkan dengan koma); kosong = tidak dibatasi.
        'ip_diizinkan' => array_filter(array_map('trim', explode(',', (string) env('PSI_IP_DIIZINKAN', '')))),
        // Endpoint PSI untuk menerima hasil: {url_api}/usulan/{psi_id}/hasil-penilaian. Kosong = PSI mengambil sendiri (GET hasil).
        'url_api' => env('PSI_API_URL'),
        'token_keluar' => env('PSI_API_TOKEN'),
        'timeout_detik' => 20,
        // Rekomendasi resmi hanya dapat ditetapkan setelah keputusan pleno dicatat.
        'wajib_pleno_sebelum_rekomendasi' => true,
        // Pemetaan "Jenis Instansi" PSI -> usulan_psn.jenis_pengusul (pencocokan teks tanpa beda huruf besar/kecil).
        'jenis_instansi' => [
            'kementerian/lembaga' => 'KL', 'kementerian' => 'KL', 'lembaga' => 'KL', 'kl' => 'KL',
            'pemerintah daerah' => 'PEMDA', 'pemda' => 'PEMDA', 'provinsi' => 'PEMDA', 'kabupaten/kota' => 'PEMDA',
            'bumn' => 'BUMN_SWASTA', 'bumd' => 'BUMN_SWASTA', 'badan usaha' => 'BUMN_SWASTA', 'swasta' => 'BUMN_SWASTA', 'bumn/swasta' => 'BUMN_SWASTA',
        ],
        // "Jenis PSN" PSI yang dianggap infrastruktur (sub-kriteria KK3-KK4 berlaku).
        'jenis_psn_infrastruktur' => ['infrastruktur', 'psn infrastruktur'],
        'keputusan_pleno' => [
            'DISETUJUI' => 'Disetujui untuk direkomendasikan',
            'DISETUJUI_CATATAN' => 'Disetujui dengan catatan',
            'DITUNDA' => 'Ditunda / perlu kelengkapan',
            'DITOLAK' => 'Tidak disetujui',
        ],
    ],

    // Keamanan HTTP. CSP dapat dimatikan sementara (CSP_AKTIF=false) bila mengganggu integrasi.
    'keamanan' => [
        'csp' => (bool) env('CSP_AKTIF', true),
    ],

    // Pengisian & verifikasi pemutakhiran per PSN per cut-off (Fase 7).
    'pengisian' => [
        // Batas pengisian default = tanggal cut-off + N hari bila periode_cutoff.batas_pengisian kosong.
        'batas_hari_setelah_cutoff' => 10,
        // Pengajuan ditolak bila ada KP/RO bertarget tahun berjalan tanpa realisasi % periode ini.
        'wajib_realisasi_semua_ro' => true,
        // Bukti dukung: disimpan di disk 'public' folder bukti/{psn_id}; ukuran maksimum (KB) & ekstensi.
        'bukti_maks_kb' => 10240,
        'bukti_ekstensi' => ['pdf', 'jpg', 'jpeg', 'png'],
        'status_perlakuan' => ['BELUM' => 'Belum dilaksanakan', 'BERJALAN' => 'Sedang berjalan', 'SELESAI' => 'Selesai'],
        'status_isu' => ['TERBUKA' => 'Terbuka', 'PROSES' => 'Dalam proses', 'SELESAI' => 'Selesai'],
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
