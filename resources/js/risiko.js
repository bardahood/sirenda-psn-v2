const grafik = () => import('./dashboard/grafik');
const angka = (v, d = 0) => (v === null || v === undefined ? '–' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(v));

/** Halaman Risiko, Isu & Regulasi. Klik sel heatmap memfilter register. */
export function halamanRisiko() {
    return {
        ring: null, meta: null, register: [], metaReg: null, isu: [], metaIsu: null,
        memuat: true, galat: null, angka,
        o: { kategori: '', jenis: 'harapan', kemungkinan: null, dampak: null, level: '', q: '', page: 1, isuPage: 1, lewatTenggat: false },

        async init() {
            await this.$store.filter.muatOpsi();
            await this.muatSemua();
            window.addEventListener('filter-berubah', () => { this.o.page = 1; this.o.isuPage = 1; this.muatSemua(); });
        },

        qs(tambahan = {}) {
            const p = new URLSearchParams(this.$store.filter.qs());
            Object.entries(tambahan).forEach(([k, v]) => v !== null && v !== '' && v !== false && p.set(k, v === true ? 1 : v));
            return p.toString();
        },

        async muatSemua() {
            this.memuat = true;
            this.galat = null;
            try {
                const r = await window.axios.get(`/api/v1/risiko/ringkasan?${this.qs({ kategori: this.o.kategori })}`);
                this.ring = r.data.data;
                this.meta = r.data.meta;
                await Promise.all([this.muatRegister(), this.muatIsu()]);
                this.$nextTick(() => this.gambar());
            } catch (e) {
                this.galat = e.response?.data?.message || 'Data risiko gagal dimuat.';
            } finally {
                this.memuat = false;
            }
        },

        async muatRegister() {
            const o = this.o;
            const r = await window.axios.get(`/api/v1/risiko/register?${this.qs({ kategori: o.kategori, jenis: o.jenis, kemungkinan: o.kemungkinan, dampak: o.dampak, level: o.level, q: o.q, page: o.page })}`);
            this.register = r.data.data ?? [];
            this.metaReg = r.data.meta;
        },

        async muatIsu() {
            const r = await window.axios.get(`/api/v1/risiko/isu?${this.qs({ lewat_tenggat: this.o.lewatTenggat, page: this.o.isuPage })}`);
            this.isu = r.data.data ?? [];
            this.metaIsu = r.data.meta;
        },

        async gambar() {
            if (!this.ring) return;
            const { render, matriksRisiko } = await grafik();
            for (const jenis of ['harapan', 'aktual']) {
                const terpilih = this.o.jenis === jenis && this.o.kemungkinan ? { kemungkinan: this.o.kemungkinan, dampak: this.o.dampak } : null;
                render(this.$refs[`hm_${jenis}`], matriksRisiko(this.ring[jenis].sel, terpilih), (p) => this.pilihSel(jenis, p.value[1] + 1, p.value[0] + 1, p.value[2]));
            }
        },

        pilihSel(jenis, k, d, n) {
            const sama = this.o.jenis === jenis && this.o.kemungkinan === k && this.o.dampak === d;
            if (!n && !sama) return;
            Object.assign(this.o, sama ? { kemungkinan: null, dampak: null } : { jenis, kemungkinan: k, dampak: d, level: '' }, { page: 1 });
            this.muatRegister();
            this.gambar();
        },

        pilihLevel(jenis, level) {
            Object.assign(this.o, { jenis, level: this.o.level === level && this.o.jenis === jenis ? '' : level, kemungkinan: null, dampak: null, page: 1 });
            this.muatRegister();
            this.gambar();
        },

        hapusSorotan() {
            Object.assign(this.o, { kemungkinan: null, dampak: null, level: '', q: '', page: 1 });
            this.muatRegister();
            this.gambar();
        },

        ubahKategori() { this.o.page = 1; this.muatSemua(); },
        cari() { this.o.page = 1; this.muatRegister(); },
        keReg(h) { if (h >= 1 && h <= (this.metaReg?.halaman_terakhir ?? 1)) { this.o.page = h; this.muatRegister(); } },
        keIsu(h) { if (h >= 1 && h <= (this.metaIsu?.halaman_terakhir ?? 1)) { this.o.isuPage = h; this.muatIsu(); } },
        toggleTenggat() { this.o.lewatTenggat = !this.o.lewatTenggat; this.o.isuPage = 1; this.muatIsu(); },

        get keteranganSorotan() {
            const o = this.o;
            if (o.kemungkinan) return `Risiko ${o.jenis} dengan kemungkinan ${o.kemungkinan} × dampak ${o.dampak}`;
            if (o.level) return `Risiko ${o.jenis} level ${o.level}`;
            return null;
        },
        teksLevel(x) {
            if (!x?.level) return '–';
            return x.kemungkinan ? `${x.level} (${x.kemungkinan}×${x.dampak}=${x.kemungkinan * x.dampak})` : `${x.level} (tanpa skala)`;
        },
        kelasLevel(level) {
            return { Rendah: 'badge bg-green-50 text-green-800 ring-green-600/40', Sedang: 'badge bg-amber-50 text-amber-900 ring-amber-600/40', Tinggi: 'badge bg-orange-50 text-orange-900 ring-orange-600/40', 'Sangat Tinggi': 'badge bg-red-50 text-red-800 ring-red-600/40' }[level] ?? 'badge bg-slate-100 text-slate-700 ring-slate-400/40';
        },
        tautanPsn(id, tab = 'profil') {
            const q = this.$store.filter.qs();
            return `/proyek/${id}?${q ? q + '&' : ''}tab=${tab}`;
        },
        maksRegulasi() { return Math.max(1, ...(this.ring?.regulasi ?? []).map((t) => t.jumlah)); },
        statusIsu: (s) => ({ TERBUKA: 'Terbuka', PROSES: 'Dalam proses', SELESAI: 'Selesai' })[s] ?? s,
        tanggal: (t) => (t ? new Date(t + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Tanpa tenggat'),
    };
}
