const grafik = () => import('./dashboard/grafik');

/** Kurva S pada tab Progres Detail Proyek. */
export function grafikKurvaS(bulan, terlambat) {
    return {
        async init() {
            const { render, kurvaS } = await grafik();
            render(this.$refs.kanvas, kurvaS(bulan, terlambat));
        },
        async unduh() {
            const { png } = await grafik();
            png(this.$refs.kanvas, 'kurva-s');
        },
    };
}

/** Grafik perbandingan antar-usulan pada halaman penilaian. */
export function grafikPerbandingan(baris) {
    return {
        async init() {
            if (!baris.length) return;
            const { render, batangSorot } = await grafik();
            render(this.$refs.kanvas, batangSorot(baris));
        },
    };
}
