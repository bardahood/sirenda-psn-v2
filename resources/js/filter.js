// Filter global: disimpan di query string (tautan dapat dibagikan) dan berlaku lintas halaman.
const MULTI = ['prov', 'klaster', 'dit', 'status', 'dana'];
const TUNGGAL = ['periode', 'kat'];

export function bacaUrl(search = window.location.search) {
    const q = new URLSearchParams(search);
    const nilai = {};
    TUNGGAL.forEach((k) => (nilai[k] = q.get(k) || ''));
    MULTI.forEach((k) => (nilai[k] = (q.get(k) || '').split(',').filter(Boolean)));
    return nilai;
}

export function keQuery(nilai) {
    const q = new URLSearchParams();
    TUNGGAL.forEach((k) => nilai[k] && q.set(k, nilai[k]));
    MULTI.forEach((k) => nilai[k]?.length && q.set(k, nilai[k].join(',')));
    return q.toString().replace(/%2C/g, ',');
}

export function filterStore() {
    return {
        nilai: bacaUrl(),
        opsi: { periode: [], provinsi: [], klaster: [], direktorat: [], dana: [], kategori: [], status: [] },
        siap: false,

        async muatOpsi() {
            const r = await window.axios.get('/api/v1/filter-opsi');
            this.opsi = r.data.data;
            this.siap = true;
        },

        qs() {
            return keQuery(this.nilai);
        },

        /** URL halaman lain dengan filter yang sama (drill-down / navigasi). */
        tautan(path) {
            const q = this.qs();
            return q ? `${path}?${q}` : path;
        },

        aktif() {
            return MULTI.some((k) => this.nilai[k].length) || TUNGGAL.some((k) => this.nilai[k]);
        },

        set(k, v) {
            this.nilai[k] = v;
            this.terapkan();
        },

        /** Filter silang: klik batang/irisan/provinsi menambah nilai ke filter global. */
        tambah(k, v) {
            v = String(v);
            if (!this.nilai[k].includes(v)) {
                this.nilai[k] = [...this.nilai[k], v];
                this.terapkan();
            }
        },

        toggle(k, v) {
            v = String(v);
            this.nilai[k] = this.nilai[k].includes(v) ? this.nilai[k].filter((x) => x !== v) : [...this.nilai[k], v];
            this.terapkan();
        },

        reset() {
            this.nilai = bacaUrl('');
            this.terapkan();
        },

        terapkan() {
            const q = this.qs();
            window.history.replaceState({}, '', q ? `${window.location.pathname}?${q}` : window.location.pathname);
            window.dispatchEvent(new CustomEvent('filter-berubah'));
        },

        label(jenisOpsi, nilai) {
            return this.opsi[jenisOpsi]?.find((o) => String(o.nilai) === String(nilai))?.label ?? nilai;
        },
    };
}

/** Dropdown multi-pilih dengan pencarian, terhubung ke satu kunci filter global. */
export function multiPilih(kunci, jenisOpsi, judul) {
    return {
        buka: false,
        cari: '',
        kunci,
        jenisOpsi,
        judul,
        get daftar() {
            const c = this.cari.toLowerCase();
            return (this.$store.filter.opsi[this.jenisOpsi] || []).filter((o) => o.label.toLowerCase().includes(c));
        },
        get terpilih() {
            return this.$store.filter.nilai[this.kunci];
        },
        get ringkas() {
            const n = this.terpilih.length;
            if (!n) return 'Semua';
            if (n === 1) return this.$store.filter.label(this.jenisOpsi, this.terpilih[0]);
            return `${n} dipilih`;
        },
        pilih(v) {
            this.$store.filter.toggle(this.kunci, v);
        },
        bersihkan() {
            this.$store.filter.set(this.kunci, []);
        },
    };
}
