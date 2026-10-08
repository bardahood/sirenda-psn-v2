// ECharts dimuat terpisah (code-split) hanya di halaman bergrafik.
import { LABEL_STATUS, teksAktivitas } from '../label';

const grafik = () => import('./grafik');

const angka = (v, digit = 1) => (v === null || v === undefined ? '–' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: digit }).format(v));

const ENDPOINT = {
    kpi: 'dashboard/kpi',
    kpiTren: 'dashboard/kpi-tren',
    klaster: 'dashboard/distribusi?dim=klaster',
    direktorat: 'dashboard/distribusi?dim=direktorat',
    pulau: 'dashboard/distribusi?dim=pulau',
    komposisi: 'dashboard/distribusi?dim=komposisi_dana',
    provinsi: 'peta',
    progres: 'dashboard/progres',
    tren: 'dashboard/tren',
    roKritis: 'dashboard/ro-kritis?limit=5',
    tahapan: 'dashboard/tahapan',
    statusData: 'dashboard/status-data',
    trisula: 'dashboard/trisula',
    dpProyek: 'dashboard/dp-proyek',
    aktivitas: 'dashboard/aktivitas',
};

// Warna identitas kartu KPI (ikon & sparkline) mengikuti rancangan; K4 merah = risiko.
export const WARNA_KPI = { K1: '#1F6FD1', K2: '#16a34a', K3: '#f59e0b', K4: '#dc2626' };
// Palet kategori tervalidasi (validate_palette.js: lolos CVD & normal-vision), urutan tetap per entitas.
export const WARNA_DANA = { apbn: '#2a78d6', kpbu: '#eb6834', campuran: '#1baf7a', lainnya: '#eda100', apbd: '#4a3aa7', tanpa: '#94a3b8' };
const WARNA_PROGRES = ['#2a78d6', '#eb6834', '#1baf7a', '#4a3aa7'];
const RAMP_PETA = ['#0b4a9c', '#1F6FD1', '#5b9be3', '#a9c9f0'];
const IKON_AKSI = {
    CREATE: ['M12 5v14M5 12h14', 'bg-green-50 text-green-700'],
    UPDATE: ['M4 20h4L19 9l-4-4L4 16v4Z', 'bg-aksen-50 text-aksen-700'],
    DELETE: ['M4 7h16M9 7V4h6v3m-8 0 1 13h8l1-13', 'bg-red-50 text-red-700'],
    SUBMIT: ['M12 19V5m0 0-5 5m5-5 5 5', 'bg-amber-50 text-amber-800'],
    VERIFY: ['M5 12l5 5L20 7', 'bg-green-50 text-green-700'],
    RETURN: ['M9 14 4 9l5-5M4 9h11a5 5 0 0 1 0 10h-3', 'bg-red-50 text-red-700'],
    PUBLISH: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18Zm-9 9h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18', 'bg-violet-50 text-violet-700'],
};

const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function halamanDashboard(kamus, kelasPeta) {
    return {
        kamus,
        kelasPetaCfg: kelasPeta, // config psn_dashboard.peta_kelas
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
                    return window.axios.get(`/api/v1/${url}${qs ? pemisah + qs : ''}`).then((r) => [k, r.data]);
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
            const { render, garisMini, donat, petaRingkas, trenArea } = await grafik();
            const f = this.$store.filter;
            ['K1', 'K2', 'K3', 'K4'].forEach((k) => {
                const titik = (this.d.kpiTren ?? []).map((t) => ({ label: t.cutoff, nilai: t[k] }));
                if (this.$refs[`spark_${k}`]) render(this.$refs[`spark_${k}`], garisMini(titik, WARNA_KPI[k], (v) => this.nilaiKartu({ kode: k, nilai: v })));
            });
            render(this.$refs.dana, donat(this.segmenDana, { klik: true }), (p) => {
                if (['apbn', 'apbd', 'kpbu', 'lainnya'].includes(p.data.kode)) f.tambah('dana', p.data.kode);
            });
            render(this.$refs.peta, petaRingkas(this.d.provinsi ?? [], this.d.pulau ?? [], this.kelasPetaCfg), (p) => {
                if (p.seriesIndex === 0 && p.data.kode) f.tambah('prov', p.data.kode);
            });
            render(this.$refs.p5, trenArea(this.d.tren.bulan));
        },

        // ---- turunan data panel

        get totalPsn() {
            return this.kartu('K1')?.nilai ?? 0;
        },
        persen(n, total = this.totalPsn) {
            return total ? (n / total) * 100 : 0;
        },
        get klasterBaris() {
            const maks = Math.max(1, ...(this.d.klaster ?? []).map((k) => k.jumlah));
            return (this.d.klaster ?? []).map((k) => ({ ...k, lebar: `${(k.jumlah / maks) * 100}%`, pct: this.persen(k.jumlah) }));
        },
        get jumlahKlasterTerdaftar() {
            return this.$store.filter.opsi.klaster?.length ?? 0;
        },
        get direktoratTop() {
            return (this.d.direktorat ?? []).slice(0, 5).map((d) => ({ ...d, pct: this.persen(d.jumlah) }));
        },
        get trisulaCincin() {
            const urutan = { 'Pertumbuhan Ekonomi': 'Pertumbuhan Ekonomi Tinggi & Berkelanjutan', Kemiskinan: 'Penurunan Tingkat Kemiskinan', 'Sumber Daya Manusia': 'Peningkatan Kualitas SDM' };
            return Object.entries(urutan).map(([kunci, judul]) => {
                const k = (this.d.trisula?.kategori ?? []).find((x) => x.label === kunci) ?? { indikator: 0, psn: 0 };
                const pct = this.persen(k.psn);
                return { judul, psn: k.psn, indikator: k.indikator, pct, garis: `${(Math.min(pct, 100) / 100) * 2 * Math.PI * 42} ${2 * Math.PI * 42}` };
            });
        },
        get progresBaris() {
            const p = this.d.progres;
            if (!p) return [];
            const dp = p.DP ?? { total: 0, on_track: 0 };
            return [
                { label: 'Realisasi fisik', nilai: p.P2.realisasi_persen, kanan: p.P2.realisasi_persen === null ? 'belum ada data' : `${angka(p.P2.realisasi_persen)}% / rencana ${angka(p.P2.rencana_persen)}%`, target: p.P2.rencana_persen, catatan: `${angka(p.P2.cakupan_psn, 0)} dari ${angka(p.P2.total_psn, 0)} PSN berdata`, warna: WARNA_PROGRES[0] },
                { label: `Realisasi anggaran ${p.P3.tahun}`, nilai: p.P3.persen, kanan: p.P3.persen === null ? 'belum ada pagu' : `${angka(p.P3.persen)}%`, target: null, catatan: `Rp ${angka(p.P3.realisasi_triliun, 2)} T dari pagu Rp ${angka(p.P3.pagu_triliun, 2)} T`, warna: WARNA_PROGRES[1] },
                { label: 'Rincian output (KP/RO) tercapai', nilai: p.P4.persen, kanan: `${angka(p.P4.tercapai, 0)} / ${angka(p.P4.total, 0)}`, target: null, catatan: 'realisasi ≥ rencana pada cut-off', warna: WARNA_PROGRES[2] },
                { label: 'PSN Klaster Direktif Presiden on track', nilai: dp.total ? (dp.on_track / dp.total) * 100 : null, kanan: `${angka(dp.on_track, 0)} / ${angka(dp.total, 0)} on track`, target: null, catatan: `${angka(dp.berdata ?? 0, 0)} PSN DP memiliki data progres`, warna: WARNA_PROGRES[3] },
            ];
        },
        get segmenDana() {
            return (this.d.komposisi ?? []).filter((k) => k.investasi_triliun > 0).map((k) => ({ ...k, nilai: k.investasi_triliun, warna: WARNA_DANA[k.kode] ?? '#94a3b8' }));
        },
        get totalInvestasi() {
            return (this.d.komposisi ?? []).reduce((a, k) => a + k.investasi_triliun, 0);
        },
        get kelasPeta() {
            return this.kelasPetaCfg.map((k, i) => ({ ...k, warna: RAMP_PETA[i] }));
        },
        get timeline() {
            const t = this.d.dpProyek;
            if (!t) return { tahun: [], baris: [], hariIni: 0 };
            const awal = Math.min(t.tahun_awal, ...t.proyek.map((p) => p.tahun_selesai ?? t.tahun_awal));
            const akhir = Math.max(t.tahun_akhir, ...t.proyek.map((p) => Math.min(p.tahun_selesai ?? 0, t.tahun_akhir + 1)));
            const rentang = akhir + 1 - awal;
            const posisi = (tahunDesimal) => `${Math.max(0, Math.min(100, ((tahunDesimal - awal) / rentang) * 100))}%`;
            const tgl = new Date(this.meta?.cutoff?.tanggal ?? Date.now());
            return {
                tahun: Array.from({ length: rentang }, (_, i) => awal + i),
                hariIni: posisi(tgl.getFullYear() + tgl.getMonth() / 12),
                baris: t.proyek.map((p) => ({ ...p, lebar: p.tahun_selesai ? posisi(Math.min(p.tahun_selesai, akhir) + 1) : '0%', lewat: p.tahun_selesai && p.tahun_selesai > akhir })),
                total: t.total,
            };
        },
        ikonAksi(aksi) {
            return IKON_AKSI[aksi] ?? IKON_AKSI.UPDATE;
        },
        warnaKpi(k) {
            return WARNA_KPI[k];
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
            if (k.kode === 'K2') return `Rp ${angka(k.nilai)} T`;
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

        /** Delta ringkas untuk pil kartu (tanpa teks pembanding). */
        deltaRingkas(k) {
            const d = k?.delta;
            if (!d || d.nilai === null) return '–';
            return `${d.nilai > 0 ? '+' : ''}${angka(d.nilai)}${d.satuan === 'pp' ? ' pp' : '%'}`;
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
            return LABEL_STATUS[kode] ?? kode;
        },

        teksAktivitas,

        waktuRelatif(iso) {
            if (!iso) return 'waktu tidak tercatat';
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
                case 'DIREKTORAT': return d.direktorat.map((r) => ({ direktorat: r.label, jumlah_psn: r.jumlah }));
                case 'P7': return d.provinsi.map((r) => ({ kode: r.kode, provinsi: r.label, jumlah_psn: r.jumlah }));
                case 'P6': return d.komposisi.map((r) => ({ kelompok: r.label, investasi_rp_triliun: r.investasi_triliun, persen: r.persen, jumlah_psn: r.jumlah }));
                case 'P5': return d.tren.bulan.map((r) => ({ tahun: d.tren.tahun, bulan: r.bulan, cutoff: r.cutoff, rencana_persen: r.rencana_persen, realisasi_persen: r.realisasi_persen }));
                case 'P2': return [{ ...d.progres.P2, ...Object.fromEntries(Object.entries(d.progres.P3).map(([k, v]) => [`anggaran_${k}`, v])), ro_tercapai: d.progres.P4.tercapai, ro_total: d.progres.P4.total, dp_on_track: d.progres.DP?.on_track, dp_total: d.progres.DP?.total }];
                case 'RO_KRITIS': return d.roKritis.map((r) => ({ psn: r.psn, direktorat: r.direktorat, kp_ro: r.kegiatan, rencana_persen: r.rencana_persen, realisasi_persen: r.realisasi_persen, deviasi_pp: r.deviasi_pp, status: this.labelStatus(r.status) }));
                case 'TAHAPAN': return d.tahapan.map((r) => ({ tahap: r.label, jumlah_psn: r.jumlah }));
                case 'P8': return d.trisula.kategori;
                case 'TIMELINE_DP': return d.dpProyek.proyek.map((r) => ({ psn: r.nama, direktorat: r.direktorat, tahun_selesai: r.tahun_selesai, status: this.labelStatus(r.status), realisasi_persen: r.realisasi_persen }));
                default: return [];
            }
        },
    };
}
