// Peta sebaran PSN (Leaflet, dibundel). Sementara memakai simbol lingkaran proporsional di
// titik tengah provinsi; bila berkas GeoJSON provinsi tersedia, otomatis menjadi choropleth.
// Leaflet (beserta CSS-nya) dimuat terpisah hanya saat komponen peta dipakai.
let L = null;
const muatLeaflet = async () => {
    if (!L) {
        [L] = await Promise.all([import('leaflet').then((m) => m.default), import('leaflet/dist/leaflet.css')]);
    }
    return L;
};

const AKSEN = '#1F6FD1';
const USULAN = '#eb6834';
const RAMP = ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b'];
const angka = (v) => new Intl.NumberFormat('id-ID').format(v ?? 0);

export function petaSebaran(cfg) {
    // Objek Leaflet disimpan di closure, bukan state Alpine (proxy reaktif merusak Leaflet).
    let peta = null;
    let lapisan = null;
    let geo = null;

    return {
        data: cfg.data ?? [],
        meta: null,
        memuat: false,

        async init() {
            await muatLeaflet();
            peta = L.map(this.$refs.peta, { zoomSnap: 0.25, scrollWheelZoom: false, attributionControl: true }).setView([-2.5, 118], 4.5);
            if (cfg.tileUrl) {
                L.tileLayer(cfg.tileUrl, { attribution: cfg.atribusi, maxZoom: 10 }).addTo(peta);
            }
            geo = cfg.geojson ? await fetch(cfg.geojson).then((r) => r.json()).catch(() => null) : null;
            if (cfg.sumber) {
                await this.$store.filter.muatOpsi();
                await this.muat();
                window.addEventListener('filter-berubah', () => this.muat());
            } else {
                this.gambar();
            }
            new ResizeObserver(() => peta.invalidateSize()).observe(this.$refs.peta);
        },

        async muat() {
            this.memuat = true;
            const qs = this.$store.filter.qs();
            const r = await window.axios.get(`${cfg.sumber}${qs ? '?' + qs : ''}`);
            this.data = r.data.data;
            this.meta = r.data.meta;
            this.memuat = false;
            this.gambar();
        },

        gambar() {
            if (lapisan) peta.removeLayer(lapisan);
            const maks = Math.max(1, ...this.data.map((d) => d.jumlah));
            const perKode = Object.fromEntries(this.data.map((d) => [d.kode, d]));
            const teks = (d) => `<b>${d.label}</b><br>${angka(d.jumlah)} PSN${d.sejenis !== undefined ? `<br>${angka(d.sejenis)} PSN klaster sama` : ''}${d.usulan ? '<br><b>Lokasi usulan</b>' : ''}`;
            const klik = (d) => cfg.sumber && this.$store.filter.tambah('prov', d.kode);

            if (geo) {
                const warna = (n) => (n ? RAMP[Math.min(RAMP.length - 1, Math.floor((n / maks) * (RAMP.length - 1)))] : '#f1f5f9');
                lapisan = L.geoJSON(geo, {
                    style: (f) => {
                        const d = perKode[String(f.properties[cfg.kodeProp])];
                        return { color: d?.usulan ? USULAN : '#fff', weight: d?.usulan ? 3 : 1, fillColor: warna(d?.jumlah ?? 0), fillOpacity: 0.9 };
                    },
                    onEachFeature: (f, layer) => {
                        const d = perKode[String(f.properties[cfg.kodeProp])];
                        if (d) layer.bindTooltip(teks(d), { sticky: true }).on('click', () => klik(d));
                    },
                }).addTo(peta);
                return;
            }

            lapisan = L.layerGroup(this.data.filter((d) => d.lat !== null && (d.jumlah > 0 || d.usulan)).map((d) => L.circleMarker([d.lat, d.lng], {
                radius: d.jumlah ? 5 + 22 * Math.sqrt(d.jumlah / maks) : 6,
                color: d.usulan ? USULAN : '#fff',
                weight: d.usulan ? 3 : 1.5,
                fillColor: d.jumlah ? AKSEN : '#fff',
                fillOpacity: 0.6,
            }).bindTooltip(teks(d)).on('click', () => klik(d)))).addTo(peta);
        },

        get daftar() {
            return [...this.data].filter((d) => d.jumlah > 0).sort((a, b) => b.jumlah - a.jumlah);
        },
        angka,
    };
}
