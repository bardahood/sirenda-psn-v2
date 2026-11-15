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
