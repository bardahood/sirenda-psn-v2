@php
    $kamus = \App\Support\Dashboard\KamusIndikator::DAFTAR;
    // Tinggi grafik: tetap di layar kecil (bergulir), mengisi sisa panel pada mode satu layar.
    $isiPanel = 'h-60 layar:h-auto layar:min-h-0 layar:flex-1';
@endphp
<x-app-layout judul="Dashboard Monitoring & Evaluasi PSN" :layar-penuh="true">
    <x-slot:infoJudul>
        {{-- Pengganti pilihan periode: dashboard selalu memakai cut-off terbit terbaru. --}}
        <span x-data x-show="$store.statusDasbor.tanggal" x-cloak title="Dashboard selalu memakai cut-off terbit terbaru">Data per <b class="text-slate-700" x-text="$store.statusDasbor.tanggal"></b></span>
    </x-slot:infoJudul>
    <x-slot:filter>
        <x-filter-global :periode="false" />
    </x-slot:filter>
    <x-slot:aksi>
        @include('dashboard._tab')
        <div class="flex items-center gap-3 text-[12px] text-slate-600" x-show="$store.statusDasbor.tanggal" x-cloak aria-label="Status data">
            <span class="hidden min-[1800px]:inline">Kelengkapan <b class="angka text-slate-900" x-text="$store.statusDasbor.kelengkapan"></b></span>
            <a :href="'{{ route('laporan.ringkasan-pdf') }}' + ($store.filter.qs() ? '?' + $store.filter.qs() : '')" class="tombol-garis px-2.5 py-1.5 text-label" title="Unduh PDF Ringkasan Eksekutif sesuai filter">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>
                PDF
            </a>
        </div>
    </x-slot:aksi>

    <div x-data="halamanDashboard(@js($kamus), @js(config('psn_dashboard.peta_kelas')))" class="layar:h-full">
        <div x-show="galat" x-cloak role="alert" class="mb-3 rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" x-text="galat"></div>

        <div x-show="!memuat && meta && !meta.cutoff" x-cloak class="kartu text-center">
            <p class="judul-panel">Belum ada cut-off yang diterbitkan</p>
            <p class="mt-1 text-slate-600">Terbitkan snapshot pertama melalui Pengaturan &gt; Cut-off &amp; Snapshot.</p>
        </div>

        <div x-show="meta?.cutoff" x-cloak :aria-busy="memuat" :class="memuat && 'opacity-60 transition-opacity'"
             class="grid grid-cols-1 gap-2.5 md:grid-cols-2 layar:h-full layar:grid-cols-24 layar:grid-rows-[auto_repeat(2,minmax(0,1fr))]">

            {{-- 1. Baris KPI strategis (klik -> Portofolio dengan filter yang sama) --}}
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:col-span-2 xl:grid-cols-5 layar:col-span-full">
                <template x-for="k in kartuPl" :key="k.kode">
                    <a :href="k.tautan" class="kartu flex items-center gap-3 !px-4 !py-2.5 transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-aksen" :aria-label="k.judul + ': ' + k.nilai">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white shadow" :style="{ background: k.warna }">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="k.ikon"/></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1">
                                <span class="truncate text-[12px] font-bold text-slate-700" x-text="k.judul"></span>
                                <span class="relative" x-data="{ buka: false }" @click.prevent.stop="buka = !buka" @click.outside="buka = false">
                                    <span class="ikon-tombol !h-5 !w-5" role="button" tabindex="0" :aria-label="'Informasi ' + k.judul" @keydown.enter.prevent.stop="buka = !buka">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                                    </span>
                                    <span x-show="buka" x-cloak role="tooltip" class="absolute left-0 z-40 mt-1 block w-72 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-label font-normal text-slate-700 shadow-lg" x-text="info(k.info)"></span>
                                </span>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span class="angka truncate text-[22px] font-bold leading-7 text-slate-900" x-text="k.nilai"></span>
                                <span x-show="k.status" :class="'badge-' + k.status" class="!py-0 !text-[10px]"><span :class="'titik-' + k.status"></span><span x-text="labelStatus(k.status)"></span></span>
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] text-slate-500">
                                <span class="angka inline-flex shrink-0 items-center gap-0.5 rounded px-1 font-bold" :class="kelasDelta(k)" :title="teksDelta(k)">
                                    <span aria-hidden="true" x-text="panahDelta(k)"></span><span x-text="deltaRingkas(k)"></span>
                                </span>
                                <span class="truncate" x-text="k.keterangan" :title="k.keterangan"></span>
                            </div>
                        </div>
                        <div :data-spark="k.kode" class="hidden h-10 w-20 shrink-0 [@media(min-width:1700px)]:block" role="img" :aria-label="'Tren ' + k.judul + ' per cut-off terbit'"></div>
                    </a>
                </template>
            </div>

            {{-- Baris 2 --}}
            <x-panel kode="PL_KURVA" judul="Kurva S Progres Fisik Portofolio" grafik="kurva" padat class="layar:col-[span_7/span_7]">
                <x-slot:subjudul><span x-text="teksKurva"></span></x-slot:subjudul>
                <div class="flex flex-col layar:h-full">
                    <div x-ref="kurva" class="{{ $isiPanel }}" role="img" aria-label="Grafik garis rencana, realisasi, dan proyeksi realisasi progres fisik kumulatif per bulan"></div>
                </div>
            </x-panel>

            <x-panel kode="PL_KUADRAN" judul="Peta Prioritas: Nilai Investasi vs Deviasi" grafik="kuadran" padat class="layar:col-[span_8/span_8]">
                <x-slot:subjudul><span x-text="teksKuadran"></span></x-slot:subjudul>
                <div class="flex flex-col layar:h-full">
                    <div x-ref="kuadran" class="{{ $isiPanel }}" role="img" aria-label="Diagram sebar PSN: deviasi progres terhadap nilai investasi, ukuran titik = isu terbuka"></div>
                </div>
            </x-panel>

            <x-panel kode="PL_PERHATIAN" judul="Daftar Perhatian Pimpinan" padat class="layar:col-[span_9/span_9]">
                <x-slot:subjudul>10 PSN dengan skor prioritas tertinggi · arahkan kursor untuk semua alasan · klik untuk detail</x-slot:subjudul>
                <ol class="divide-y divide-slate-100">
                    <template x-for="(p, i) in pl?.perhatian ?? []" :key="p.psn_id">
                        <li>
                            <a :href="$store.filter.tautan('/proyek/' + p.psn_id)" class="flex items-center gap-1.5 py-px hover:bg-slate-50" :title="p.nama + (p.pj ? ' · ' + p.pj : '') + ' · ' + p.alasan.map(a => a.label).join(', ') + ' · skor ' + angka(p.skor)">
                                <span class="angka flex h-4 w-4 shrink-0 items-center justify-center rounded bg-primer text-[10px] font-bold text-white" x-text="i + 1"></span>
                                <span class="min-w-0 flex-1 truncate text-[11px]"><b class="font-semibold text-slate-900" x-text="p.nama"></b> <span class="text-slate-500" x-text="p.pj ? '· ' + p.pj.replace(/^Menteri\s+/i, 'Men. ') : ''"></span></span>
                                <template x-for="(a, j) in p.alasan.slice(0, 2)" :key="a.kode + a.label">
                                    <span :class="kelasAlasan(a.kode) + (j ? ' [@media(max-width:639px)]:hidden' : '')" class="shrink-0 !py-0 !text-[9.5px] !leading-[14px]"><span x-show="['TERLAMBAT','BERISIKO','TANPA_DATA'].includes(a.kode)" :class="'titik-' + a.kode"></span><span x-text="a.label"></span></span>
                                </template>
                                <span x-show="p.alasan.length > 2" class="shrink-0 text-[10px] text-slate-500" x-text="'+' + (p.alasan.length - 2)"></span>
                                <span class="angka w-12 shrink-0 text-right text-[10.5px] text-slate-600" x-text="p.investasi_triliun > 0 ? 'Rp ' + angka(p.investasi_triliun, p.investasi_triliun < 1 ? 2 : p.investasi_triliun < 10 ? 1 : 0) + ' T' : '–'"></span>
                            </a>
                        </li>
                    </template>
                </ol>
                <p x-show="!(pl?.perhatian ?? []).length" class="py-6 text-center text-[12px] text-slate-500">Tidak ada PSN yang memenuhi kriteria perhatian pada filter ini.</p>
            </x-panel>

            {{-- Baris 3 --}}
            <x-panel kode="PL_MATRIKS" judul="Status Pelaksanaan per Klaster" padat class="layar:col-[span_7/span_7]">
                <x-slot:subjudul>Jumlah PSN aktif · klik sel untuk memfilter klaster dan status</x-slot:subjudul>
                <table class="w-full table-fixed text-[11px]">
                    <colgroup><col><col class="w-[3.9rem]"><col class="w-[3.9rem]"><col class="w-[3.9rem]"><col class="w-[3.9rem]"></colgroup>
                    <thead class="text-[10px] text-slate-500">
                        <tr><th class="py-0.5 pr-1 text-left font-medium">Klaster</th>
                            <template x-for="s in pl?.matriks.status ?? []" :key="s"><th class="whitespace-nowrap py-0.5 text-center text-[9.5px] font-medium"><span class="inline-flex items-center gap-0.5"><span :class="'titik-' + s"></span><span x-text="labelStatus(s)"></span></span></th></template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="b in pl?.matriks.baris ?? []" :key="b.label">
                            <tr>
                                <td class="truncate py-0 pr-1 leading-4 text-slate-700" :title="b.label + ': ' + b.jumlah + ' PSN'" x-text="b.label"></td>
                                <template x-for="s in pl.matriks.status" :key="s">
                                    <td class="p-[1px]">
                                        <button type="button" class="angka block w-full rounded py-0 text-center font-semibold leading-4 disabled:cursor-default" :disabled="!b.id || !b.sel[s]"
                                                :style="gayaSel(s, b.sel[s], b.jumlah)" @click="saringSel(b.id, s)" :title="b.label + ' · ' + labelStatus(s) + ': ' + b.sel[s] + ' PSN'" x-text="b.sel[s] || '·'"></button>
                                    </td>
                                </template>
                            </tr>
                        </template>
                        <tr class="border-t border-slate-200 font-bold">
                            <td class="py-0.5 pr-1 text-slate-800">Total</td>
                            <template x-for="s in pl?.matriks.status ?? []" :key="s"><td class="angka py-0.5 text-center text-slate-900" x-text="pl.matriks.total[s]"></td></template>
                        </tr>
                    </tbody>
                </table>
            </x-panel>

            <x-panel kode="PL_KEPATUHAN" judul="Kepatuhan Pelaporan per K/L" padat class="layar:col-[span_6/span_6]">
                <x-slot:subjudul><span x-text="teksKepatuhan"></span></x-slot:subjudul>
                <ul class="flex flex-col justify-around gap-0.5 layar:min-h-full">
                    <template x-for="k in kepatuhanTop" :key="k.slug">
                        <li>
                            <button type="button" class="grid w-full grid-cols-[minmax(0,48%)_1fr_auto] items-center gap-2 rounded text-left hover:bg-slate-50" @click="$store.filter.tambah('kl', k.slug)" :title="k.label + ': ' + k.melapor + ' dari ' + k.jumlah + ' PSN melapor · klik untuk memfilter'">
                                <span class="truncate text-[11px] leading-[15px] text-slate-700" x-text="k.label.replace(/^Kementerian\s+/i, 'Kem. ')"></span>
                                <span class="h-2 rounded-full bg-slate-100"><span class="block h-2 rounded-full bg-aksen" :style="{ width: persenLebar(k.persen) }"></span></span>
                                <span class="angka w-[5.5rem] text-right text-[11px] leading-[15px]"><b class="text-slate-900" x-text="angka(k.persen, 0) + '%'"></b> <span class="text-slate-500" x-text="'(' + k.melapor + '/' + k.jumlah + ')'"></span></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </x-panel>

            <x-panel kode="PL_HAMBATAN" judul="Hambatan Utama (Isu Terbuka)" padat class="layar:col-[span_6/span_6]">
                <x-slot:subjudul><span x-text="teksHambatan"></span></x-slot:subjudul>
                <div class="flex flex-col layar:min-h-full">
                    <div class="mb-1 flex gap-3 text-[10.5px] text-slate-600">
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#eb6834"></span>pada PSN kritis</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" style="background:#2a78d6"></span>pada PSN lain</span>
                    </div>
                    <ul class="flex flex-1 flex-col justify-around gap-0.5">
                        <template x-for="h in hambatanBaris" :key="h.kode">
                            <li class="grid grid-cols-[minmax(0,42%)_1fr_auto] items-center gap-2" :title="h.label + ': ' + h.jumlah + ' isu di ' + h.psn + ' PSN (' + h.psn_kritis + ' pada PSN kritis)'">
                                <span class="truncate text-[11px] leading-[15px] text-slate-700" x-text="h.label"></span>
                                <span class="flex h-2.5 overflow-hidden rounded-full bg-slate-100">
                                    <span class="block h-full" style="background:#eb6834" :style="{ width: h.lebarKritis }"></span>
                                    <span class="block h-full" style="background:#2a78d6" :style="{ width: h.lebarLain }"></span>
                                </span>
                                <span class="angka w-[5.5rem] whitespace-nowrap text-right text-[11px]"><b class="text-slate-900" x-text="h.jumlah"></b> <span class="text-slate-500" x-text="'(' + h.psn + ' PSN)'"></span></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-panel>

            <x-panel kode="PL_PERGERAKAN" judul="Pergerakan Status" padat class="layar:col-[span_5/span_5]">
                <x-slot:subjudul><span x-text="pl?.pergerakan.sebelumnya ? 'Dibanding cut-off ' + pl.pergerakan.sebelumnya : 'Belum ada cut-off pembanding'"></span></x-slot:subjudul>
                <div x-show="pl?.pergerakan.sebelumnya" class="flex flex-col gap-1.5">
                    <div class="flex h-3 overflow-hidden rounded-full bg-slate-100" role="img" :aria-label="(pl?.pergerakan.kelompok ?? []).map(g => g.label + ' ' + g.jumlah).join(', ')">
                        <template x-for="g in pergerakanSegmen" :key="g.kode"><span class="block h-full" :style="{ width: g.lebar, background: g.warna }" :title="g.label + ': ' + g.jumlah + ' PSN'"></span></template>
                    </div>
                    <ul class="grid grid-cols-2 gap-x-2 text-[10.5px]">
                        <template x-for="g in pergerakanSegmen" :key="g.kode">
                            <li class="flex items-center gap-1"><span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: g.warna }"></span><span class="truncate text-slate-600" x-text="g.label"></span><b class="angka ml-auto text-slate-900" x-text="g.jumlah"></b></li>
                        </template>
                    </ul>
                    <p class="mt-0.5 text-[10.5px] font-semibold text-slate-700" x-show="(pl?.pergerakan.sorot ?? []).length">Perlu diwaspadai</p>
                    <ul class="divide-y divide-slate-100">
                        <template x-for="s in pl?.pergerakan.sorot ?? []" :key="s.psn_id">
                            <li><a :href="$store.filter.tautan('/proyek/' + s.psn_id)" class="flex items-center gap-1 py-0.5 text-[10.5px] hover:bg-slate-50" :title="s.nama + ': ' + labelStatus(s.dari) + ' menjadi ' + labelStatus(s.ke)">
                                <span class="min-w-0 flex-1 truncate font-semibold text-slate-800" x-text="s.nama"></span>
                                <span :class="'badge-' + s.ke" class="!py-0 !text-[9.5px]"><span :class="'titik-' + s.ke"></span><span x-text="labelStatus(s.ke)"></span></span>
                            </a></li>
                        </template>
                    </ul>
                </div>
            </x-panel>
        </div>
    </div>
</x-app-layout>
