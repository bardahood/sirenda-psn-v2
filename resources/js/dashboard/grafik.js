// Grafik ECharts (tree-shaken). Aturan visual: satu seri = satu warna (aksen),
// tren dua seri memakai palet tervalidasi (biru #2a78d6, oranye #eb6834),
// sudut data 4px, grid/axis samar, tooltip per-batang & crosshair untuk garis.
import * as echarts from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent, MarkLineComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, LineChart, GridComponent, TooltipComponent, LegendComponent, MarkLineComponent, CanvasRenderer]);

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
export function batangHorizontal(baris, { nilai = 'jumlah', satuan = 'PSN', klik = false, sumbuX = true, lebarLabel = 170 } = {}) {
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
            axisLabel: { color: WARNA.teks, fontSize: 11, width: lebarLabel, overflow: 'break', lineHeight: 13 },
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
