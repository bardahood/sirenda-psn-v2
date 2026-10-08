// Grafik ECharts (tree-shaken). Aturan visual: satu seri = satu warna (aksen),
// tren dua seri memakai palet tervalidasi (biru #2a78d6, oranye #eb6834),
// sudut data 4px, grid/axis samar, tooltip per-batang & crosshair untuk garis.
import * as echarts from 'echarts/core';
import { BarChart, LineChart, HeatmapChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent, MarkLineComponent, MarkPointComponent, VisualMapComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, LineChart, HeatmapChart, GridComponent, TooltipComponent, LegendComponent, MarkLineComponent, MarkPointComponent, VisualMapComponent, CanvasRenderer]);

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
