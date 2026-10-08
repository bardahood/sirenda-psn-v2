// Label UI untuk kode teknis. Padanan backend: app/Support/Label.php -- perbarui bersamaan.
export const LABEL_TABEL = {
    psn: 'profil PSN', psn_lokasi: 'lokasi PSN', psn_stakeholder: 'stakeholder', psn_profil_item: 'isian project profile',
    psn_dokumen: 'dokumen', psn_fasilitas: 'fasilitas', psn_sdgs: 'SDGs', psn_sumber_dana: 'sumber dana',
    kegiatan: 'KP/RO', kegiatan_target: 'target/realisasi KP/RO', risiko: 'risiko', risiko_pemantauan: 'pemantauan risiko',
    risiko_perlakuan: 'perlakuan risiko', regulasi: 'regulasi', isu: 'isu', indikator: 'indikator',
    penerima_manfaat: 'penerima manfaat', trisula: 'indikator Trisula', periode_cutoff: 'snapshot cut-off', pengisian_psn: 'pengisian data',
};

export const LABEL_AKSI = {
    CREATE: 'menambah', UPDATE: 'mengubah', DELETE: 'menghapus', RESTORE: 'memulihkan',
    PUBLISH: 'menerbitkan', SUBMIT: 'mengajukan', VERIFY: 'memverifikasi', RETURN: 'mengembalikan',
};

export const LABEL_STATUS = { ON_TRACK: 'On Track', BERISIKO: 'Berisiko', TERLAMBAT: 'Terlambat', TANPA_DATA: 'Tanpa data' };

export const pengguna = (p) => (['console', 'sistem', null, undefined, ''].includes(p) ? 'Sistem' : p);

/** "Sistem menerbitkan snapshot cut-off" */
export const teksAktivitas = (a) => `${pengguna(a.pengguna)} ${LABEL_AKSI[a.aksi] ?? a.aksi.toLowerCase()} ${LABEL_TABEL[a.tabel] ?? a.tabel.replaceAll('_', ' ')}`;

export const waktuLokal = (iso) => (iso ? new Date(iso).toLocaleString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'waktu tidak tercatat');

/** Nama direktorat dipendekkan untuk sumbu grafik (nama lengkap di tooltip). */
export const pendekDirektorat = (n) => n.replace(/^Direktorat\s+/i, 'Dit. ');
