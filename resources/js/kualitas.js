import { teksAktivitas, waktuLokal, pendekDirektorat } from './label';

const grafik = () => import('./dashboard/grafik');
const angka = (v, d = 1) => (v === null || v === undefined ? '–' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(v));
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export function halamanKualitas() {
    return {
        d: null,
        meta: null,
        memuat: true,
        galat: null,
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
                const r = await window.axios.get(`/api/v1/kualitas-data${qs ? '?' + qs : ''}`);
                this.d = r.data.data;
                this.meta = r.data.meta;
                this.$nextTick(() => this.gambar());
            } catch (e) {
                this.galat = e.response?.data?.message || 'Data kualitas gagal dimuat.';
            } finally {
                this.memuat = false;
            }
        },

        async gambar() {
            if (!this.d) return;
            const { render, batangHorizontal, heatmap } = await grafik();
            render(this.$refs.sektor, batangHorizontal(
                [...this.d.per_sektor].sort((a, b) => b.kelengkapan_persen - a.kelengkapan_persen).map((s) => ({ label: pendekDirektorat(s.label), jumlah: s.kelengkapan_persen, id: s.id })),
                { satuan: '% kelengkapan', klik: true, lebarLabel: 230, potong: true },
            ), (p) => {
                const urut = [...this.d.per_sektor].sort((a, b) => b.kelengkapan_persen - a.kelengkapan_persen).reverse();
                this.$store.filter.tambah('dit', urut[p.dataIndex].id);
            });
            const h = this.d.heatmap;
            const iBaris = Object.fromEntries(h.sektor.map((s, i) => [s.id, i]));
            const iKolom = Object.fromEntries(h.bagian.map((b, i) => [b.kode, i]));
            render(this.$refs.heatmap, heatmap(h.sektor.map((s) => pendekDirektorat(s.label)), h.bagian.map((b) => b.label),
                h.sel.map((c) => [iKolom[c.bagian], iBaris[c.sektor_id], c.persen])));
        },

        get tinggiSektor() { return `${Math.max(240, (this.d?.per_sektor.length ?? 0) * 26 + 40)}px`; },
        get tinggiHeatmap() { return `${Math.max(280, (this.d?.heatmap.sektor.length ?? 0) * 24 + 170)}px`; },

        pembanding() {
            const [th, bl] = (this.meta?.sebelumnya ?? '').split('-');
            return th ? `${BULAN[+bl - 1]} ${th}` : null;
        },
        teksDelta(k) {
            const d = k?.delta;
            if (!d || d.nilai === null) return 'Tidak ada pembanding';
            return `${d.nilai > 0 ? '+' : ''}${angka(d.nilai)}${d.satuan === 'pp' ? ' pp' : '%'} vs ${this.pembanding()}`;
        },
        kelasDelta(k) {
            const b = k?.delta?.baik;
            return b === true ? 'bg-green-50 text-green-800' : b === false ? 'bg-red-50 text-red-800' : 'bg-slate-100 text-slate-700';
        },
        tautanDetail(id) {
            const qs = this.$store.filter.qs();
            return `/proyek/${id}?${qs ? qs + '&' : ''}tab=profil`;
        },
        waktu: waktuLokal,
        teksAktivitas,
    };
}
