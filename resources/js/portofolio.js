const angka = (v, d = 1) => (v === null || v === undefined ? '–' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: d }).format(v));

/** Tabel Portofolio PSN: paginasi server, urut, cari; state di query string. */
export function halamanPortofolio() {
    const q = new URLSearchParams(window.location.search);
    return {
        baris: [],
        meta: null,
        memuat: true,
        galat: null,
        angka,
        o: {
            q: q.get('q') || '',
            urut: q.get('urut') || 'nama',
            arah: q.get('arah') || 'asc',
            kritis: q.get('kritis') === '1',
            tahap: q.get('tahap') || '',
            nonaktif: q.get('nonaktif') === '1',
            page: +(q.get('page') || 1),
        },

        async init() {
            await this.$store.filter.muatOpsi();
            await this.muat();
            window.addEventListener('filter-berubah', () => { this.o.page = 1; this.muat(); });
        },

        qsEkstra() {
            const p = new URLSearchParams();
            if (this.o.q) p.set('q', this.o.q);
            if (this.o.urut !== 'nama') p.set('urut', this.o.urut);
            if (this.o.arah !== 'asc') p.set('arah', this.o.arah);
            if (this.o.kritis) p.set('kritis', '1');
            if (this.o.tahap) p.set('tahap', this.o.tahap);
            if (this.o.nonaktif) p.set('nonaktif', '1');
            if (this.o.page > 1) p.set('page', this.o.page);
            return p.toString();
        },

        qsLengkap() {
            return [this.$store.filter.qs(), this.qsEkstra()].filter(Boolean).join('&');
        },

        async muat() {
            this.memuat = true;
            this.galat = null;
            const qs = this.qsLengkap();
            window.history.replaceState({}, '', qs ? `${window.location.pathname}?${qs}` : window.location.pathname);
            try {
                const r = await window.axios.get(`/api/v1/proyek${qs ? '?' + qs : ''}`);
                this.baris = r.data.data;
                this.meta = r.data.meta;
            } catch (e) {
                this.galat = e.response?.data?.message || 'Data portofolio gagal dimuat.';
            } finally {
                this.memuat = false;
            }
        },

        cari() { this.o.page = 1; this.muat(); },
        ubah(k, v) { this.o[k] = v; this.o.page = 1; this.muat(); },
        urutkan(kolom) {
            this.o.arah = this.o.urut === kolom && this.o.arah === 'asc' ? 'desc' : 'asc';
            this.o.urut = kolom;
            this.o.page = 1;
            this.muat();
        },
        ariaSort(kolom) { return this.o.urut === kolom ? (this.o.arah === 'asc' ? 'ascending' : 'descending') : 'none'; },
        ke(hal) { if (hal >= 1 && hal <= (this.meta?.halaman_terakhir ?? 1)) { this.o.page = hal; this.muat(); } },

        tautanDetail(id) {
            const qs = this.qsLengkap();
            return `/proyek/${id}${qs ? '?' + qs : ''}`;
        },
        tautanUnduh(format) {
            const p = new URLSearchParams(this.qsLengkap());
            p.delete('page');
            p.set('format', format);
            return `/api/v1/proyek?${p.toString()}`;
        },
        labelStatus(k) {
            return { ON_TRACK: 'On Track', BERISIKO: 'Berisiko', TERLAMBAT: 'Terlambat', TANPA_DATA: 'Tanpa data' }[k] ?? k;
        },
    };
}
