// Grafik ECharts (tree-shaken). Aturan visual: satu seri = satu warna (aksen),
// tren dua seri memakai palet tervalidasi (biru #2a78d6, oranye #eb6834),
// sudut data 4px, grid/axis samar, tooltip per-batang & crosshair untuk garis.
import * as echarts from 'echarts/core';
import { BarChart, LineChart, HeatmapChart, PieChart, ScatterChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent, MarkLineComponent, MarkPointComponent, MarkAreaComponent, VisualMapComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, LineChart, HeatmapChart, PieChart, ScatterChart, GridComponent, TooltipComponent, LegendComponent, MarkLineComponent, MarkPointComponent, MarkAreaComponent, VisualMapComponent, CanvasRenderer]);

export const WARNA = {
    aksen: '#1F6FD1',
    seri1: '#2a78d6',
    seri2: '#eb6834',
    teks: '#334155',
    teksSamar: '#64748b',
    grid: '#e2e8f0',
};

const fmt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 });
export const angka = (v, digit = 1) => (v === null || v === undefined ? '–' : new Intl.NumberFormat('id-ID', { maximumFractionDigits: digit, minimumFractionDigits: 0 }).format(v));

const dasar = {
    textStyle: { fontFamily: '"Plus Jakarta Sans Variable", sans-serif', color: WARNA.teks },
    animationDuration: 300,
    tooltip: {
        backgroundColor: '#fff',
        borderColor: WARNA.grid,
        textStyle: { color: WARNA.teks, fontSize: 12 },
        extraCssText: 'box-shadow:0 4px 12px rgba(15,23,42,.08);border-radius:8px;',
    },
};

const instans = new WeakMap();

export function render(el, opsi, onKlik) {
    let c = instans.get(el);
    if (!c) {
        c = echarts.init(el, null, { renderer: 'canvas' });
        instans.set(el, c);
        new ResizeObserver(() => c.resize()).observe(el);
    }
    c.off('click');
    if (onKlik) c.on('click', (p) => onKlik(p));
    c.setOption({ ...dasar, ...opsi }, true);
    return c;
}

export function png(el, nama) {
    const c = instans.get(el);
    if (!c) return;
    const a = document.createElement('a');
    a.href = c.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: '#fff' });
    a.download = `${nama}.png`;
    a.click();
}

/** Batang horizontal satu seri; kategori dengan nilai terbesar di atas. */
export function batangHorizontal(baris, { nilai = 'jumlah', satuan = 'PSN', klik = false, sumbuX = true, lebarLabel = 170, potong = false } = {}) {
    const data = [...baris].reverse();
    return {
        grid: { left: 8, right: 48, top: 8, bottom: 8, containLabel: true },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => `${p.name}<br/><b>${angka(p.value)}</b> ${satuan}${klik ? '<br/><span style="color:#64748b">Klik untuk memfilter</span>' : ''}` },
        xAxis: { type: 'value', minInterval: satuan === 'PSN' ? 1 : 0, splitLine: { show: sumbuX, lineStyle: { color: WARNA.grid } }, axisLabel: { show: sumbuX, color: WARNA.teksSamar, fontSize: 11 } },
        yAxis: {
            type: 'category',
            data: data.map((d) => d.label),
            axisTick: { show: false },
            axisLine: { lineStyle: { color: WARNA.grid } },
            // Label panjang dibungkus dua baris, bukan dipotong, agar tidak ambigu.
            axisLabel: { color: WARNA.teks, fontSize: 11, width: lebarLabel, overflow: potong ? 'truncate' : 'break', lineHeight: 13 },
        },
        series: [{
            type: 'bar',
            data: data.map((d) => d[nilai]),
            barMaxWidth: 18,
            itemStyle: { color: WARNA.aksen, borderRadius: [0, 4, 4, 0] },
            emphasis: { itemStyle: { color: '#1a5db0' } },
            cursor: klik ? 'pointer' : 'default',
            label: { show: true, position: 'right', color: WARNA.teks, fontSize: 11, formatter: (p) => angka(p.value) },
        }],
    };
}

export function kolom(baris, { satuan = 'PSN' } = {}) {
    return {
        grid: { left: 8, right: 8, top: 24, bottom: 8, containLabel: true },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => `${p.name}<br/><b>${angka(p.value)}</b> ${satuan}` },
        xAxis: { type: 'category', data: baris.map((d) => d.label), axisTick: { show: false }, axisLine: { lineStyle: { color: WARNA.grid } }, axisLabel: { color: WARNA.teks, fontSize: 11, interval: 0 } },
        yAxis: { type: 'value', minInterval: 1, splitLine: { lineStyle: { color: WARNA.grid } }, axisLabel: { color: WARNA.teksSamar, fontSize: 11 } },
        series: [{
            type: 'bar', data: baris.map((d) => d.jumlah), barMaxWidth: 28,
            itemStyle: { color: WARNA.aksen, borderRadius: [4, 4, 0, 0] },
            label: { show: true, position: 'top', color: WARNA.teks, fontSize: 11 },
        }],
    };
}

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/** P5: rencana vs realisasi kumulatif; satu sumbu (persen), legenda + label ujung. */
export function tren(bulan) {
    const seri = (nama, kunci, warna, putus) => ({
        name: nama,
        type: 'line',
        data: bulan.map((b) => b[kunci]),
        connectNulls: false,
        symbol: 'circle',
        symbolSize: 8,
        lineStyle: { width: 2, color: warna, type: putus ? 'dashed' : 'solid' },
        itemStyle: { color: warna, borderColor: '#fff', borderWidth: 2 },
        endLabel: { show: true, formatter: (p) => `${nama} ${angka(p.value)}%`, color: WARNA.teks, fontSize: 11 },
    });
    return {
        grid: { left: 8, right: 110, top: 32, bottom: 8, containLabel: true },
        legend: { top: 0, left: 0, itemWidth: 16, textStyle: { color: WARNA.teks, fontSize: 12 } },
        tooltip: { ...dasar.tooltip, trigger: 'axis', axisPointer: { type: 'line', lineStyle: { color: WARNA.teksSamar } }, valueFormatter: (v) => (v === null || v === undefined ? 'tidak ada snapshot' : `${angka(v)}%`) },
        xAxis: { type: 'category', data: BULAN, boundaryGap: false, axisTick: { show: false }, axisLine: { lineStyle: { color: WARNA.grid } }, axisLabel: { color: WARNA.teksSamar, fontSize: 11 } },
        yAxis: { type: 'value', min: 0, max: (v) => Math.max(100, Math.ceil(v.max / 10) * 10), axisLabel: { formatter: '{value}%', color: WARNA.teksSamar, fontSize: 11 }, splitLine: { lineStyle: { color: WARNA.grid } } },
        series: [seri('Rencana', 'rencana_persen', WARNA.seri2, true), seri('Realisasi', 'realisasi_persen', WARNA.seri1, false)],
    };
}

/** Kurva S proyek: tren rencana vs realisasi; penanda merah pada titik terakhir bila Terlambat. */
export function kurvaS(bulan, terlambat) {
    const opsi = tren(bulan);
    if (terlambat) {
        const idx = bulan.map((b) => b.realisasi_persen).findLastIndex((v) => v !== null && v !== undefined);
        if (idx >= 0) {
            opsi.series[1].markPoint = {
                symbol: 'pin', symbolSize: 40, itemStyle: { color: '#dc2626' },
                label: { formatter: '!', color: '#fff', fontWeight: 700 },
                data: [{ coord: [idx, bulan[idx].realisasi_persen], name: 'Terlambat' }],
                tooltip: { formatter: 'Status Terlambat pada cut-off ini' },
            };
        }
    }
    return opsi;
}

// Ramp sekuensial satu hue (biru, terang -> gelap) untuk besaran 0-100%.
const RAMP_BIRU = ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95', '#0d366b'];

/** Heatmap baris x kolom, nilai persen 0-100; sel kosong (null) abu-abu. */
export function heatmap(baris, kolom, sel) {
    return {
        grid: { left: 8, right: 16, top: 8, bottom: 56, containLabel: true },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => `${baris[p.value[1]]}<br/>${kolom[p.value[0]]}: <b>${p.value[2] === null ? 'tidak ada data' : angka(p.value[2]) + '%'}</b>` },
        xAxis: { type: 'category', data: kolom, position: 'top', axisTick: { show: false }, axisLine: { show: false }, axisLabel: { rotate: 60, fontSize: 10, color: WARNA.teks, interval: 0, width: 120, overflow: 'truncate' } },
        yAxis: { type: 'category', data: baris, inverse: true, axisTick: { show: false }, axisLine: { show: false }, axisLabel: { fontSize: 10, color: WARNA.teks, width: 190, overflow: 'truncate' } },
        visualMap: { min: 0, max: 100, calculable: false, orient: 'horizontal', left: 'center', bottom: 0, itemHeight: 160, text: ['100%', '0%'], textStyle: { fontSize: 11, color: WARNA.teksSamar }, inRange: { color: RAMP_BIRU } },
        series: [{
            type: 'heatmap',
            // Teks sel putih di atas >= 50% (ramp gelap), gelap di bawahnya, agar tetap terbaca.
            data: sel.map((v) => ({ value: v, label: { color: v[2] !== null && v[2] >= 50 ? '#fff' : '#0f172a' } })),
            itemStyle: { borderColor: '#fff', borderWidth: 2 },
            label: { show: true, fontSize: 9, formatter: (p) => (p.value[2] === null ? '' : Math.round(p.value[2])) },
            emphasis: { itemStyle: { borderColor: '#0f172a', borderWidth: 1 } },
        }],
    };
}

/** Perbandingan nilai akhir antar-usulan: usulan yang sedang dilihat disorot (aksen), lainnya abu-abu. */
export function batangSorot(baris) {
    const data = [...baris].reverse();
    return {
        grid: { left: 8, right: 48, top: 8, bottom: 8, containLabel: true },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => `${p.name}<br/>Nilai akhir <b>${angka(p.value, 2)}</b>${data[p.dataIndex].ditolak ? '<br/>Ditolak (gate)' : ''}` },
        xAxis: { type: 'value', min: 0, max: 100, splitLine: { lineStyle: { color: WARNA.grid } }, axisLabel: { color: WARNA.teksSamar, fontSize: 11 } },
        yAxis: { type: 'category', data: data.map((d) => d.label), axisTick: { show: false }, axisLine: { lineStyle: { color: WARNA.grid } }, axisLabel: { color: WARNA.teks, fontSize: 11, width: 200, overflow: 'truncate' } },
        series: [{
            type: 'bar', barMaxWidth: 16,
            data: data.map((d) => ({ value: d.nilai, itemStyle: { color: d.ini ? WARNA.aksen : '#94a3b8', borderRadius: [0, 4, 4, 0] } })),
            label: { show: true, position: 'right', fontSize: 11, color: WARNA.teks, formatter: (p) => `${angka(p.value, 1)}${data[p.dataIndex].ditolak ? ' · ditolak' : ''}` },
        }],
    };
}

// Zona matriks risiko per level (tint muda; teks gelap agar kontras). Level selalu juga tertulis di tooltip & legenda.
const ZONA_RISIKO = { Rendah: '#dcfce7', Sedang: '#fef3c7', Tinggi: '#fed7aa', 'Sangat Tinggi': '#fecaca' };
const levelSkor = (s) => (s <= 4 ? 'Rendah' : s <= 9 ? 'Sedang' : s <= 16 ? 'Tinggi' : 'Sangat Tinggi');

/** Matriks risiko 5x5: sumbu X dampak, Y kemungkinan; isi = jumlah risiko; sel terpilih ditebalkan. */
export function matriksRisiko(sel, terpilih) {
    const jumlah = Object.fromEntries(sel.map((c) => [`${c.kemungkinan}-${c.dampak}`, c.jumlah]));
    const data = [];
    for (let k = 1; k <= 5; k++) {
        for (let d = 1; d <= 5; d++) {
            const aktif = terpilih && terpilih.kemungkinan === k && terpilih.dampak === d;
            data.push({
                value: [d - 1, k - 1, jumlah[`${k}-${d}`] ?? 0],
                itemStyle: { color: ZONA_RISIKO[levelSkor(k * d)], borderColor: aktif ? '#0E2747' : '#fff', borderWidth: aktif ? 3 : 2 },
            });
        }
    }
    return {
        grid: { left: 44, right: 8, top: 8, bottom: 44 },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => {
            const [d, k, n] = p.value;
            const skor = (k + 1) * (d + 1);
            return `Kemungkinan ${k + 1} × Dampak ${d + 1} = ${skor} (${levelSkor(skor)})<br/><b>${n}</b> risiko${n ? '<br/><span style="color:#64748b">Klik untuk memfilter register</span>' : ''}`;
        } },
        xAxis: { type: 'category', data: ['1', '2', '3', '4', '5'], name: 'Dampak', nameLocation: 'middle', nameGap: 28, nameTextStyle: { color: WARNA.teks, fontSize: 11 }, axisTick: { show: false }, axisLine: { show: false }, axisLabel: { color: WARNA.teks } },
        yAxis: { type: 'category', data: ['1', '2', '3', '4', '5'], name: 'Kemungkinan', nameLocation: 'middle', nameGap: 24, nameTextStyle: { color: WARNA.teks, fontSize: 11 }, axisTick: { show: false }, axisLine: { show: false }, axisLabel: { color: WARNA.teks } },
        series: [{ type: 'heatmap', data, cursor: 'pointer', label: { show: true, color: '#0f172a', fontSize: 13, fontWeight: 600, formatter: (p) => (p.value[2] ? p.value[2] : '') } }],
    };
}

/** Sparkline kartu KPI: satu seri, tanpa sumbu; titik terakhir ditandai. Tooltip per cut-off. */
export function garisMini(titik, warna, format = (v) => angka(v)) {
    return {
        animation: false,
        grid: { left: 2, right: 6, top: 6, bottom: 2 },
        tooltip: { ...dasar.tooltip, trigger: 'axis', axisPointer: { type: 'line', lineStyle: { color: WARNA.grid } }, formatter: (p) => `Cut-off ${p[0].name}<br/><b>${format(p[0].value)}</b>` },
        xAxis: { type: 'category', show: false, boundaryGap: false, data: titik.map((t) => t.label) },
        yAxis: { type: 'value', show: false, scale: true },
        series: [{
            type: 'line', data: titik.map((t) => t.nilai), smooth: 0.3, symbol: 'circle', symbolSize: (v, p) => (p.dataIndex === titik.length - 1 ? 7 : 0),
            lineStyle: { width: 2, color: warna }, itemStyle: { color: warna, borderColor: '#fff', borderWidth: 2 },
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: `${warna}40` }, { offset: 1, color: `${warna}00` }]) },
        }],
    };
}

/** Donat komposisi (bagian dari keseluruhan); celah 2px antar-irisan; label di legenda HTML. */
export function donat(segmen, { satuan = 'Rp triliun', klik = false } = {}) {
    return {
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => `${p.name}<br/><b>${angka(p.value)}</b> ${satuan} (${angka(p.percent)}%)<br/>${angka(p.data.jumlah, 0)} PSN${klik && p.data.kode ? '<br/><span style="color:#64748b">Klik untuk memfilter</span>' : ''}` },
        series: [{
            type: 'pie', radius: ['62%', '90%'], center: ['50%', '50%'], avoidLabelOverlap: false, label: { show: false }, labelLine: { show: false },
            itemStyle: { borderColor: '#fff', borderWidth: 2, borderRadius: 3 }, cursor: klik ? 'pointer' : 'default',
            emphasis: { scale: true, scaleSize: 4 },
            data: segmen.map((d) => ({ name: d.label, value: d.nilai, jumlah: d.jumlah, kode: d.kode, itemStyle: { color: d.warna } })),
        }],
    };
}

/**
 * Peta ringkas tanpa basemap: simbol provinsi di titik tengahnya (bujur, lintang), ukuran & gelap
 * mengikuti kelas jumlah PSN (sekuensial satu hue), dan label ringkas per pulau.
 */
export function petaRingkas(prov, pulau, kelas) {
    const RAMP = ['#0b4a9c', '#1F6FD1', '#5b9be3', '#a9c9f0'];
    const UKURAN = [22, 16, 11, 7];
    const kelasDari = (n) => kelas.findIndex((k) => n >= k.min);
    const titik = prov.filter((p) => p.lat !== null && p.lng !== null);
    // Posisi label pulau agar tidak menutupi simbol utama.
    const POSISI = { Sumatera: 'left', Jawa: 'bottom', 'Bali & Nusa Tenggara': 'right', Kalimantan: 'top', Sulawesi: 'right', Maluku: 'top', Papua: 'top' };
    return {
        grid: { left: 8, right: 8, top: 8, bottom: 8 },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => (p.seriesIndex === 0 ? `${p.data.nama}<br/><b>${angka(p.data.jumlah, 0)}</b> PSN<br/><span style="color:#64748b">Klik untuk memfilter</span>` : `${p.data.nama}<br/><b>${angka(p.data.jumlah, 0)}</b> PSN (dihitung sekali per pulau)`) },
        xAxis: { type: 'value', min: 94, max: 142, show: false },
        yAxis: { type: 'value', min: -14.5, max: 7.5, show: false },
        series: [
            {
                type: 'scatter', cursor: 'pointer', z: 2,
                data: titik.map((p) => {
                    const k = kelasDari(p.jumlah);
                    return { value: [p.lng, p.lat], nama: p.label, kode: p.kode, jumlah: p.jumlah, symbolSize: k < 0 ? 5 : UKURAN[k], itemStyle: { color: k < 0 ? '#cbd5e1' : RAMP[k], borderColor: '#fff', borderWidth: 1.5 } };
                }),
            },
            {
                type: 'scatter', symbolSize: 1, z: 3, silent: false, itemStyle: { color: 'transparent' },
                data: pulau.filter((p) => p.lat !== null).map((p) => ({
                    value: [p.lng, p.lat], nama: p.label, jumlah: p.jumlah,
                    label: {
                        show: true, position: POSISI[p.label] ?? 'top', distance: 14, formatter: `{n|${p.label}}\n{v|${angka(p.jumlah, 0)} PSN}`,
                        backgroundColor: '#fff', borderColor: WARNA.grid, borderWidth: 1, borderRadius: 6, padding: [4, 6],
                        shadowColor: 'rgba(15,23,42,.08)', shadowBlur: 6,
                        rich: { n: { fontSize: 10, color: WARNA.teksSamar, lineHeight: 13 }, v: { fontSize: 11, fontWeight: 700, color: '#0f172a', lineHeight: 14 } },
                    },
                })),
            },
        ],
    };
}

/**
 * Peta wilayah gaya paparan: titik provinsi samar sebagai latar, gelembung per wilayah (luas ~ jumlah PSN)
 * berlabel jumlah PSN, PKPN, dan nilai investasi. "Nasional & Lintas Pulau" ditaruh di pojok kanan atas.
 */
export function petaWilayah(kelompok, provinsi) {
    const POSISI = { Sumatera: 'left', Jawa: 'left', Kalimantan: 'top', Sulawesi: 'top', 'Bali & Nusa Tenggara': 'right', 'Maluku & Papua': 'bottom' };
    const GESER = { Kalimantan: [-34, 0], Sulawesi: [26, 0] };
    const maks = Math.max(1, ...kelompok.map((k) => k.jumlah));
    const ukuran = (n) => (n > 0 ? 14 + Math.sqrt(n / maks) * 30 : 8);
    const label = (k, posisi) => ({
        show: true, position: posisi, distance: 6, offset: GESER[k.label] ?? [0, 0],
        formatter: `{n|${k.label}}\n{v|${angka(k.jumlah, 0)} PSN} {p|${angka(k.pkpn, 0)} PKPN}\n{r|Rp ${angka(k.investasi_triliun, 1)} T}`,
        backgroundColor: '#fff', borderColor: WARNA.grid, borderWidth: 1, borderRadius: 6, padding: [3, 6],
        shadowColor: 'rgba(15,23,42,.08)', shadowBlur: 6,
        rich: {
            n: { fontSize: 10, color: WARNA.teksSamar, lineHeight: 13 },
            v: { fontSize: 11, fontWeight: 700, color: '#0f172a', lineHeight: 14 },
            p: { fontSize: 10, color: '#6d28d9', lineHeight: 14 },
            r: { fontSize: 10, color: '#334155', lineHeight: 13 },
        },
    });
    const tooltip = (d) => `${d.label}<br/><b>${angka(d.jumlah, 0)}</b> PSN (${angka(d.pkpn, 0)} PKPN)<br/>Rp ${angka(d.investasi_triliun, 1)} triliun`;
    const wilayah = kelompok.filter((k) => !k.lintas && k.lat !== null);
    const lintas = kelompok.find((k) => k.lintas);
    return {
        grid: { left: 4, right: 4, top: 4, bottom: 4 },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => (p.seriesIndex === 0 ? p.data.nama : tooltip(p.data.k)) },
        xAxis: { type: 'value', min: 91, max: 143, show: false },
        yAxis: { type: 'value', min: -15.5, max: 9, show: false },
        series: [
            { type: 'scatter', silent: true, z: 1, symbolSize: 5, itemStyle: { color: '#cbd5e1' }, data: provinsi.map((p) => ({ value: [p.lng, p.lat], nama: p.label })) },
            {
                type: 'scatter', z: 3,
                data: wilayah.map((k) => ({ value: [k.lng, k.lat], k, symbolSize: ukuran(k.jumlah), itemStyle: { color: '#1F6FD1', opacity: 0.85, borderColor: '#fff', borderWidth: 1.5 }, label: label(k, POSISI[k.label] ?? 'top') }))
                    .concat(lintas ? [{ value: [140, 6.8], k: lintas, symbolSize: ukuran(lintas.jumlah), itemStyle: { color: '#0d2b55', opacity: 0.9, borderColor: '#fff', borderWidth: 1.5 }, label: label(lintas, 'left') }] : []),
            },
        ],
    };
}

/** Tren bulanan rencana (putus-putus) vs realisasi (garis + area) kumulatif; satu sumbu persen. */
export function trenArea(bulan) {
    const opsi = tren(bulan);
    opsi.grid = { left: 8, right: 16, top: 36, bottom: 8, containLabel: true };
    opsi.series.forEach((s) => { s.endLabel = { show: false }; });
    opsi.series[1].areaStyle = { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: `${WARNA.seri1}33` }, { offset: 1, color: `${WARNA.seri1}05` }]) };
    return opsi;
}

/** Kurva S portofolio + proyeksi realisasi Desember (garis putus-putus biru dari titik terakhir). */
export function kurvaProyeksi(bulan, proyeksi) {
    const opsi = trenArea(bulan);
    opsi.grid = { left: 8, right: 16, top: 36, bottom: 8, containLabel: true };
    if (proyeksi) {
        const data = bulan.map((b) => (b.bulan === proyeksi.bulan_awal ? proyeksi.nilai_awal : b.bulan === 12 ? proyeksi.desember : null));
        opsi.series.push({
            name: 'Proyeksi realisasi', type: 'line', data, connectNulls: true, symbol: (v, p) => (p.dataIndex === 11 ? 'circle' : 'none'), symbolSize: 8,
            lineStyle: { width: 2, type: [4, 4], color: WARNA.seri1, opacity: 0.8 }, itemStyle: { color: '#fff', borderColor: WARNA.seri1, borderWidth: 2 },
            label: { show: true, position: 'top', formatter: (p) => (p.dataIndex === 11 ? `${angka(p.value)}%` : ''), color: WARNA.seri1, fontSize: 11, fontWeight: 700 },
            tooltip: { valueFormatter: (v) => (v === null || v === undefined ? '–' : `${angka(v)}% (proyeksi laju saat ini)`) },
        });
    }
    return opsi;
}

/**
 * Peta prioritas: x = deviasi progres (pp), y = nilai investasi (Rp triliun, skala log), ukuran = isu terbuka.
 * Satu seri per status (warna status + label di legenda). Area kiri atas = besar dan tertinggal.
 */
export function kuadran(q, warnaStatus, labelStatus) {
    const urut = ['TERLAMBAT', 'BERISIKO', 'ON_TRACK'];
    const xs = q.titik.map((t) => t.deviasi_pp);
    const xMin = Math.min(q.garis_x - 10, ...xs);
    const xMax = Math.max(10, ...xs);
    const seri = urut.map((s, i) => ({
        name: labelStatus[s], type: 'scatter', cursor: 'pointer',
        data: q.titik.filter((t) => t.status === s).map((t) => ({ value: [t.deviasi_pp, Math.max(t.investasi_triliun, 0.001)], ...t })),
        symbolSize: (v, p) => 8 + Math.sqrt(p.data.isu) * 4,
        itemStyle: { color: warnaStatus[s], opacity: 0.8, borderColor: '#fff', borderWidth: 1 },
        ...(i === 0 && q.garis_y ? {
            markArea: { silent: true, itemStyle: { color: 'rgba(220,38,38,0.06)' }, label: { position: 'insideTopLeft', color: '#b91c1c', fontSize: 10.5, fontWeight: 600 },
                data: [[{ name: 'Besar dan tertinggal', coord: [xMin, q.garis_y] }, { coord: [q.garis_x, 'max'] }]] },
            markLine: { silent: true, symbol: 'none', lineStyle: { color: WARNA.teksSamar, type: 'dashed', width: 1 }, label: { show: false },
                data: [{ xAxis: q.garis_x }, { yAxis: q.garis_y }] },
        } : {}),
    }));
    return {
        grid: { left: 22, right: 16, top: 26, bottom: 22, containLabel: true },
        legend: { top: 0, right: 0, itemWidth: 10, itemHeight: 10, textStyle: { color: WARNA.teks, fontSize: 11 } },
        tooltip: { ...dasar.tooltip, trigger: 'item', formatter: (p) => `<b>${p.data.nama}</b>${p.data.pj ? `<br/>${p.data.pj}` : ''}<br/>Deviasi ${p.data.deviasi_pp > 0 ? '+' : ''}${angka(p.data.deviasi_pp)} pp · ${labelStatus[p.data.status]}<br/>Investasi Rp ${angka(p.data.investasi_triliun, 2)} T · ${p.data.isu} isu terbuka<br/><span style="color:#64748b">Klik untuk membuka detail</span>` },
        xAxis: { type: 'value', min: Math.floor(xMin / 10) * 10, max: Math.ceil(xMax / 10) * 10, name: 'Deviasi realisasi terhadap rencana (pp)', nameLocation: 'middle', nameGap: 20, nameTextStyle: { color: WARNA.teksSamar, fontSize: 10.5 },
            axisLabel: { color: WARNA.teksSamar, fontSize: 10.5 }, splitLine: { lineStyle: { color: WARNA.grid } } },
        yAxis: { type: 'log', logBase: 10, min: 10 ** Math.floor(Math.log10(Math.max(0.001, Math.min(...q.titik.map((t) => t.investasi_triliun).filter((v) => v > 0), 1)))), name: 'Investasi (Rp T, skala log)', nameLocation: 'middle', nameGap: 38, nameTextStyle: { color: WARNA.teksSamar, fontSize: 10.5 },
            axisLabel: { color: WARNA.teksSamar, fontSize: 10.5, formatter: (v) => angka(v, 3) }, splitLine: { lineStyle: { color: WARNA.grid } } },
        series: seri,
    };
}
