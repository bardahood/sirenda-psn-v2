// ECharts dimuat terpisah (code-split) hanya di halaman bergrafik.
import { LABEL_STATUS } from '../label';

const grafik = () => import('./grafik');

const angka = (v, digit = 1) => (v === null || v === undefined ? '–' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: digit }).format(v));

// Pemantauan Pelaksanaan: kartu & seluruh panel dalam satu endpoint; kpi dipakai untuk meta cut-off.
const ENDPOINT = { kpi: 'dashboard/kpi', statusData: 'dashboard/status-data', pelaksanaan: 'dashboard/pelaksanaan' };

// Ikhtisar Portofolio: kartu memakai K1-K4 yang sama + seluruh panel paparan dalam satu endpoint.
const ENDPOINT_IKHTISAR = { kpi: 'dashboard/kpi', kpiTren: 'dashboard/kpi-tren', statusData: 'dashboard/status-data', ikhtisar: 'dashboard/ikhtisar' };

// Warna identitas kartu KPI (ikon & sparkline) mengikuti rancangan; K4 merah = risiko; K5 ungu = PKPN.
export const WARNA_KPI = { K1: '#1F6FD1', K2: '#16a34a', K3: '#f59e0b', K4: '#dc2626', K5: '#7c3aed' };
// Skema pendanaan 3 kelompok (paparan): palet kategori tervalidasi yang sama, urutan tetap.
export const WARNA_SKEMA = { non_apbn: '#2a78d6', campuran: '#1baf7a', apbn_apbd: '#eb6834', tanpa: '#94a3b8' };
const WARNA_TARGET = { SEBELUM: '#1baf7a', BATAS: '#2a78d6', SETELAH: '#eb6834', TANPA: '#94a3b8' };
// Palet kategori tervalidasi (validate_palette.js: lolos CVD & normal-vision), urutan tetap per entitas.
export const WARNA_DANA = { apbn: '#2a78d6', kpbu: '#eb6834', campuran: '#1baf7a', lainnya: '#eda100', apbd: '#4a3aa7', tanpa: '#94a3b8' };
// Warna status (sama dengan badge-*): dipakai sel matriks, titik kuadran, dan pergerakan.
export const WARNA_STATUS = { ON_TRACK: '#16a34a', BERISIKO: '#d97706', TERLAMBAT: '#dc2626', TANPA_DATA: '#64748b' };
const WARNA_GERAK = { MEMBAIK: '#16a34a', MULAI: '#2a78d6', TETAP: '#cbd5e1', BARU: '#94a3b8', BERHENTI: '#d97706', MEMBURUK: '#dc2626' };
// Kartu Pemantauan Pelaksanaan: [kode, judul, warna identitas, ikon, kode kamus].
const KARTU_PL = [
    ['aktif', 'PSN Aktif', WARNA_KPI.K1, 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1', 'PL_AKTIF'],
    ['realisasi', 'Progres Fisik vs Rencana', WARNA_KPI.K3, 'M3 3v18h18M7 15l4-4 3 3 5-6', 'PL_PROGRES'],
    ['serapan', 'Serapan Anggaran', WARNA_KPI.K2, 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6', 'PL_SERAPAN'],
    ['kritis', 'PSN Kritis', WARNA_KPI.K4, 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z', 'PL_KRITIS'],
    ['cakupan', 'Cakupan Pelaporan', WARNA_KPI.K5, 'M9 12l2 2 4-4M5 4h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z', 'PL_CAKUPAN'],
];

const BULAN_PENDEK = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function halamanDashboard(kamus, kelasPeta, mode = 'pelaksanaan') {
    const endpoint = mode === 'ikhtisar' ? ENDPOINT_IKHTISAR : ENDPOINT;
    return {
        kamus,
        mode,
        kelasPetaCfg: kelasPeta, // config psn_dashboard.peta_kelas
        d: Object.fromEntries(Object.keys(endpoint).map((k) => [k, null])),
        kebutuhanTerpilih: 'KONFIRMASI',
        meta: null,
        memuat: true,
        galat: null,
        tooltipTerbuka: null,
        angka,

        async init() {
            // Dashboard tidak menampilkan pilihan periode: selalu cut-off terbit terbaru.
            if (this.$store.filter.nilai.periode) {
                this.$store.filter.nilai.periode = '';
                this.$store.filter.terapkan();
            }
            await this.$store.filter.muatOpsi();
            await this.muat();
            window.addEventListener('filter-berubah', () => this.muat());
        },

        async muat() {
            this.memuat = true;
            this.galat = null;
            const qs = this.$store.filter.qs();
            try {
                const hasil = await Promise.all(Object.entries(endpoint).map(([k, url]) => {
                    const pemisah = url.includes('?') ? '&' : '?';
                    return window.axios.get(`/api/v1/${url}${qs ? pemisah + qs : ''}`).then((r) => [k, r.data]);
                }));
                hasil.forEach(([k, r]) => (this.d[k] = r.data));
                this.meta = hasil.find(([k]) => k === 'kpi')[1].meta;
                const sd = this.d.statusData;
                Object.assign(this.$store.statusDasbor, this.meta?.cutoff && sd
                    ? { tanggal: this.tanggalCutoff, kelengkapan: `${angka(sd.kelengkapan_persen)}%`, belum: `${angka(sd.belum_terverifikasi, 0)} / ${angka(sd.total_psn, 0)}` }
                    : { tanggal: null, kelengkapan: null, belum: null });
                this.$nextTick(() => this.gambar());
            } catch (e) {
                this.galat = e.response?.data?.message || 'Data dashboard gagal dimuat. Coba muat ulang halaman.';
            } finally {
                this.memuat = false;
            }
        },

        async gambar() {
            if (!this.meta?.cutoff) return;
            const { render, garisMini, donat, petaWilayah, kurvaProyeksi, kuadran } = await grafik();
            if (this.mode !== 'ikhtisar') {
                const p = this.pl;
                if (!p) return;
                this.$root.querySelectorAll('[data-spark]').forEach((el) => {
                    const k = el.dataset.spark;
                    const kartu = this.kartuPl.find((x) => x.kode === k);
                    render(el, garisMini(p.kpi.tren.map((t) => ({ label: t.cutoff, nilai: t[k] })), kartu.warna, (v) => this.formatKpi(k, v)));
                });
                render(this.$refs.kurva, kurvaProyeksi(p.kurva.bulan, p.kurva.proyeksi));
                render(this.$refs.kuadran, kuadran(p.kuadran, WARNA_STATUS, LABEL_STATUS), (x) => {
                    if (x.data?.psn_id) window.location.href = this.$store.filter.tautan('/proyek/' + x.data.psn_id);
                });
                return;
            }
            ['K1', 'K2', 'K3', 'K4'].forEach((k) => {
                const titik = (this.d.kpiTren ?? []).map((t) => ({ label: t.cutoff, nilai: t[k] }));
                if (this.$refs[`spark_${k}`]) render(this.$refs[`spark_${k}`], garisMini(titik, WARNA_KPI[k], (v) => this.nilaiKartu({ kode: k, nilai: v })));
            });
            const ik = this.d.ikhtisar;
            if (!ik) return;
            render(this.$refs.skema, donat(this.segmenSkema, { klik: false }));
            render(this.$refs.wilayah, petaWilayah(ik.wilayah.kelompok, ik.wilayah.provinsi));
        },

        // ---- turunan data panel

        /** Penyebut persentase panel: PSN aktif (K1 menghitung seluruh PSN tercatat). */
        get totalPsn() {
            return this.kartu('K1')?.aktif ?? this.kartu('K1')?.nilai ?? 0;
        },
        persen(n, total = this.totalPsn) {
            return total ? (n / total) * 100 : 0;
        },
        // ---- Pemantauan Pelaksanaan
        get pl() {
            return this.d.pelaksanaan;
        },
        formatKpi(k, v) {
            if (v === null || v === undefined) return '–';
            return ['realisasi', 'serapan', 'cakupan'].includes(k) ? `${angka(v)}%` : angka(v, 0);
        },
        get kartuPl() {
            const p = this.pl?.kpi;
            if (!p) return [];
            const n = p.nilai;
            const f = this.$store.filter;
            const proyek = (tambahan) => f.tautan('/proyek') + (tambahan ? (f.qs() ? '&' : '?') + tambahan : '');
            const ket = {
                aktif: `${angka(n.melapor, 0)} melapor · ${angka(n.aktif - n.melapor, 0)} tanpa data`,
                realisasi: n.realisasi === null ? 'belum ada PSN berdata' : `deviasi ${n.deviasi > 0 ? '+' : ''}${angka(n.deviasi)} pp dari rencana ${angka(n.rencana)}% · ${angka(n.berdata, 0)} PSN berdata`,
                serapan: `Rp ${angka(n.realisasi_triliun, 2)} T dari pagu Rp ${angka(n.pagu_triliun, 2)} T`,
                kritis: `Terlambat atau risiko ≥ Tinggi, dari ${angka(n.aktif, 0)} PSN`,
                cakupan: `${angka(n.melapor, 0)} dari ${angka(n.aktif, 0)} PSN melaporkan progres`,
            };
            const tautan = { aktif: proyek(''), realisasi: proyek(''), serapan: proyek(''), kritis: proyek('kritis=1'), cakupan: proyek('status=tanpa_data') };
            return KARTU_PL.map(([kode, judul, warna, ikon, info]) => ({
                kode, judul, warna, ikon, info, nilai: this.formatKpi(kode, n[kode]), delta: p.delta[kode],
                status: kode === 'realisasi' && n.realisasi !== null ? n.status : null, keterangan: ket[kode], tautan: tautan[kode],
            }));
        },
        get teksKurva() {
            const k = this.pl?.kurva;
            if (!k) return '';
            return k.proyeksi ? `Proyeksi realisasi Desember ${angka(k.proyeksi.desember)}% dengan laju saat ini · ${angka(this.pl.kpi.nilai.berdata, 0)} PSN berdata` : `Belum ada data progres ${k.tahun}`;
        },
        get teksKuadran() {
            const q = this.pl?.kuadran;
            if (!q) return '';
            return `${q.titik.length} PSN berdata · ${q.besar_terlambat} PSN besar dan tertinggal · ${q.tanpa_data} PSN belum dapat dipetakan`;
        },
        kelasAlasan(kode) {
            if (['TERLAMBAT', 'BERISIKO', 'TANPA_DATA'].includes(kode)) return `badge-${kode}`;
            return { RISIKO: 'badge bg-red-50 text-red-800 ring-red-600/40', ISU_KRITIS: 'badge bg-orange-50 text-orange-900 ring-orange-600/40', ISU_BANYAK: 'badge bg-amber-50 text-amber-900 ring-amber-600/40' }[kode] ?? 'badge bg-slate-100 text-slate-700 ring-slate-400/40';
        },
        gayaSel(status, n, total) {
            if (!n) return { color: '#94a3b8' };
            const a = Math.round((0.15 + 0.75 * (n / Math.max(1, total))) * 255).toString(16).padStart(2, '0');
            return { background: `${WARNA_STATUS[status]}${a}`, color: n / Math.max(1, total) > 0.45 ? '#fff' : '#0f172a' };
        },
        saringSel(klaster, status) {
            if (!klaster) return;
            const f = this.$store.filter;
            if (!f.nilai.klaster.includes(String(klaster))) f.nilai.klaster = [...f.nilai.klaster, String(klaster)];
            f.nilai.status = [status.toLowerCase()];
            f.terapkan();
        },
        get kepatuhanTop() {
            return (this.pl?.kepatuhan.kl ?? []).slice(0, this.pl?.batas?.kepatuhan ?? 8);
        },
        get teksKepatuhan() {
            const k = this.pl?.kepatuhan;
            if (!k) return '';
            return `Terendah dulu · ${k.nol} dari ${k.jumlah_kl} K/L belum melaporkan satu PSN pun · klik untuk memfilter`;
        },
        get hambatanBaris() {
            const h = this.pl?.hambatan;
            if (!h) return [];
            const maks = Math.max(1, ...h.kategori.map((x) => x.jumlah));
            return h.kategori.map((x) => ({ ...x, lebarKritis: `${(x.psn_kritis / maks) * 100}%`, lebarLain: `${(x.psn_lain / maks) * 100}%` }));
        },
        get teksHambatan() {
            const h = this.pl?.hambatan;
            if (!h) return '';
            return `${angka(h.total, 0)} isu terbuka di ${angka(h.psn, 0)} PSN · ${angka(h.pada_psn_kritis, 0)} pada PSN kritis`;
        },
        get pergerakanSegmen() {
            const g = this.pl?.pergerakan.kelompok ?? [];
            const total = Math.max(1, g.reduce((a, x) => a + x.jumlah, 0));
            const urut = ['MEMBAIK', 'MULAI', 'TETAP', 'BARU', 'BERHENTI', 'MEMBURUK'];
            return urut.map((k) => g.find((x) => x.kode === k)).filter((x) => x && (x.jumlah > 0 || ['MEMBAIK', 'MEMBURUK'].includes(x.kode)))
                .map((x) => ({ ...x, warna: WARNA_GERAK[x.kode], lebar: `${(x.jumlah / total) * 100}%` }));
        },
        // ---- Ikhtisar Portofolio
        get rkpTahap() {
            return this.d.ikhtisar?.rkp ?? [];
        },
        get segmenSkema() {
            return (this.d.ikhtisar?.skema ?? []).filter((k) => k.investasi_triliun > 0).map((k) => ({ ...k, nilai: k.investasi_triliun, warna: WARNA_SKEMA[k.kode] ?? '#94a3b8' }));
        },
        get skemaBaris() {
            return (this.d.ikhtisar?.skema ?? []).filter((k) => k.kode !== 'tanpa' || k.jumlah > 0).map((k) => ({ ...k, warna: WARNA_SKEMA[k.kode] ?? '#94a3b8' }));
        },
        get skemaUtama() {
            return [...(this.d.ikhtisar?.skema ?? [])].sort((a, b) => (b.persen ?? 0) - (a.persen ?? 0))[0] ?? null;
        },
        get totalInvestasiSkema() {
            return (this.d.ikhtisar?.skema ?? []).reduce((a, k) => a + k.investasi_triliun, 0);
        },
        get wilayahBaris() {
            return this.d.ikhtisar?.wilayah.kelompok ?? [];
        },
        get selesaiKategori() {
            const s = this.d.ikhtisar?.selesai;
            if (!s) return [];
            const warna = { TIDAK: '#dc2626', MASIH: '#16a34a', KONFIRMASI: '#d97706' };
            return s.kategori.map((k) => ({ ...k, warna: warna[k.kode], pct: s.total ? (k.jumlah / s.total) * 100 : 0 }));
        },
        get selesaiTerpilih() {
            return this.selesaiKategori.find((k) => k.kode === this.kebutuhanTerpilih) ?? null;
        },
        get statusTahap() {
            const t = this.d.ikhtisar?.status;
            if (!t) return [];
            const maks = Math.max(1, ...t.tahap.map((x) => x.jumlah));
            return t.tahap.map((x) => ({ ...x, lebar: `${(x.jumlah / maks) * 100}%` }));
        },
        get targetSegmen() {
            const t = this.d.ikhtisar?.status;
            if (!t) return [];
            return t.target.map((x) => ({ ...x, warna: WARNA_TARGET[x.kode], pct: t.belum_selesai ? (x.jumlah / t.belum_selesai) * 100 : 0 }));
        },
        kartuPkpn() {
            const p = this.d.ikhtisar?.pkpn;
            return p ? { kode: 'K5', nilai: p.jumlah, delta: p.delta, investasi_triliun: p.investasi_triliun, persen_investasi: p.persen_investasi } : null;
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
            if (kode === 'K5') return this.kartuPkpn();
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
                case 'PL_KURVA': return d.pelaksanaan.kurva.bulan.map((r) => ({ tahun: d.pelaksanaan.kurva.tahun, bulan: r.bulan, cutoff: r.cutoff, rencana_persen: r.rencana_persen, realisasi_persen: r.realisasi_persen }))
                    .concat(d.pelaksanaan.kurva.proyeksi ? [{ tahun: d.pelaksanaan.kurva.tahun, bulan: 12, cutoff: 'proyeksi', rencana_persen: '', realisasi_persen: d.pelaksanaan.kurva.proyeksi.desember }] : []);
                case 'PL_KUADRAN': return d.pelaksanaan.kuadran.titik.map((r) => ({ psn: r.nama, penanggung_jawab: r.pj, deviasi_pp: r.deviasi_pp, investasi_rp_triliun: r.investasi_triliun, isu_terbuka: r.isu, status: this.labelStatus(r.status) }));
                case 'PL_PERHATIAN': return d.pelaksanaan.perhatian.map((r, i) => ({ peringkat: i + 1, psn: r.nama, penanggung_jawab: r.pj, skor: r.skor, status: this.labelStatus(r.status), investasi_rp_triliun: r.investasi_triliun, rencana_persen: r.rencana_persen, realisasi_persen: r.realisasi_persen, isu_terbuka: r.isu, alasan: r.alasan.map((a) => a.label).join(', ') }));
                case 'PL_MATRIKS': return d.pelaksanaan.matriks.baris.map((r) => ({ klaster: r.label, jumlah_psn: r.jumlah, ...Object.fromEntries(Object.entries(r.sel).map(([k, v]) => [this.labelStatus(k), v])) }));
                case 'PL_KEPATUHAN': return d.pelaksanaan.kepatuhan.kl.map((r) => ({ kementerian_lembaga: r.label, jumlah_psn: r.jumlah, psn_melapor: r.melapor, persen: r.persen }));
                case 'PL_HAMBATAN': return d.pelaksanaan.hambatan.kategori.map((r) => ({ kategori: r.label, isu_terbuka: r.jumlah, pada_psn_kritis: r.psn_kritis, pada_psn_lain: r.psn_lain, jumlah_psn: r.psn }));
                case 'PL_PERGERAKAN': return [...d.pelaksanaan.pergerakan.kelompok.map((r) => ({ kelompok: r.label, jumlah_psn: r.jumlah, psn: '', dari: '', ke: '' })),
                    ...d.pelaksanaan.pergerakan.sorot.map((r) => ({ kelompok: r.kelompok === 'MEMBURUK' ? 'Memburuk' : 'Berhenti melapor', jumlah_psn: '', psn: r.nama, dari: this.labelStatus(r.dari), ke: this.labelStatus(r.ke) }))];
                case 'IKH_RKP': return d.ikhtisar.rkp.flatMap((t) => [{ dasar: t.dasar, tahap: t.judul, perubahan: 'Jumlah PSN dalam daftar', jumlah: t.jumlah, rincian: t.catatan, sumber: t.live ? 'Basis data SIRENDA' : 'Dokumen resmi' },
                    ...t.perubahan.map((p) => ({ dasar: t.dasar, tahap: t.judul, perubahan: `${p.arah === '+' ? 'Tambah' : 'Kurang'}: ${p.label}`, jumlah: p.jumlah, rincian: p.rincian, sumber: t.live ? 'Basis data SIRENDA' : 'Dokumen resmi' }))]);
                case 'IKH_WILAYAH': return d.ikhtisar.wilayah.kelompok.map((r) => ({ wilayah: r.label, jumlah_psn: r.jumlah, jumlah_pkpn: r.pkpn, investasi_rp_triliun: r.investasi_triliun }));
                case 'IKH_SKEMA': return d.ikhtisar.skema.map((r) => ({ skema: r.label, investasi_rp_triliun: r.investasi_triliun, persen: r.persen, jumlah_psn: r.jumlah, jumlah_pkpn: r.pkpn }));
                case 'IKH_SELESAI': return d.ikhtisar.selesai.kategori.flatMap((k) => k.psn.map((p) => ({ kategori: k.label, kode_psn: p.kode, psn: p.nama, sumber_isian: p.sumber ?? '', isian_kebutuhan_status: p.isian ?? '' })));
                case 'IKH_STATUS': return [...d.ikhtisar.status.tahap.map((r) => ({ kelompok: 'Tahapan', label: r.label, jumlah_psn: r.jumlah })), { kelompok: 'Tahapan', label: 'Keluar dari PSN', jumlah_psn: d.ikhtisar.status.keluar },
                    ...d.ikhtisar.status.target.map((r) => ({ kelompok: 'Target selesai (PSN belum selesai)', label: r.label, jumlah_psn: r.jumlah }))];
                default: return [];
            }
        },
    };
}
