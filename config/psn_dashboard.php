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
    // Sumber penetapan PSN dari psn.sumber_input (kolom `psn.sumber` aplikasi lama: "1=rkp,2=permenko").
    // Dimensi filter global `sumber` dan kolom snapshot_psn.sumber.
    'sumber_psn' => [1 => 'RKP', 2 => 'PERMENKO'],
    'label_sumber_psn' => ['RKP' => 'RKP', 'PERMENKO' => 'Permenko'],

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
    // Form Pengendalian PSN (cetak per PSN; /proyek/{id}/form-pengendalian). Isian terisi dari data SIRENDA,
    // sisanya dilengkapi PIC. Teks batas pengembalian diubah per putaran evaluasi.
    'form_pengendalian' => [
        'batas_kembali' => env('FORM_PENGENDALIAN_BATAS', 'Selasa, 17 November 2026'),
        // Templat Word resmi untuk unduhan .docx terisi (FormPengendalianWord). Ganti berkas ini tiap putaran form baru.
        'templat_word' => resource_path('templates/form-pengendalian-psn.docx'),
        // ref_status_psn.kode -> tahapan butir B1.
        'tahapan' => ['5' => 'PENYIAPAN', '4' => 'TRANSAKSI', '3' => 'KONSTRUKSI', '2' => 'BEROPERASI_SEBAGIAN', '1' => 'KUMULATIF_SELESAI'],
        'maks_isu' => 10,          // isu terbuka yang dicetak di C2 (jalur kritis dulu, lalu tenggat terdekat)
        'baris_isu_kosong' => 2,   // baris kosong tambahan di C2 untuk diisi PIC
        'baris_lampiran' => 8,
    ],

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
        // API SIF (Portal PSN dan KPBU, Dit. PSI): SIRENDA (PMO) MENGAMBIL usulan dan MENGIRIM hasil penilaian.
        // Spesifikasi: "Dokumen Integrasi API Usulan PSN untuk PMO" v1.3 (13 Okt 2026). Lihat docs/integrasi-psi.md.
        'sif' => [
            'url' => env('SIF_BASE_URL'),          // https://<host-sif>/api/v1/external/usulan-psn
            'api_key' => env('SIF_API_KEY'),       // header X-API-Key
            'client_id' => env('SIF_CLIENT_ID'),   // header X-Client-Id
            // Kunci publik RSA untuk membungkus kunci sesi (X-Session-Key). Kunci privat TIDAK diperlukan SIRENDA.
            'kunci_publik' => env('SIF_KUNCI_PUBLIK', 'storage/app/private/kunci/sif-public.pem'),
            'timeout_detik' => 30,
            'per_halaman' => 100,                  // maks. 100 menurut SIF
            // Tahun RKP usulan (SIF tidak mengirimkannya). Kosong = tahun submitted_at + 1.
            'tahun_rkp' => env('SIF_TAHUN_RKP'),
            // Ambil usulan otomatis setiap jam (scheduler). Bisa juga manual: php artisan psi:sinkron.
            'sinkron_otomatis' => (bool) env('SIF_SINKRON_OTOMATIS', false),
            // Jenis lampiran SIF -> jenis dokumen SIRENDA + judul.
            'jenis_lampiran' => [
                'surat_persetujuan_menteri' => ['SURAT_PENGUSULAN', 'Surat Persetujuan Menteri'],
                'surat_rekomendasi' => ['SURAT_PENGUSULAN', 'Surat Rekomendasi'],
                'studi_kelayakan' => ['LAMPIRAN', 'Studi Kelayakan'],
                'prastudi_kelayakan' => ['LAMPIRAN', 'Prastudi Kelayakan'],
                'amdal' => ['LAMPIRAN', 'AMDAL'],
                'ded' => ['LAMPIRAN', 'DED'],
                'andalalin' => ['LAMPIRAN', 'Andalalin'],
                'dokumen_teknis' => ['LAMPIRAN', 'Dokumen Teknis'],
                'surat_komitmen' => ['LAMPIRAN', 'Surat Komitmen'],
                'lainnya' => ['LAMPIRAN', 'Lampiran Lainnya'],
            ],
        ],
        // Rekomendasi resmi hanya dapat ditetapkan setelah keputusan pleno dicatat.
        'wajib_pleno_sebelum_rekomendasi' => true,
        // Pemetaan "Jenis Instansi" PSI -> usulan_psn.jenis_pengusul (pencocokan teks tanpa beda huruf besar/kecil).
        'jenis_instansi' => [
            'kementerian/lembaga' => 'KL', 'kementerian' => 'KL', 'lembaga' => 'KL', 'kl' => 'KL', 'kementerian_lembaga' => 'KL',
            'pemerintah daerah' => 'PEMDA', 'pemerintah_daerah' => 'PEMDA', 'pemda' => 'PEMDA', 'provinsi' => 'PEMDA', 'kabupaten/kota' => 'PEMDA',
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

    // Sinkron data dari SIRENDA PEKS3 (aplikasi input profil PSN) ke dashboard. Lihat docs/integrasi-peks3.md.
    // PEKS3 mengirim paket per PSN (HMAC-SHA256) -> basis data cermin (koneksi `legacy`) -> diterapkan atomik
    // lewat LegacyImporter + bangun ulang snapshot semua cut-off.
    'peks3' => [
        'aktif' => (bool) env('PEKS3_AKTIF', false),
        'client_id' => env('PEKS3_CLIENT_ID', 'sirenda-peks3'),
        'rahasia' => env('PEKS3_RAHASIA'),                       // kunci HMAC bersama, min. 32 karakter
        'ip_diizinkan' => array_values(array_filter(array_map('trim', explode(',', (string) env('PEKS3_IP_DIIZINKAN', ''))))),
        'toleransi_detik' => 300,                                // selisih X-Timestamp yang diterima
        'jeda_terapkan_detik' => (int) env('PEKS3_JEDA_DETIK', 60),          // tunggu tidak ada kiriman baru selama ini
        'tunggu_maks_detik' => (int) env('PEKS3_TUNGGU_MAKS_DETIK', 600),    // terapkan paling lambat setelah ini
        'maks_paket' => 50,
        'maks_baris_referensi' => 5000,
        // Rekonsiliasi ditolak bila akan menghapus lebih dari proporsi ini (indikasi daftar ID terpotong).
        'maks_hapus_rekonsiliasi' => 0.2,

        // Tabel per PSN -> kolom kunci PSN. Paket mengganti SELURUH baris tabel ini milik PSN tersebut.
        'tabel_psn' => [
            'psn' => 'id', 'psn_log' => 'id',
            'lokasi_psn' => 'psn_id', 'unit_pengampu' => 'psn_id', 'psn_penanggung_jawab' => 'psn_id', 'psn_pendanaan' => 'psn_id',
            'psn_sdgs' => 'psn_id', 'psn_item' => 'psn_id', 'target_psn' => 'psn_id', 'dukungan_psn' => 'psn_id',
            'psn_fasilitas' => 'psn_id', 'psn_kebutuhan' => 'psn_id', 'psn_dokumentasi' => 'psn_id', 'psn_stakeholder' => 'psn_id',
            'psn_indikator' => 'psn_id', 'psn_penerima' => 'psn_id', 'psn_trisula' => 'psn_id', 'psn_trisula_triwulan' => 'psn_id',
            'psn_kegiatan' => 'psn_id', 'psn_kegiatan_detil' => 'psn_id', 'psn_kegiatan_cp' => 'psn_id', 'psn_kegiatan_detil_cp' => 'psn_id',
            'psn_risiko' => 'psn_id', 'psn_pj_perlakuan' => 'psn_id', 'psn_regulasi' => 'psn_id', 'monev_header' => 'psn_id',
            'psn_risiko_log' => 'psn_id', 'psn_regulasi_log' => 'psn_id', 'psn_kegiatan_log' => 'psn_id', 'psn_indikator_log' => 'psn_id',
            'psn_penerima_log' => 'psn_id', 'psn_trisula_log' => 'psn_id', 'psn_item_log' => 'psn_id', 'psn_kebutuhan_log' => 'psn_id',
            'psn_stakeholder_log' => 'psn_id', 'psn_dokumentasi_log' => 'psn_id', 'psn_fasilitas_log' => 'psn_id', 'psn_sdgs_log' => 'psn_id',
            'psn_pj_perlakuan_log' => 'psn_id',
        ],
        // Anak monev_header (kunci monev_id), ikut diganti bersama paket PSN.
        'tabel_monev' => ['monev_detil', 'monev_kelembagaan', 'monev_capaian', 'monev_anggaran', 'monev_risiko', 'monev_regulasi', 'monev_dokumentasi', 'monev_evaluasi'],
        // Tabel referensi/global: upsert per kunci (tanpa hapus).
        'tabel_referensi' => ['master' => ['tipe_tabel', 'kode_tabel'], 'users' => ['username'], 'psn_2027' => ['id'], 'peta' => ['code', 'hc-key']],
        // Kolom yang tidak boleh dikirim/disimpan (kredensial aplikasi lama).
        'kolom_dibuang' => ['users' => ['password', 'token', 'remember_token']],
    ],

    // Evaluasi keberlanjutan status PSN (kertas kerja "Input Form Pengendalian PSN 2026 v4"). docs/evaluasi-status-psn.md
    'evaluasi_status' => [
        'tahun_rkp' => (int) env('EVALUASI_TAHUN_RKP', 2027),
        'hari_grafik' => 14,

        // Status akhir = Indikasi Status bila sudah diisi; bila belum, Tindak Lanjut; bila belum, Proses Pengecekan.
        'status' => [
            'BELUM_DITERIMA' => 'Form belum diterima',
            'PROSES_PENGECEKAN' => 'Proses Pengecekan',
            'BUTUH_DATA' => 'Butuh Data Tambahan',
            'VERIFIKASI_PJ' => 'Perlu Verifikasi ke PJ',
            'SIAP_DIANALISIS' => 'Siap Dianalisis',
            'DIPERTAHANKAN' => 'Dipertahankan',
            'DIKELUARKAN' => 'Dikeluarkan',
        ],
        'hasil_pengecekan' => [
            'LENGKAP_BENAR' => 'Lengkap dan Benar Semua',
            'LENGKAP_ADA_SALAH' => 'Lengkap, Data Masih Ada yang Salah',
            'BELUM_LENGKAP' => 'Data Belum Lengkap',
            'PROSES' => 'Proses Pengecekan',
        ],
        // Hasil Pengecekan -> Tindak Lanjut (bawaan otomatis; dapat diubah pemeriksa).
        'tindak_lanjut_otomatis' => ['LENGKAP_BENAR' => 'SIAP_DIANALISIS', 'LENGKAP_ADA_SALAH' => 'VERIFIKASI_PJ', 'BELUM_LENGKAP' => 'BUTUH_DATA', 'PROSES' => null],
        'tindak_lanjut' => ['SIAP_DIANALISIS' => 'Siap Dianalisis', 'VERIFIKASI_PJ' => 'Perlu Verifikasi ke PJ', 'BUTUH_DATA' => 'Butuh Data Tambahan'],
        'indikasi' => ['DIPERTAHANKAN' => 'Dipertahankan', 'DIKELUARKAN' => 'Dikeluarkan'],

        'jenis_psn' => ['FISIK' => 'Fisik', 'NON_FISIK' => 'Non-Fisik'],
        // Seluruh daftar pilihan di bawah mengikuti sheet "Daftar Pilihan" kertas kerja (urutan dan teks sama persis).
        'sumber_pendanaan' => ['APBN' => 'APBN', 'APBD' => 'APBD', 'BUMN' => 'BUMN', 'BADAN_USAHA' => 'Badan Usaha-Swasta',
            'DANANTARA' => 'Danantara', 'LAINNYA' => 'Lainnya', 'CAMPURAN' => 'Campuran'],
        // ref_sumber_dana.kode profil -> pilihan A8; lebih dari satu kategori = Campuran.
        'peta_sumber_dana' => ['A' => 'APBN', 'B' => 'APBD', 'C' => 'BUMN', 'D' => 'BADAN_USAHA', 'E' => 'LAINNYA', 'F' => 'DANANTARA', 'G' => 'DANANTARA'],
        'ya_tidak' => ['1' => 'Ya', '0' => 'Tidak'],
        'tahun_min' => 2000,
        'tahun_maks' => 2100,
        'tahapan' => ['PENYIAPAN' => 'Penyiapan', 'TRANSAKSI' => 'Transaksi', 'KONSTRUKSI' => 'Konstruksi', 'BEROPERASI_SEBAGIAN' => 'Beroperasi sebagian', 'KUMULATIF_SELESAI' => 'Kumulatif selesai'],
        'd1' => ['MASIH' => 'Masih membutuhkan status PSN', 'TIDAK' => 'Tidak lagi membutuhkan status PSN'],
        'd1_alasan' => [
            'SELESAI' => 'Proyek/program telah selesai atau mencapai sasaran',
            'BATAL' => 'Proyek/program tidak dilanjutkan/dibatalkan',
            'TANPA_FASILITAS' => 'Dapat dilanjutkan tanpa fasilitas PSN (termasuk program reguler/prioritas K/L)',
            'LAINNYA' => 'Lainnya',
        ],
        // D2: fasilitas kemudahan PP 42/2021.
        'd2_fasilitas' => [
            'PERIZINAN' => 'Perizinan usaha', 'PEMBIAYAAN' => 'Pembiayaan proyek', 'PBJ' => 'Percepatan pengadaan barang/jasa',
            'TATA_RUANG' => 'Rencana tata ruang dan ruang laut', 'PENYIAPAN' => 'Penyiapan proyek', 'DAMPAK_SOSIAL' => 'Penanganan dampak sosial',
            'RENCANA_INDUK' => 'Perencanaan dalam rencana induk sektor', 'HUKUM' => 'Penyelesaian masalah hukum', 'LMAN' => 'Pendanaan pengadaan lahan melalui LMAN',
        ],

        // Kertas kerja (sheet "Format Ringkas"): komponen B2 per jenis PSN. [label, satuan tetap|null, sumber profil]
        'capaian' => [
            'PROGRES_FISIK' => ['Progres fisik', '%', 'progres'],
            'OUTPUT_UTAMA' => ['Output utama', null, null],
            'PEMBIAYAAN' => ['Pembiayaan (penyerapan)', 'Rp miliar', 'pembiayaan'],
            'PERIZINAN' => ['Perizinan', 'izin', null],
            'PENGADAAN_LAHAN' => ['Pengadaan lahan', null, null],
            'REGULASI' => ['Regulasi dan petunjuk teknis', 'dokumen', 'regulasi'],
            'SARANA' => ['Sarana / sistem pendukung', null, null],
        ],
        'capaian_jenis' => [
            'FISIK' => ['PROGRES_FISIK', 'OUTPUT_UTAMA', 'PEMBIAYAAN', 'PERIZINAN', 'PENGADAAN_LAHAN'],
            'NON_FISIK' => ['OUTPUT_UTAMA', 'PEMBIAYAAN', 'REGULASI', 'SARANA'],
        ],
        'milestone' => [
            'FISIK' => [
                'FS' => 'Studi kelayakan (Pra-FS / FS; OBC untuk KPBU)', 'KKPR' => 'Kesesuaian kegiatan pemanfaatan ruang (KKPR)',
                'LINGKUNGAN' => 'Persetujuan lingkungan (AMDAL / UKL-UPL)', 'PENLOK' => 'Penetapan lokasi pengadaan tanah (Penlok)',
                'DED' => 'Desain teknis (Basic Design / DED)', 'PENDANAAN' => 'Kepastian skema dan sumber pendanaan (FBC / penjaminan KPBU)',
                'PENGADAAN' => 'Pengadaan badan usaha / kontraktor', 'KONTRAK' => 'Penandatanganan kontrak / PPJT / financial close (FID)',
                'GROUNDBREAKING' => 'Dimulainya konstruksi (groundbreaking)', 'LAIN' => 'Milestone lain sesuai sektor',
            ],
            'NON_FISIK' => [
                'KAJIAN' => 'Kajian / desain program (naskah kebijakan, peta jalan)', 'REGULASI_DASAR' => 'Penetapan regulasi dasar (Perpres / PP / Permen)',
                'JUKNIS' => 'Petunjuk teknis / NSPK pelaksanaan', 'KELEMBAGAAN' => 'Penetapan kelembagaan dan pelaksana (K/L, BUMN, satgas, mitra)',
                'SASARAN' => 'Penetapan sasaran dan basis data penerima (mis. DTSEN)', 'PENDANAAN_NF' => 'Kepastian skema dan sumber pendanaan (alokasi anggaran / DIPA)',
                'PBJ_MITRA' => 'Pengadaan barang/jasa / perjanjian kerja sama mitra', 'SISTEM' => 'Sistem informasi, monitoring, dan pelaporan',
                'PILOT' => 'Uji coba / pilot', 'PELUNCURAN' => 'Peluncuran / pelaksanaan penuh program', 'LAIN_NF' => 'Milestone lain sesuai program',
            ],
        ],
        'status_milestone' => ['SELESAI' => 'Selesai', 'PROSES' => 'Proses', 'BELUM' => 'Belum', 'NA' => 'N/A'],
        'tipologi_isu' => [
            '1' => 'Pengadaan Tanah dan Legalitas Lahan', '2' => 'Perizinan dan Regulasi', '3' => 'Tata Ruang, Kawasan Hutan, dan Lingkungan',
            '4' => 'Pendanaan, Pembiayaan, dan Investasi', '5' => 'Teknis, Konstruksi, dan Pelaksanaan', '6' => 'Pengadaan Barang/Jasa dan Kontrak',
            '7' => 'Kelembagaan, Koordinasi, dan Kerja Sama', '8' => 'Sosial dan Kemasyarakatan', '9' => 'Infrastruktur Pendukung, Utilitas, dan Konektivitas',
            '10' => 'Operasional dan Optimalisasi Pemanfaatan', '11' => 'Lainnya',
        ],
        'maks_isu' => 5,
        'maks_lampiran' => 8,
        'lampiran_jenis' => ['DOKUMEN' => 'Dokumen', 'FOTO' => 'Foto', 'VIDEO' => 'Video', 'LAINNYA' => 'Lainnya'],
        'lampiran_bentuk' => ['TAUTAN' => 'Tautan', 'FILE' => 'File'],

        // Rekomendasi sistem (alat bantu analis; keputusan tetap pada Indikasi Status yang diisi analis).
        'rekomendasi' => [
            'DIPERTAHANKAN' => 'Indikasi dipertahankan',
            'PERLU_PENDALAMAN' => 'Perlu pendalaman',
            'DIKELUARKAN' => 'Indikasi dikeluarkan',
            'BELUM_CUKUP_DATA' => 'Data belum cukup',
        ],
        'ambang' => [
            'deviasi_fisik_poin' => 10,        // realisasi tertinggal >= 10 poin dari rencana s.d. cut-off
            'serapan_min_persen' => 50,        // realisasi anggaran < 50% pagu s.d. cut-off
            'mundur_tahun' => 2,               // proyeksi selesai (D3) mundur >= 2 tahun dari target (A7)
            'penyiapan_maks_tahun' => 3,       // masih Penyiapan/Transaksi >= 3 tahun sejak ditetapkan (A6)
        ],
    ],
];
