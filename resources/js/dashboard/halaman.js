// ECharts dimuat terpisah (code-split) hanya di halaman bergrafik.
const grafik = () => import('./grafik');

const angka = (v, digit = 1) => (v === null || v === undefined ? '–' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: digit }).format(v));

const ENDPOINT = {
    kpi: 'kpi',
    klaster: 'distribusi?dim=klaster',
    provinsi: 'distribusi?dim=provinsi',
    dana: 'distribusi?dim=dana',
    progres: 'progres',
    tren: 'tren',
    roKritis: 'ro-kritis?limit=10',
    tahapan: 'tahapan',
    statusData: 'status-data',
    trisula: 'trisula',
    timelineDp: 'timeline-dp',
    aktivitas: 'aktivitas',
};

const LABEL_TABEL = {
    psn: 'profil PSN', psn_lokasi: 'lokasi PSN', psn_stakeholder: 'stakeholder', psn_profil_item: 'item profil', psn_dokumen: 'dokumen',
    kegiatan: 'KP/RO', kegiatan_target: 'target/realisasi KP/RO', risiko: 'risiko', risiko_pemantauan: 'pemantauan risiko',
    regulasi: 'regulasi', isu: 'isu', indikator: 'indikator', trisula: 'indikator Trisula', periode_cutoff: 'snapshot cut-off',
    pengisian_psn: 'pengisian data', risiko_perlakuan: 'perlakuan risiko',
};
const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const LABEL_AKSI = { CREATE: 'menambah', UPDATE: 'mengubah', DELETE: 'menghapus', RESTORE: 'memulihkan', PUBLISH: 'menerbitkan', SUBMIT: 'mengajukan', VERIFY: 'memverifikasi', RETURN: 'mengembalikan' };

export function halamanDashboard(kamus) {
    return {
        kamus,
        d: Object.fromEntries(Object.keys(ENDPOINT).map((k) => [k, null])),
        meta: null,
        memuat: true,
        galat: null,
        tooltipTerbuka: null,
        angka,

        async init() {
            await this.$store.filter.muatOpsi();
            await this.muat();
            window.addEventListener('filter-berubah', () => this.muat());
        },

        async muat() {
            this.memuat = true;
            this.galat = null;
            const qs = this.$store.filter.qs();
            try {
                const hasil = await Promise.all(Object.entries(ENDPOINT).map(([k, url]) => {
                    const pemisah = url.includes('?') ? '&' : '?';
                    return window.axios.get(`/api/v1/dashboard/${url}${qs ? pemisah + qs : ''}`).then((r) => [k, r.data]);
                }));
                hasil.forEach(([k, r]) => (this.d[k] = r.data));
                this.meta = hasil.find(([k]) => k === 'kpi')[1].meta;
                this.$nextTick(() => this.gambar());
            } catch (e) {
                this.galat = e.response?.data?.message || 'Data dashboard gagal dimuat. Coba muat ulang halaman.';
            } finally {
                this.memuat = false;
            }
        },

        async gambar() {
            if (!this.meta?.cutoff) return;
            const { render, batangHorizontal, kolom, tren } = await grafik();
            const f = this.$store.filter;
            render(this.$refs.p1, batangHorizontal(this.d.klaster, { klik: true, lebarLabel: 150 }), (p) => {
                const item = this.d.klaster[this.d.klaster.length - 1 - p.dataIndex];
                if (item?.id) f.tambah('klaster', item.id);
            });
            render(this.$refs.p7, batangHorizontal(this.d.provinsi.slice(0, 12), { klik: true }), (p) => {
                const daftar = this.d.provinsi.slice(0, 12);
                f.tambah('prov', daftar[daftar.length - 1 - p.dataIndex].kode);
            });
            render(this.$refs.p6, batangHorizontal(this.d.dana, { nilai: 'investasi_triliun', satuan: 'Rp triliun', klik: true, sumbuX: false, lebarLabel: 60 }), (p) => {
                f.tambah('dana', this.d.dana[this.d.dana.length - 1 - p.dataIndex].kode);
            });
            render(this.$refs.tahapan, batangHorizontal(this.d.tahapan));
            render(this.$refs.p5, tren(this.d.tren.bulan));
            render(this.$refs.dp, kolom(this.d.timelineDp));
        },

        // ---- tampilan

        get tanggalCutoff() {
            return this.meta?.cutoff ? new Date(this.meta.cutoff.tanggal).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) : '–';
        },

        info(kode) {
            const k = this.kamus[kode];
            return k ? `${k.rumus}\nSumber: ${k.sumber}\nCut-off: ${this.tanggalCutoff}` : '';
        },

        kartu(kode) {
            return this.d.kpi?.find((k) => k.kode === kode) ?? null;
        },

        nilaiKartu(k) {
            if (!k || k.nilai === null) return '–';
            if (k.kode === 'K2') return angka(k.nilai);
            if (k.kode === 'K3') return `${angka(k.nilai)}%`;
            return angka(k.nilai, 0);
        },

        teksDelta(k) {
            const d = k?.delta;
            if (!d || d.nilai === null) return 'Tidak ada pembanding';
            const tanda = d.nilai > 0 ? '+' : '';
            const [th, bl] = (this.meta.sebelumnya ?? '').split('-');
            return `${tanda}${angka(d.nilai)}${d.satuan === 'pp' ? ' pp' : '%'} vs ${BULAN_PENDEK[+bl - 1] ?? ''} ${th ?? ''}`;
        },

        kelasDelta(k) {
            const b = k?.delta?.baik;
            return b === true ? 'bg-green-50 text-green-800' : b === false ? 'bg-red-50 text-red-800' : 'bg-slate-100 text-slate-700';
        },

        panahDelta(k) {
            const a = k?.delta?.arah;
            return a === 'naik' ? '▲' : a === 'turun' ? '▼' : '■';
        },

        labelStatus(kode) {
            return { ON_TRACK: 'On Track', BERISIKO: 'Berisiko', TERLAMBAT: 'Terlambat', TANPA_DATA: 'Tanpa data' }[kode] ?? kode;
        },

        teksAktivitas(a) {
            return `${['console', 'sistem'].includes(a.pengguna) ? 'Sistem' : a.pengguna} ${LABEL_AKSI[a.aksi] ?? a.aksi.toLowerCase()} ${LABEL_TABEL[a.tabel] ?? a.tabel.replaceAll('_', ' ')}`;
        },

        waktuRelatif(iso) {
            if (!iso) return '';
            const detik = (Date.now() - new Date(iso).getTime()) / 1000;
            const rtf = new Intl.RelativeTimeFormat('id-ID', { numeric: 'auto' });
            if (detik < 3600) return rtf.format(-Math.round(detik / 60), 'minute');
            if (detik < 86400) return rtf.format(-Math.round(detik / 3600), 'hour');
            return rtf.format(-Math.round(detik / 86400), 'day');
        },

        persenLebar(v) {
            return `${Math.max(0, Math.min(100, v ?? 0))}%`;
        },

        // ---- unduh

        async unduhPng(ref, kode) {
            const { png } = await grafik();
            png(this.$refs[ref], `${kode}_${this.meta?.cutoff?.kode ?? ''}`);
        },

        unduhCsv(kode) {
            const baris = this.barisCsv(kode);
            if (!baris.length) return;
            const kolom = Object.keys(baris[0]);
            const esc = (v) => `"${String(v ?? '').replaceAll('"', '""')}"`;
            const isi = [kolom.join(';'), ...baris.map((b) => kolom.map((k) => esc(b[k])).join(';'))].join('\r\n');
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob(['﻿' + isi], { type: 'text/csv;charset=utf-8' }));
            a.download = `${kode}_${this.meta?.cutoff?.kode ?? ''}.csv`;
            a.click();
            URL.revokeObjectURL(a.href);
        },

        barisCsv(kode) {
            const d = this.d;
            switch (kode) {
                case 'KPI': return d.kpi.map((k) => ({ indikator: `${k.kode} ${this.kamus[k.kode].nama}`, nilai: k.nilai, sebelumnya: k.sebelumnya, delta: k.delta.nilai, satuan_delta: k.delta.satuan }));
                case 'P1': return d.klaster.map((r) => ({ klaster: r.label, jumlah_psn: r.jumlah }));
                case 'P7': return d.provinsi.map((r) => ({ kode: r.kode, provinsi: r.label, jumlah_psn: r.jumlah }));
                case 'P6': return d.dana.map((r) => ({ skema: r.label, investasi_rp_triliun: r.investasi_triliun, jumlah_psn: r.jumlah }));
                case 'P5': return d.tren.bulan.map((r) => ({ tahun: d.tren.tahun, bulan: r.bulan, cutoff: r.cutoff, rencana_persen: r.rencana_persen, realisasi_persen: r.realisasi_persen }));
                case 'P2': return [{ ...d.progres.P2, ...Object.fromEntries(Object.entries(d.progres.P3).map(([k, v]) => [`anggaran_${k}`, v])), ro_tercapai: d.progres.P4.tercapai, ro_total: d.progres.P4.total }];
                case 'RO_KRITIS': return d.roKritis.map((r) => ({ psn: r.psn, kp_ro: r.kegiatan, rencana_persen: r.rencana_persen, realisasi_persen: r.realisasi_persen, deviasi_pp: r.deviasi_pp, status: this.labelStatus(r.status) }));
                case 'TAHAPAN': return d.tahapan.map((r) => ({ tahap: r.label, jumlah_psn: r.jumlah }));
                case 'P8': return d.trisula.kategori;
                case 'TIMELINE_DP': return d.timelineDp.map((r) => ({ tahun_selesai: r.label, jumlah_psn: r.jumlah }));
                default: return [];
            }
        },
    };
}
