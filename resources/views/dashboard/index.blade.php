@php
    $kamus = \App\Support\Dashboard\KamusIndikator::DAFTAR;
    $idKlasterDp = \Illuminate\Support\Facades\DB::table('ref_klaster')->where('kode', 'DP')->value('id');
    $ikonInfo = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>';
    $kpi = [
        'K1' => ['Total Proyek PSN', 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1'],
        'K2' => ['Total Investasi', 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        'K3' => ['Rata-rata Progres Fisik', 'M3 3v18h18M7 15l4-4 3 3 5-6'],
        'K4' => ['Risiko Kritis', 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
    ];
@endphp
<x-app-layout judul="Dashboard Monitoring & Evaluasi PSN" subjudul="Berdasarkan Data Proyek Strategis Nasional">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>

    <div x-data="halamanDashboard(@js($kamus), @js(config('psn_dashboard.peta_kelas')))" class="mx-auto max-w-[1680px]">
        <div x-show="galat" x-cloak role="alert" class="mb-6 rounded-kartu border border-red-200 bg-red-50 p-4 text-red-800" x-text="galat"></div>

        <div x-show="!memuat && meta && !meta.cutoff" x-cloak class="kartu text-center">
            <p class="judul-panel">Belum ada cut-off yang diterbitkan</p>
            <p class="mt-1 text-slate-600">Terbitkan snapshot pertama melalui Pengaturan &gt; Cut-off &amp; Snapshot.</p>
        </div>

        <div x-show="meta?.cutoff" x-cloak :aria-busy="memuat" :class="memuat && 'opacity-60 transition-opacity'" class="space-y-5">

            {{-- Status data --}}
            <section class="flex flex-wrap items-center gap-x-6 gap-y-2 text-[13px] text-slate-600" aria-label="Status data">
                <span>Data cut-off <b class="text-slate-900" x-text="tanggalCutoff"></b></span>
                <span>Kelengkapan rata-rata <b class="angka text-slate-900" x-text="angka(d.statusData?.kelengkapan_persen) + '%'"></b></span>
                <span><b class="angka text-slate-900" x-text="angka(d.statusData?.belum_terverifikasi, 0)"></b> dari <span class="angka" x-text="angka(d.statusData?.total_psn, 0)"></span> PSN belum terverifikasi</span>
                <span class="relative" x-data="{ buka: false }" @click.outside="buka = false">
                    <button type="button" class="ikon-tombol" @click="buka = !buka" aria-label="Informasi status data">{!! $ikonInfo !!}</button>
                    <span x-show="buka" x-cloak role="tooltip" class="absolute left-0 z-30 mt-1 block w-72 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-label font-normal text-slate-700 shadow-lg" x-text="info('STATUS_DATA')"></span>
                </span>
                <a :href="'{{ route('laporan.ringkasan-pdf') }}' + ($store.filter.qs() ? '?' + $store.filter.qs() : '')" class="tombol-garis ml-auto py-1.5 text-label">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>
                    Unduh PDF Ringkasan
                </a>
            </section>

            {{-- K1-K4. Klik kartu -> Portofolio dengan filter yang sama. --}}
            <div class="grid grid-cols-12 gap-5">
                @foreach ($kpi as $k => [$judul, $ikon])
                    <a :href="$store.filter.tautan('/proyek') + ({{ $k === 'K4' ? 'true' : 'false' }} ? ($store.filter.qs() ? '&' : '?') + 'kritis=1' : '')"
                       class="kartu col-span-12 block transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-aksen sm:col-span-6 2xl:col-span-3"
                       aria-labelledby="kpi-{{ $k }}">
                        <div class="flex items-start gap-4">
                            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-white shadow-md" :style="{ background: warnaKpi('{{ $k }}') }">
                                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $ikon }}"/></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start gap-1">
                                    <span id="kpi-{{ $k }}" class="mr-auto text-[13px] font-bold text-slate-800">{{ $judul }}</span>
                                    <span class="relative" x-data="{ buka: false }" @click.prevent.stop="buka = !buka" @click.outside="buka = false">
                                        <span class="ikon-tombol" role="button" tabindex="0" aria-label="Informasi {{ $judul }}" @keydown.enter.prevent.stop="buka = !buka">{!! $ikonInfo !!}</span>
                                        <span x-show="buka" x-cloak role="tooltip" class="absolute right-0 z-30 mt-1 block w-72 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-label font-normal text-slate-700 shadow-lg" x-text="info('{{ $k }}')"></span>
                                    </span>
                                </div>
                                <div class="angka text-[30px] font-bold leading-9 text-slate-900" x-text="nilaiKartu(kartu('{{ $k }}'))"></div>
                                <p class="text-label font-normal text-slate-500">
                                    @switch($k)
                                        @case('K1') PSN aktif @break
                                        @case('K2') nilai investasi PSN aktif @break
                                        @case('K3') <span x-text="'tertimbang investasi · ' + angka(kartu('K3')?.cakupan_psn, 0) + ' PSN berdata'"></span> @break
                                        @case('K4') <span x-text="'dari ' + angka(totalPsn, 0) + ' PSN aktif'"></span> @break
                                    @endswitch
                                </p>
                            </div>
                        </div>
                        <div class="mt-3 flex items-end gap-3">
                            <div class="shrink-0">
                                <span class="angka inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[13px] font-bold" :class="kelasDelta(kartu('{{ $k }}'))">
                                    <span aria-hidden="true" x-text="panahDelta(kartu('{{ $k }}'))"></span><span x-text="deltaRingkas(kartu('{{ $k }}'))"></span>
                                </span>
                                <p class="mt-1 text-[11px] text-slate-500" x-text="meta?.sebelumnya ? 'vs periode sebelumnya' : 'belum ada pembanding'"></p>
                            </div>
                            <div x-ref="spark_{{ $k }}" class="ml-auto h-12 w-full max-w-[150px]" role="img" aria-label="Tren {{ $judul }} per cut-off terbit"></div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="grid grid-cols-12 gap-5">
                {{-- Kolom kiri (2/3) --}}
                <div class="col-span-12 grid grid-cols-12 content-start gap-5 xl:col-span-8">
                    <x-panel kode="P1" judul="Distribusi PSN per Klaster" class="col-span-12 lg:col-span-7">
                        <x-slot:subjudul><span x-text="(d.klaster ?? []).filter(k => k.label !== 'Lainnya').length + ' klaster dengan jumlah PSN terbanyak, dari ' + jumlahKlasterTerdaftar + ' klaster terdaftar'"></span></x-slot:subjudul>
                        <ul class="space-y-2.5">
                            <template x-for="k in klasterBaris" :key="k.label">
                                <li>
                                    <button type="button" class="grid w-full grid-cols-[minmax(0,13rem)_1fr_auto] items-center gap-3 rounded-md text-left hover:bg-slate-50 disabled:cursor-default disabled:hover:bg-transparent"
                                            :disabled="!k.id" @click="k.id && $store.filter.tambah('klaster', k.id)" :title="k.id ? 'Klik untuk memfilter klaster ' + k.label : k.label">
                                        <span class="line-clamp-2 text-[12px] leading-tight text-slate-700" x-text="k.label"></span>
                                        <span class="h-2.5 rounded-full bg-slate-100"><span class="block h-2.5 rounded-full bg-aksen" :style="{ width: k.lebar }"></span></span>
                                        <span class="angka w-24 text-right text-[12px]"><b class="text-slate-900" x-text="angka(k.jumlah, 0)"></b> <span class="text-slate-500" x-text="'(' + angka(k.pct) + '%)'"></span></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </x-panel>

                    <x-panel kode="P8" judul="Kontribusi Trisula Pembangunan" class="col-span-12 lg:col-span-5">
                        <x-slot:subjudul>PSN yang memiliki indikator per sasaran Trisula</x-slot:subjudul>
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="(t, i) in trisulaCincin" :key="t.judul">
                                <div class="text-center">
                                    <svg viewBox="0 0 100 100" class="mx-auto h-24 w-24" role="img" :aria-label="t.judul + ': ' + angka(t.pct) + '% PSN aktif memiliki indikator'">
                                        <circle cx="50" cy="50" r="42" fill="none" stroke="#eef2f7" stroke-width="10"/>
                                        <circle cx="50" cy="50" r="42" fill="none" :stroke="['#1baf7a', '#eb6834', '#2a78d6'][i]" stroke-width="10" stroke-linecap="round" :stroke-dasharray="t.garis" transform="rotate(-90 50 50)"/>
                                        <text x="50" y="55" text-anchor="middle" class="angka" font-size="17" font-weight="700" fill="#0f172a" x-text="angka(t.pct, 0) + '%'"></text>
                                    </svg>
                                    <p class="mt-1 text-[11px] font-semibold leading-tight text-slate-700" x-text="t.judul"></p>
                                    <p class="text-[11px] text-slate-500" x-text="t.psn + ' PSN · ' + t.indikator + ' indikator'"></p>
                                </div>
                            </template>
                        </div>
                        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-900" role="note">Metodologi kontribusi terhadap target RPJMN belum ditetapkan; persentase = PSN aktif yang memiliki indikator Trisula, bukan capaian.</p>
                    </x-panel>

                    <x-panel kode="P2" judul="Progres Fisik & Keuangan Tahun Berjalan" class="col-span-12 lg:col-span-7">
                        <x-slot:subjudul><span x-text="'Realisasi vs rencana kumulatif ' + (d.progres?.P3.tahun ?? '') + ' — garis vertikal menandai rencana'"></span></x-slot:subjudul>
                        <ul class="space-y-4">
                            <template x-for="b in progresBaris" :key="b.label">
                                <li>
                                    <div class="flex items-baseline justify-between gap-3 text-[12px]">
                                        <span class="font-semibold text-slate-700" x-text="b.label"></span>
                                        <span class="angka font-bold text-slate-900" x-text="b.kanan"></span>
                                    </div>
                                    <div class="relative mt-1.5 h-2.5 rounded-full bg-slate-100" role="img" :aria-label="b.label + ': ' + b.kanan">
                                        <div class="h-2.5 rounded-full" :style="{ width: persenLebar(b.nilai), background: b.warna }"></div>
                                        <div x-show="b.target !== null" class="absolute -top-1 h-[18px] w-0.5 rounded bg-slate-900" :style="{ left: persenLebar(b.target) }" title="Rencana"></div>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500" x-text="b.catatan"></p>
                                </li>
                            </template>
                        </ul>
                        <p class="mt-3"><span :class="'badge-' + (d.progres?.P2.status ?? 'TANPA_DATA')"><span :class="'titik-' + (d.progres?.P2.status ?? 'TANPA_DATA')"></span><span x-text="'Progres fisik: ' + labelStatus(d.progres?.P2.status) + (d.progres?.P2.deviasi_pp !== null && d.progres ? ' (deviasi ' + angka(d.progres.P2.deviasi_pp) + ' pp)' : '')"></span></span></p>
                    </x-panel>

                    <x-panel kode="DIREKTORAT" judul="Sebaran PSN per Direktorat" class="col-span-12 lg:col-span-5">
                        <x-slot:subjudul><span x-text="'Unit kerja dengan beban PSN terbanyak (5 dari ' + (d.direktorat ?? []).length + ')'"></span></x-slot:subjudul>
                        <ul class="divide-y divide-slate-100">
                            <template x-for="r in direktoratTop" :key="r.id">
                                <li>
                                    <button type="button" class="flex w-full items-center gap-3 py-2.5 text-left hover:bg-slate-50" @click="$store.filter.tambah('dit', r.id)" :title="'Klik untuk memfilter ' + r.label">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-aksen" aria-hidden="true"></span>
                                        <span class="min-w-0 flex-1 truncate text-[12px] text-slate-700" x-text="r.label.replace(/^Direktorat\s+/i, '')"></span>
                                        <b class="angka text-[12px] text-slate-900" x-text="angka(r.jumlah, 0)"></b>
                                        <span class="angka w-14 text-right text-[11px] text-slate-500" x-text="'(' + angka(r.pct) + '%)'"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                        <p class="mt-2 text-[11px] text-slate-500">PSN dengan beberapa direktorat pengampu dihitung di setiap direktorat.</p>
                    </x-panel>

                    <x-panel kode="RO_KRITIS" judul="RO Critical Path Berisiko & Terlambat" class="col-span-12">
                        <x-slot:subjudul>Diurutkan berdasarkan selisih realisasi terhadap rencana terbesar (5 teratas)</x-slot:subjudul>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px] text-left text-[12px]">
                                <thead class="border-b border-slate-200 text-[11px] text-slate-500">
                                    <tr><th class="w-6 py-2 pr-2">#</th><th class="py-2 pr-3">PSN / KP-RO</th><th class="py-2 pr-3">Direktorat</th><th class="w-56 py-2 pr-3">Progres fisik</th><th class="py-2">Status</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(r, i) in d.roKritis ?? []" :key="r.kegiatan_id">
                                        <tr>
                                            <td class="py-2 pr-2 text-slate-500" x-text="i + 1"></td>
                                            <td class="max-w-[18rem] py-2 pr-3">
                                                <a class="block truncate font-semibold text-slate-900 hover:text-aksen-700 hover:underline" :href="$store.filter.tautan('/proyek/' + r.psn_id)" :title="r.psn" x-text="r.psn"></a>
                                                <span class="block truncate text-[11px] text-slate-500" :title="r.kegiatan" x-text="'RO: ' + r.kegiatan"></span>
                                            </td>
                                            <td class="max-w-[14rem] truncate py-2 pr-3 text-slate-700" x-text="r.direktorat ?? '–'"></td>
                                            <td class="py-2 pr-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="relative h-2 flex-1 rounded-full bg-slate-100">
                                                        <span class="block h-2 rounded-full bg-aksen" :style="{ width: persenLebar(r.realisasi_persen) }"></span>
                                                        <span class="absolute -top-1 h-4 w-0.5 bg-slate-900" :style="{ left: persenLebar(r.rencana_persen) }"></span>
                                                    </span>
                                                    <span class="angka w-20 text-right text-[11px] text-slate-700" x-text="angka(r.realisasi_persen, 0) + '% / ' + angka(r.rencana_persen, 0) + '%'"></span>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap py-2"><span :class="'badge-' + r.status"><span :class="'titik-' + r.status"></span><span x-text="labelStatus(r.status)"></span></span></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <p x-show="!(d.roKritis ?? []).length" class="py-6 text-center text-slate-500">Tidak ada KP/RO critical path berstatus Berisiko atau Terlambat pada cut-off dan filter ini.</p>
                        </div>
                    </x-panel>
                </div>

                {{-- Kolom kanan (1/3) --}}
                <div class="col-span-12 flex flex-col gap-5 xl:col-span-4">
                    <x-panel kode="P7" judul="Peta Sebaran PSN per Provinsi" grafik="peta">
                        <x-slot:subjudul>Jumlah PSN berdasarkan lokasi; klik titik untuk memfilter provinsi</x-slot:subjudul>
                        <div x-ref="peta" class="h-[240px] rounded-lg bg-gradient-to-b from-sky-50 to-white" role="img" aria-label="Sebaran PSN per provinsi dan pulau"></div>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-600">
                            <template x-for="k in kelasPeta" :key="k.label"><span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" :style="{ background: k.warna }"></span><span x-text="k.label"></span></span></template>
                            <a :href="$store.filter.tautan('/peta')" class="ml-auto font-semibold text-aksen-700 hover:underline">Peta lengkap →</a>
                        </div>
                    </x-panel>

                    <x-panel kode="P6" judul="Komposisi Sumber Pendanaan" grafik="dana" class="flex-1">
                        <x-slot:subjudul><span x-text="'Berdasarkan ' + angka(totalPsn, 0) + ' PSN aktif; tiap PSN dihitung sekali'"></span></x-slot:subjudul>
                        <div class="flex flex-wrap items-center gap-5">
                            <div class="relative h-48 w-48 shrink-0">
                                <div x-ref="dana" class="h-48 w-48" role="img" aria-label="Komposisi investasi per kombinasi skema pendanaan"></div>
                                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                    <span class="angka text-[17px] font-bold text-slate-900" x-text="'Rp ' + angka(totalInvestasi) + ' T'"></span>
                                    <span class="text-[11px] text-slate-500">Total investasi</span>
                                </div>
                            </div>
                            <ul class="min-w-[9rem] flex-1 space-y-2">
                                <template x-for="s in d.komposisi ?? []" :key="s.kode">
                                    <li class="flex items-center gap-2 text-[12px]">
                                        <span class="h-3 w-3 shrink-0 rounded-full" :style="{ background: $data.segmenDana.find(x => x.kode === s.kode)?.warna ?? '#cbd5e1' }"></span>
                                        <span class="min-w-0 flex-1 text-slate-700" x-text="s.label" :title="angka(s.jumlah, 0) + ' PSN · Rp ' + angka(s.investasi_triliun) + ' T'"></span>
                                        <b class="angka text-slate-900" x-text="s.persen === null ? '–' : (s.investasi_triliun > 0 && s.persen < 0.1 ? '< 0,1%' : angka(s.persen) + '%')"></b>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </x-panel>
                </div>
            </div>

            {{-- Baris bawah: proporsi kolom mengikuti rancangan pada layar lebar --}}
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 2xl:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)_minmax(0,1.5fr)_minmax(0,1.15fr)]">
                <x-panel kode="P5" judul="Tren Progres Fisik Bulanan" grafik="p5" >
                    <x-slot:subjudul><span x-text="'Kumulatif rencana vs realisasi ' + (d.tren?.tahun ?? '') + ' — dari snapshot cut-off terbit'"></span></x-slot:subjudul>
                    <div x-ref="p5" class="h-60" role="img" aria-label="Grafik garis rencana dan realisasi progres fisik kumulatif per bulan"></div>
                </x-panel>

                <x-panel kode="TAHAPAN" judul="Tahapan Status PSN" >
                    <x-slot:subjudul>Jumlah PSN per tahap</x-slot:subjudul>
                    <ol class="space-y-3">
                        <template x-for="(t, i) in d.tahapan ?? []" :key="t.kode">
                            <li>
                                <a :href="$store.filter.tautan('/proyek') + ($store.filter.qs() ? '&' : '?') + 'tahap=' + t.kode" class="flex items-center gap-2.5 rounded-md hover:bg-slate-50">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-[12px] font-bold" :class="t.kode === 'TANPA' ? 'bg-slate-100 text-slate-500' : 'bg-primer text-white'" x-text="t.kode === 'TANPA' ? '–' : i + 1"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex justify-between text-[12px]"><span class="truncate text-slate-700" x-text="t.label"></span><b class="angka text-slate-900" x-text="angka(t.jumlah, 0)"></b></span>
                                        <span class="mt-1 block h-1.5 rounded-full bg-slate-100"><span class="block h-1.5 rounded-full" :class="t.kode === 'TANPA' ? 'bg-slate-400' : 'bg-aksen'" :style="{ width: persenLebar(persen(t.jumlah)) }"></span></span>
                                    </span>
                                </a>
                            </li>
                        </template>
                    </ol>
                </x-panel>

                <x-panel kode="TIMELINE_DP" judul="Timeline PSN Klaster Direktif Presiden menuju 2029" >
                    <x-slot:subjudul>Target tahun selesai & status progres; garis merah = cut-off</x-slot:subjudul>
                    <div class="mb-1 ml-[calc(42%+0.5rem)] flex text-[10px] text-slate-500" aria-hidden="true">
                        <template x-for="th in timeline.tahun" :key="th"><span class="flex-1 border-l border-slate-200 pl-1" x-text="th"></span></template>
                    </div>
                    <ul class="space-y-2">
                        <template x-for="p in timeline.baris" :key="p.psn_id">
                            <li class="flex items-center gap-2">
                                <a :href="$store.filter.tautan('/proyek/' + p.psn_id)" class="w-[42%] min-w-0 shrink-0 hover:underline" :title="p.nama + (p.direktorat ? ' — ' + p.direktorat : '')">
                                    <span class="block truncate text-[11px] font-semibold text-slate-800" x-text="p.nama"></span>
                                    <span class="block truncate text-[10px] text-slate-500" x-text="(p.tahun_selesai ? 'Selesai ' + p.tahun_selesai : 'Tahun selesai belum ada') + ' · ' + labelStatus(p.status)"></span>
                                </a>
                                <span class="relative h-3 flex-1 rounded-full bg-slate-100">
                                    <span class="absolute inset-y-0 left-0 rounded-full" :style="{ width: p.lebar, background: { TERLAMBAT: '#dc2626', BERISIKO: '#d97706', ON_TRACK: '#16a34a' }[p.status] ?? '#94a3b8' }"></span>
                                    <span class="absolute -inset-y-1 w-0.5 bg-red-600" :style="{ left: timeline.hariIni }" aria-hidden="true"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                    <p class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-slate-500">
                        <span x-text="'Menampilkan ' + timeline.baris.length + ' dari ' + (timeline.total ?? 0) + ' PSN DP'"></span>
                        @if ($idKlasterDp)<a :href="'/proyek?klaster={{ $idKlasterDp }}'" class="ml-auto font-semibold text-aksen-700 hover:underline">Lihat semua →</a>@endif
                    </p>
                </x-panel>

                <section class="kartu" aria-labelledby="judul-aktivitas">
                    <h2 id="judul-aktivitas" class="judul-panel">Aktivitas Terbaru</h2>
                    <p class="mb-3 mt-0.5 text-label font-normal text-slate-500">Riwayat perubahan data PSN</p>
                    <ul class="space-y-3">
                        <template x-for="(a, i) in (d.aktivitas ?? []).slice(0, 6)" :key="i">
                            <li class="flex gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="ikonAksi(a.aksi)[1]">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="ikonAksi(a.aksi)[0]"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[12px] font-semibold leading-snug text-slate-800" x-text="teksAktivitas(a)"></span>
                                    <template x-if="a.psn"><a class="block truncate text-[11px] text-aksen-700 hover:underline" :href="$store.filter.tautan('/proyek/' + a.psn_id)" x-text="a.psn"></a></template>
                                    <span class="block text-[11px] text-slate-500" x-text="waktuRelatif(a.waktu)"></span>
                                </span>
                            </li>
                        </template>
                    </ul>
                    <p x-show="!(d.aktivitas ?? []).length" class="text-slate-500">Belum ada aktivitas.</p>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
