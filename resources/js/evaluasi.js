// Grafik pergerakan status akhir evaluasi PSN per hari (batang bertumpuk). Warna = warna badge status.
const grafik = () => import('./dashboard/grafik');

const WARNA_STATUS = {
    PROSES_PENGECEKAN: '#0284c7',
    BUTUH_DATA: '#d97706',
    VERIFIKASI_PJ: '#ea580c',
    SIAP_DIANALISIS: '#4f46e5',
    DIPERTAHANKAN: '#16a34a',
    DIKELUARKAN: '#b91c1c',
};

export function grafikStatusHarian(hari, label) {
    return {
        async init() {
            const { render, WARNA } = await grafik();
            const tanggal = hari.map((h) => new Date(h.tanggal + 'T00:00:00').toLocaleDateString('id-ID', { day: 'numeric', month: 'short' }));
            render(this.$refs.kanvas, {
                grid: { left: 8, right: 8, top: 44, bottom: 8, containLabel: true },
                legend: { top: 0, left: 0, itemWidth: 10, itemHeight: 10, textStyle: { fontSize: 11, color: WARNA.teks } },
                tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, backgroundColor: '#fff', borderColor: WARNA.grid, textStyle: { color: WARNA.teks, fontSize: 12 } },
                xAxis: { type: 'category', data: tanggal, axisTick: { show: false }, axisLine: { lineStyle: { color: WARNA.grid } }, axisLabel: { color: WARNA.teks, fontSize: 11 } },
                yAxis: { type: 'value', minInterval: 1, splitLine: { lineStyle: { color: WARNA.grid } }, axisLabel: { color: WARNA.teksSamar, fontSize: 11 } },
                series: Object.keys(label).map((k) => ({
                    name: label[k], type: 'bar', stack: 'status', barMaxWidth: 32,
                    itemStyle: { color: WARNA_STATUS[k] },
                    emphasis: { focus: 'series' },
                    data: hari.map((h) => h.status[k] ?? 0),
                })),
            });
        },
    };
}

// Isian langsung sel kertas kerja (event delegation: baris diganti HTML dari server setelah simpan).
export function kertasKerja(meta, urlPola) {
    return {
        meta, buka: false, td: null, kunci: null, tipe: null, opsi: null, judul: '', nilai: '', multi: [], lain: '',
        posisi: { top: 0, left: 0 }, menyimpan: false, pesan: '', galat: false, galatEditor: '',

        klik(ev) {
            const td = ev.target.closest('td[data-edit="1"]');
            if (!td || ev.target.closest('a')) return;
            ev.preventDefault();
            if (td.dataset.tautan) { window.location.href = td.dataset.tautan; return; }
            const m = this.meta[td.dataset.kunci];
            if (!m) return;
            this.td = td;
            this.kunci = td.dataset.kunci;
            this.tipe = m.tipe;
            this.opsi = m.opsi;
            const th = td.closest('table').querySelectorAll('thead tr:last-child th')[td.cellIndex];
            const nama = td.closest('tr').querySelector('td:nth-child(3)')?.innerText.trim();
            this.judul = `${th ? th.innerText.trim() : this.kunci} · ${nama ?? ''}`;
            let v = null;
            try { v = JSON.parse(td.dataset.nilai ?? 'null'); } catch { v = null; }
            this.multi = Array.isArray(v) ? v.map(String) : [];
            this.nilai = Array.isArray(v) || v === null ? '' : String(v);
            try { this.lain = JSON.parse(td.dataset.lain ?? 'null') ?? ''; } catch { this.lain = ''; }
            this.galatEditor = '';
            const r = td.getBoundingClientRect();
            const lebar = Math.min(352, window.innerWidth * 0.92);
            this.posisi = { top: Math.min(r.bottom + 4, window.innerHeight - 320), left: Math.max(8, Math.min(r.left, window.innerWidth - lebar - 8)) };
            this.buka = true;
            this.$nextTick(() => this.$refs.masukan?.focus());
        },

        tutup() {
            this.buka = false;
            this.td?.focus();
        },

        async simpan() {
            if (!this.td || this.menyimpan) return;
            const tr = this.td.closest('tr');
            this.menyimpan = true;
            this.galatEditor = '';
            try {
                const r = await window.axios.patch(urlPola.replace('__ID__', tr.dataset.psn), {
                    kunci: this.kunci, nilai: this.tipe === 'multi' ? this.multi : this.nilai, nilai_lain: this.lain, no: Number(tr.dataset.no),
                });
                const kunci = this.kunci;
                tr.outerHTML = r.data.html;
                this.buka = false;
                this.galat = false;
                this.pesan = r.data.message;
                this.$nextTick(() => this.$el.querySelector(`tr[data-psn="${tr.dataset.psn}"] td[data-kunci="${kunci}"]`)?.focus());
            } catch (e) {
                const d = e.response?.data;
                this.galatEditor = (d?.errors && Object.values(d.errors)[0]?.[0]) || d?.message || 'Gagal menyimpan.';
            } finally {
                this.menyimpan = false;
            }
        },
    };
}
