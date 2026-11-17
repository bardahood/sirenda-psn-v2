@php
    $kamus = \App\Support\Dashboard\KamusIndikator::DAFTAR;
    $kpi = [
        'K1' => ['Total Proyek PSN', 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1'],
        'K2' => ['Total Investasi', 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        'K3' => ['Rata-rata Progres Fisik', 'M3 3v18h18M7 15l4-4 3 3 5-6'],
        'K4' => ['Risiko Kritis', 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
        'K5' => ['PKPN', 'M12 3 3 8l9 5 9-5-9-5Zm-9 9 9 5 9-5M3 16l9 5 9-5'],
    ];
@endphp
<x-app-layout judul="Dashboard Monitoring & Evaluasi PSN" :layar-penuh="true">
    <x-slot:infoJudul>
        <span x-data x-show="$store.statusDasbor.tanggal" x-cloak title="Dashboard selalu memakai cut-off terbit terbaru">Data per <b class="text-slate-700" x-text="$store.statusDasbor.tanggal"></b></span>
    </x-slot:infoJudul>
    <x-slot:filter>
        <x-filter-global :periode="false" />
    </x-slot:filter>
    <x-slot:aksi>
        @include('dashboard._tab')
        <div class="flex items-center gap-3 text-[12px] text-slate-600" x-show="$store.statusDasbor.tanggal" x-cloak aria-label="Status data">
            <a :href="'{{ route('laporan.ringkasan-pdf') }}' + ($store.filter.qs() ? '?' + $store.filter.qs() : '')" class="tombol-garis px-2.5 py-1.5 text-label" title="Unduh PDF Ringkasan Eksekutif sesuai filter">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>
                PDF
            </a>
        </div>
    </x-slot:aksi>

    <div x-data="halamanDashboard(@js($kamus), @js(config('psn_dashboard.peta_kelas')), 'ikhtisar')" class="layar:h-full">
        <div x-show="galat" x-cloak role="alert" class="mb-3 rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" x-text="galat"></div>

        <div x-show="!memuat && meta && !meta.cutoff" x-cloak class="kartu text-center">
            <p class="judul-panel">Belum ada cut-off yang diterbitkan</p>
            <p class="mt-1 text-slate-600">Terbitkan snapshot pertama melalui Pengaturan &gt; Cut-off &amp; Snapshot.</p>
        </div>

        <div x-show="meta?.cutoff" x-cloak :aria-busy="memuat" :class="memuat && 'opacity-60 transition-opacity'"
             class="grid grid-cols-1 gap-2.5 md:grid-cols-2 layar:h-full layar:grid-cols-24 layar:grid-rows-[auto_repeat(2,minmax(0,1fr))]">

            @include('dashboard._kartu-kpi', ['kpi' => $kpi, 'kolom' => 'xl:grid-cols-5'])

            {{-- Baris 2: perjalanan daftar PSN dalam RKP (slide 4) & skema pendanaan (slide 6) --}}
            <x-panel kode="IKH_RKP" judul="Perjalanan Daftar PSN dalam RKP" padat class="md:col-span-2 layar:col-[span_15/span_15]">
                <x-slot:subjudul>Tahap bertanda <b>data SIRENDA</b> dihitung dari basis data; tahap lain angka dokumen resmi · tidak dipengaruhi filter</x-slot:subjudul>
                <ol class="grid h-full grid-cols-1 gap-2 sm:grid-cols-5">
                    <template x-for="(t, i) in rkpTahap" :key="t.kode">
                        <li class="flex min-h-0 flex-col rounded-lg border p-2" :class="t.live ? 'border-aksen/40 bg-aksen-50/40' : 'border-slate-200 bg-white'" :style="{ marginTop: `calc(${(rkpTahap.length - 1 - i) * 0.6}rem)` }">
                            <span class="self-start rounded px-1.5 py-0.5 text-[10px] font-bold text-white" :class="t.live ? 'bg-aksen' : 'bg-primer'" x-text="t.dasar"></span>
                            <span class="mt-1 line-clamp-2 text-[11px] font-semibold leading-tight text-slate-700" x-text="t.judul"></span>
                            <span class="mt-0.5 flex items-baseline gap-1">
                                <b class="angka text-[24px] leading-7 text-slate-900" x-text="t.jumlah === null ? '–' : angka(t.jumlah, 0)"></b>
                                <span x-show="t.jumlah !== null" class="text-[10px] text-slate-500">PSN dalam daftar</span>
                            </span>
                            <span x-show="t.live" class="text-[10px] font-semibold text-aksen-700" x-text="'data SIRENDA' + (t.dokumen && t.dokumen !== t.jumlah ? ' · dokumen ' + angka(t.dokumen, 0) : '')"></span>
                            <span x-show="t.catatan" class="truncate text-[10px] text-slate-500" :title="t.catatan" x-text="t.catatan"></span>
                            <ul class="mt-1 min-h-0 space-y-0.5 overflow-y-auto">
                                <template x-for="p in t.perubahan" :key="p.label">
                                    <li class="flex items-start gap-1 text-[10.5px] leading-tight" :title="p.rincian">
                                        <span class="angka shrink-0 rounded px-1 font-bold" :class="p.arah === '+' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800'" x-text="(p.arah === '+' ? '+' : '−') + angka(p.jumlah, 0)"></span>
                                        <span class="min-w-0 truncate text-slate-700" x-text="p.label"></span>
                                    </li>
                                </template>
                            </ul>
                        </li>
                    </template>
                </ol>
            </x-panel>

            <x-panel kode="IKH_SKEMA" judul="Porsi Skema Pendanaan" grafik="skema" padat class="layar:col-[span_9/span_9]">
                <x-slot:subjudul><span x-text="skemaUtama ? 'Didominasi ' + skemaUtama.label + ' sebesar ' + angka(skemaUtama.persen) + '% nilai investasi' : 'Nilai investasi per kelompok skema'"></span></x-slot:subjudul>
                <div class="flex items-center gap-3 layar:h-full">
                    <div class="relative aspect-square h-40 shrink-0 layar:h-full layar:max-h-[12rem]">
                        <div x-ref="skema" class="h-full w-full" role="img" aria-label="Porsi nilai investasi per skema pendanaan"></div>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="angka text-[13px] font-bold text-slate-900" x-text="'Rp ' + angka(totalInvestasiSkema, totalInvestasiSkema < 100 ? 1 : 0) + ' T'"></span>
                            <span class="text-[10px] text-slate-500">nilai investasi</span>
                        </div>
                    </div>
                    <ul class="min-w-0 flex-1 space-y-2">
                        <template x-for="s in skemaBaris" :key="s.kode">
                            <li class="text-[11px]">
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: s.warna }"></span>
                                    <span class="min-w-0 flex-1 truncate font-semibold text-slate-700" x-text="s.label"></span>
                                    <b class="angka text-[13px] text-slate-900" x-text="s.persen === null ? '–' : angka(s.persen) + '%'"></b>
                                </div>
                                <div class="angka pl-4 text-[10.5px] text-slate-500" x-text="'Rp ' + angka(s.investasi_triliun) + ' T · ' + angka(s.jumlah, 0) + ' PSN (' + angka(s.pkpn, 0) + ' PKPN)'"></div>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-panel>

            {{-- Baris 3: wilayah (slide 6), PSN selesai x kebutuhan status (slide 21), status & target 2029 (slide 13, 25-31) --}}
            <x-panel kode="IKH_WILAYAH" judul="Sebaran PSN menurut Wilayah" grafik="wilayah" padat class="layar:col-[span_10/span_10]">
                <x-slot:subjudul>Jumlah PSN, PKPN, dan nilai investasi · PSN di beberapa wilayah masuk Nasional &amp; Lintas Pulau</x-slot:subjudul>
                <div class="flex flex-col layar:h-full">
                    <div x-ref="wilayah" class="h-64 rounded-lg bg-gradient-to-b from-sky-50 to-white layar:h-auto layar:min-h-0 layar:flex-1" role="img" aria-label="Peta sebaran PSN per wilayah"></div>
                    <p class="sr-only"><template x-for="w in wilayahBaris" :key="w.label"><span x-text="w.label + ': ' + w.jumlah + ' PSN, ' + w.pkpn + ' PKPN, Rp ' + angka(w.investasi_triliun) + ' triliun. '"></span></template></p>
                </div>
            </x-panel>

            <x-panel kode="IKH_SELESAI" judul="PSN Selesai menurut Kebutuhan Status" padat class="layar:col-[span_7/span_7]">
                <x-slot:subjudul><span x-text="angka(d.ikhtisar?.selesai.total ?? 0, 0) + ' PSN Kumulatif Selesai · isian Kebutuhan Status PSN di SIRENDA'"></span></x-slot:subjudul>
                <div class="flex flex-col gap-1.5 layar:h-full">
                    <div class="grid grid-cols-3 gap-1.5" role="tablist" aria-label="Kategori kebutuhan status">
                        <template x-for="k in selesaiKategori" :key="k.kode">
                            <button type="button" role="tab" :aria-selected="kebutuhanTerpilih === k.kode" @click="kebutuhanTerpilih = k.kode"
                                    class="rounded-lg border px-2 py-1 text-left transition" :class="kebutuhanTerpilih === k.kode ? 'border-slate-900 bg-slate-50' : 'border-slate-200 hover:bg-slate-50'">
                                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full" :style="{ background: k.warna }"></span><b class="angka text-[20px] leading-6 text-slate-900" x-text="angka(k.jumlah, 0)"></b></span>
                                <span class="block text-[10px] leading-tight text-slate-600" x-text="k.label"></span>
                            </button>
                        </template>
                    </div>
                    <ul class="min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto" :aria-label="selesaiTerpilih?.label">
                        <template x-for="p in selesaiTerpilih?.psn ?? []" :key="p.psn_id">
                            <li class="py-0.5 text-[11px]" :title="p.isian ? 'Isian K/L: ' + p.isian : 'Belum ada isian Kebutuhan Status PSN'">
                                <a :href="$store.filter.tautan('/proyek/' + p.psn_id)" class="block truncate font-semibold text-slate-800 hover:underline" x-text="p.nama"></a>
                                <span class="block truncate text-[10px] text-slate-500" x-text="p.isian ? (p.sumber === 'EVALUASI' ? 'D1 Evaluasi · ' : '') + p.isian : (p.sumber === 'EVALUASI' ? 'Dari isian D1 menu Evaluasi' : 'Belum diisi K/L')"></span>
                            </li>
                        </template>
                        <li x-show="!(selesaiTerpilih?.psn ?? []).length" class="py-3 text-center text-[11px] text-slate-500">Tidak ada PSN pada kategori ini.</li>
                    </ul>
                </div>
            </x-panel>

            <x-panel kode="IKH_STATUS" judul="Status Tahapan & Target Selesai" padat class="layar:col-[span_7/span_7]">
                <x-slot:subjudul><span x-text="angka(d.ikhtisar?.status.belum_selesai ?? 0, 0) + ' PSN belum selesai · batas RPJMN ' + (d.ikhtisar?.status.tahun_batas ?? 2029)"></span></x-slot:subjudul>
                <div class="flex flex-col justify-between gap-2 layar:h-full">
                    <ul class="space-y-0.5">
                        <template x-for="t in statusTahap" :key="t.kode">
                            <li class="grid grid-cols-[minmax(0,44%)_1fr_auto] items-center gap-2 text-[11px] leading-[15px]">
                                <span class="truncate text-slate-700" x-text="t.label"></span>
                                <span class="h-2 rounded-full bg-slate-100"><span class="block h-2 rounded-full" :class="t.kode === 'TANPA' ? 'bg-slate-400' : 'bg-aksen'" :style="{ width: t.lebar }"></span></span>
                                <b class="angka w-8 text-right text-slate-900" x-text="angka(t.jumlah, 0)"></b>
                            </li>
                        </template>
                        <li class="text-[10.5px] text-slate-500" x-text="'Keluar dari PSN: ' + angka(d.ikhtisar?.status.keluar ?? 0, 0) + ' PSN (tidak dihitung di atas)'"></li>
                    </ul>
                    <div>
                        <p class="mb-1 text-[11px] font-semibold text-slate-700">Target tahun selesai PSN yang belum selesai</p>
                        <div class="flex h-3 overflow-hidden rounded-full bg-slate-100" role="img" :aria-label="targetSegmen.map((s) => s.label + ' ' + s.jumlah + ' PSN').join(', ')">
                            <template x-for="s in targetSegmen" :key="s.kode"><span class="h-3" :style="{ width: s.pct + '%', background: s.warna }" :title="s.label + ': ' + s.jumlah + ' PSN'"></span></template>
                        </div>
                        <ul class="mt-1 grid grid-cols-2 gap-x-2 text-[10.5px]">
                            <template x-for="s in targetSegmen" :key="s.kode">
                                <li class="flex items-center gap-1"><span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: s.warna }"></span><span class="truncate text-slate-600" x-text="s.label"></span><b class="angka ml-auto text-slate-900" x-text="angka(s.jumlah, 0)"></b></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </x-panel>
        </div>
    </div>
</x-app-layout>
