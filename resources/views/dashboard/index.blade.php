@php
    $kamus = \App\Support\Dashboard\KamusIndikator::DAFTAR;
    $idKlasterDp = \Illuminate\Support\Facades\DB::table('ref_klaster')->where('kode', 'DP')->value('id');
    $ikonInfo = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>';
    $kpi = [
        'K1' => ['Total Proyek PSN', 'M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1'],
        'K2' => ['Total Investasi', 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'],
        'K3' => ['Rata-rata Progres Fisik', 'M3 3v18h18M7 15l4-4 3 3 5-6'],
        'K4' => ['Risiko Kritis', 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
    ];
    // Tinggi grafik: tetap di layar kecil (bergulir), mengisi sisa panel pada mode satu layar.
    $isiPanel = 'h-56 layar:h-auto layar:min-h-0 layar:flex-1';
@endphp
<x-app-layout judul="Dashboard Monitoring & Evaluasi PSN" subjudul="Berdasarkan Data Proyek Strategis Nasional" :layar-penuh="true">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>
    <x-slot:aksi>
        {{-- Status data (diisi halaman dashboard melalui Alpine.store('statusDasbor')) --}}
        <div class="flex items-center gap-3 text-[12px] text-slate-600" x-show="$store.statusDasbor.tanggal" x-cloak aria-label="Status data">
            <span class="hidden min-[1700px]:inline">Cut-off <b class="text-slate-900" x-text="$store.statusDasbor.tanggal"></b></span>
            <span class="hidden min-[1800px]:inline">Kelengkapan <b class="angka text-slate-900" x-text="$store.statusDasbor.kelengkapan"></b></span>
            <span class="hidden min-[1880px]:inline"><b class="angka text-slate-900" x-text="$store.statusDasbor.belum"></b> belum terverifikasi</span>
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
             class="grid grid-cols-1 gap-2.5 md:grid-cols-2 layar:h-full layar:grid-cols-24 layar:grid-rows-[auto_repeat(3,minmax(0,1fr))]">

            {{-- Baris 1: K1-K4 (klik -> Portofolio dengan filter yang sama) --}}
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:col-span-2 xl:grid-cols-4 layar:col-span-full">
                @foreach ($kpi as $k => [$judul, $ikon])
                    <a :href="$store.filter.tautan('/proyek') + ({{ $k === 'K4' ? 'true' : 'false' }} ? ($store.filter.qs() ? '&' : '?') + 'kritis=1' : '')"
                       class="kartu flex items-center gap-3 !px-4 !py-2.5 transition hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-aksen"
                       aria-labelledby="kpi-{{ $k }}">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white shadow" :style="{ background: warnaKpi('{{ $k }}') }">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $ikon }}"/></svg>
                        </span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1">
                                <span id="kpi-{{ $k }}" class="truncate text-[12px] font-bold text-slate-700">{{ $judul }}</span>
                                <span class="relative" x-data="{ buka: false }" @click.prevent.stop="buka = !buka" @click.outside="buka = false">
                                    <span class="ikon-tombol !h-5 !w-5" role="button" tabindex="0" aria-label="Informasi {{ $judul }}" @keydown.enter.prevent.stop="buka = !buka">{!! $ikonInfo !!}</span>
                                    <span x-show="buka" x-cloak role="tooltip" class="absolute left-0 z-40 mt-1 block w-72 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-label font-normal text-slate-700 shadow-lg" x-text="info('{{ $k }}')"></span>
                                </span>
                            </div>
                            <div class="angka truncate text-[22px] font-bold leading-7 text-slate-900" x-text="nilaiKartu(kartu('{{ $k }}'))"></div>
                            <div class="flex items-center gap-1.5 text-[11px] text-slate-500">
                                <span class="angka inline-flex items-center gap-0.5 rounded px-1 font-bold" :class="kelasDelta(kartu('{{ $k }}'))">
                                    <span aria-hidden="true" x-text="panahDelta(kartu('{{ $k }}'))"></span><span x-text="deltaRingkas(kartu('{{ $k }}'))"></span>
                                </span>
                                <span class="truncate">
                                    @switch($k)
                                        @case('K1') PSN aktif @break
                                        @case('K2') investasi PSN aktif @break
                                        @case('K3') <span x-text="angka(kartu('K3')?.cakupan_psn, 0) + ' PSN berdata'"></span> @break
                                        @case('K4') <span x-text="'dari ' + angka(totalPsn, 0) + ' PSN'"></span> @break
                                    @endswitch
                                </span>
                            </div>
                        </div>
                        <div x-ref="spark_{{ $k }}" class="ml-auto h-10 w-20 shrink-0 min-[1700px]:w-32" role="img" aria-label="Tren {{ $judul }} per cut-off terbit"></div>
                    </a>
                @endforeach
            </div>

            {{-- Baris 2 --}}
            <x-panel kode="P1" judul="Distribusi PSN per Klaster" padat class="layar:col-[span_7/span_7]">
                <x-slot:subjudul><span x-text="(d.klaster ?? []).filter(k => k.label !== 'Lainnya').length + ' klaster teratas dari ' + jumlahKlasterTerdaftar + ' terdaftar · klik untuk memfilter'"></span></x-slot:subjudul>
                <ul class="flex flex-col justify-between layar:min-h-full">
                    <template x-for="k in klasterBaris" :key="k.label">
                        <li>
                            <button type="button" class="grid w-full grid-cols-[minmax(0,42%)_1fr_auto] items-center gap-2 rounded text-left hover:bg-slate-50 disabled:cursor-default disabled:hover:bg-transparent"
                                    :disabled="!k.id" @click="k.id && $store.filter.tambah('klaster', k.id)" :title="k.label + ': ' + k.jumlah + ' PSN'">
                                <span class="truncate text-[11px] leading-[15px] text-slate-700" x-text="k.label"></span>
                                <span class="h-2 rounded-full bg-slate-100"><span class="block h-2 rounded-full bg-aksen" :style="{ width: k.lebar }"></span></span>
                                <span class="angka w-[5.5rem] text-right text-[11px] leading-[15px]"><b class="text-slate-900" x-text="angka(k.jumlah, 0)"></b> <span class="text-slate-500" x-text="'(' + angka(k.pct) + '%)'"></span></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </x-panel>

            <x-panel kode="P7" judul="Peta Sebaran PSN per Provinsi" grafik="peta" padat class="layar:col-[span_6/span_6]">
                <x-slot:subjudul>Klik titik untuk memfilter provinsi · <a :href="$store.filter.tautan('/peta')" class="font-semibold text-aksen-700 hover:underline">peta lengkap</a></x-slot:subjudul>
                <div class="flex flex-col layar:h-full">
                    <div x-ref="peta" class="{{ $isiPanel }} rounded-lg bg-gradient-to-b from-sky-50 to-white" role="img" aria-label="Sebaran PSN per provinsi dan pulau"></div>
                    <div class="mt-1 flex flex-wrap gap-x-3 text-[10.5px] text-slate-600">
                        <template x-for="k in kelasPeta" :key="k.label"><span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full" :style="{ background: k.warna }"></span><span x-text="k.label"></span></span></template>
                    </div>
                </div>
            </x-panel>

            <x-panel kode="P2" judul="Progres Fisik & Keuangan Tahun Berjalan" padat class="layar:col-[span_6/span_6]">
                <x-slot:subjudul>Realisasi vs rencana kumulatif · garis hitam = rencana</x-slot:subjudul>
                <ul class="flex flex-col justify-around gap-1 layar:min-h-full">
                    <template x-for="(b, i) in progresBaris" :key="b.label">
                        <li :title="b.catatan">
                            <div class="flex items-center justify-between gap-2 text-[11.5px]">
                                <span class="truncate font-semibold text-slate-700" x-text="b.label"></span>
                                <span x-show="i === 0" :class="'badge-' + (d.progres?.P2.status ?? 'TANPA_DATA')" class="!py-0 !text-[10px]"><span :class="'titik-' + (d.progres?.P2.status ?? 'TANPA_DATA')"></span><span x-text="labelStatus(d.progres?.P2.status)"></span></span>
                                <span class="flex-1"></span><span class="sr-only" x-text="b.catatan"></span>
                                <span class="angka shrink-0 font-bold text-slate-900" x-text="b.kanan"></span>
                            </div>
                            <div class="relative mt-1 h-2 rounded-full bg-slate-100" role="img" :aria-label="b.label + ': ' + b.kanan">
                                <div class="h-2 rounded-full" :style="{ width: persenLebar(b.nilai), background: b.warna }"></div>
                                <div x-show="b.target !== null" class="absolute -top-1 h-4 w-0.5 rounded bg-slate-900" :style="{ left: persenLebar(b.target) }"></div>
                            </div>
                        </li>
                    </template>
                </ul>
            </x-panel>

            <x-panel kode="P6" judul="Komposisi Sumber Pendanaan" grafik="dana" padat class="layar:col-[span_5/span_5]">
                <x-slot:subjudul>Tiap PSN dihitung sekali menurut kombinasi skemanya</x-slot:subjudul>
                <div class="flex items-center gap-3 layar:h-full">
                    <div class="relative aspect-square h-40 shrink-0 layar:h-full layar:max-h-[11rem]">
                        <div x-ref="dana" class="h-full w-full" role="img" aria-label="Komposisi investasi per kombinasi skema pendanaan"></div>
                        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                            <span class="angka text-[13px] font-bold text-slate-900" x-text="'Rp ' + angka(totalInvestasi, totalInvestasi < 100 ? 1 : 0) + ' T'"></span>
                            <span class="text-[10px] text-slate-500">Total investasi</span>
                        </div>
                    </div>
                    <ul class="min-w-0 flex-1 space-y-1">
                        <template x-for="s in d.komposisi ?? []" :key="s.kode">
                            <li class="flex items-center gap-1.5 text-[11px]" :title="angka(s.jumlah, 0) + ' PSN · Rp ' + angka(s.investasi_triliun) + ' T'">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: $data.segmenDana.find(x => x.kode === s.kode)?.warna ?? '#cbd5e1' }"></span>
                                <span class="min-w-0 flex-1 truncate text-slate-700" x-text="s.label"></span>
                                <b class="angka text-slate-900" x-text="s.persen === null ? '–' : (s.investasi_triliun > 0 && s.persen < 0.1 ? '<0,1%' : angka(s.persen) + '%')"></b>
                            </li>
                        </template>
                    </ul>
                </div>
            </x-panel>

            {{-- Baris 3 --}}
            <x-panel kode="DIREKTORAT" judul="Sebaran PSN per Direktorat" padat class="layar:col-[span_6/span_6]">
                <x-slot:subjudul><span x-text="'5 teratas dari ' + (d.direktorat ?? []).length + ' direktorat · PSN multi-pengampu dihitung di tiap direktorat'"></span></x-slot:subjudul>
                <ul class="flex flex-col justify-around divide-y divide-slate-100 layar:min-h-full">
                    <template x-for="r in direktoratTop" :key="r.id">
                        <li>
                            <button type="button" class="flex w-full items-center gap-2 py-1 text-left hover:bg-slate-50" @click="$store.filter.tambah('dit', r.id)" :title="r.label + ' — klik untuk memfilter'">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-aksen" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1 truncate text-[11.5px] text-slate-700" x-text="r.label.replace(/^Direktorat\s+/i, '')"></span>
                                <b class="angka text-[11.5px] text-slate-900" x-text="angka(r.jumlah, 0)"></b>
                                <span class="angka w-12 text-right text-[10.5px] text-slate-500" x-text="'(' + angka(r.pct) + '%)'"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </x-panel>

            <x-panel kode="RO_KRITIS" judul="RO Critical Path Berisiko & Terlambat" padat class="layar:col-[span_7/span_7]">
                <x-slot:subjudul>5 teratas menurut selisih realisasi terhadap rencana</x-slot:subjudul>
                <div class="min-h-0 overflow-auto layar:flex-1">
                    <table class="w-full text-left text-[11.5px]">
                        <thead class="sticky top-0 bg-white text-[10.5px] text-slate-500">
                            <tr><th class="py-1 pr-2">PSN / KP-RO</th><th class="py-1 pr-2">Direktorat</th><th class="w-36 py-1 pr-2">Progres</th><th class="py-1">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="r in d.roKritis ?? []" :key="r.kegiatan_id">
                                <tr>
                                    <td class="max-w-[12rem] py-1 pr-2">
                                        <a class="block truncate font-semibold text-slate-900 hover:underline" :href="$store.filter.tautan('/proyek/' + r.psn_id)" :title="r.psn" x-text="r.psn"></a>
                                        <span class="block truncate text-[10.5px] text-slate-500" :title="r.kegiatan" x-text="r.kegiatan"></span>
                                    </td>
                                    <td class="max-w-[9rem] truncate py-1 pr-2 text-slate-700" :title="r.direktorat" x-text="(r.direktorat ?? '–').replace(/^Direktorat\s+/i, '')"></td>
                                    <td class="py-1 pr-2">
                                        <span class="relative block h-1.5 rounded-full bg-slate-100">
                                            <span class="block h-1.5 rounded-full bg-aksen" :style="{ width: persenLebar(r.realisasi_persen) }"></span>
                                            <span class="absolute -top-1 h-3.5 w-0.5 bg-slate-900" :style="{ left: persenLebar(r.rencana_persen) }"></span>
                                        </span>
                                        <span class="angka text-[10.5px] text-slate-600" x-text="angka(r.realisasi_persen, 0) + '% / ' + angka(r.rencana_persen, 0) + '%'"></span>
                                    </td>
                                    <td class="whitespace-nowrap py-1"><span :class="'badge-' + r.status"><span :class="'titik-' + r.status"></span><span x-text="labelStatus(r.status)"></span></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="!(d.roKritis ?? []).length" class="py-6 text-center text-[12px] text-slate-500">Tidak ada KP/RO critical path berstatus Berisiko atau Terlambat pada cut-off dan filter ini.</p>
                </div>
            </x-panel>

            <x-panel kode="P5" judul="Tren Progres Fisik Bulanan" grafik="p5" padat class="layar:col-[span_6/span_6]">
                <x-slot:subjudul><span x-text="'Kumulatif rencana vs realisasi ' + (d.tren?.tahun ?? '') + ' per cut-off terbit'"></span></x-slot:subjudul>
                <div class="flex flex-col layar:h-full">
                    <div x-ref="p5" class="{{ $isiPanel }}" role="img" aria-label="Grafik garis rencana dan realisasi progres fisik kumulatif per bulan"></div>
                </div>
            </x-panel>

            <x-panel kode="TAHAPAN" judul="Tahapan Status PSN" padat class="layar:col-[span_5/span_5]">
                <x-slot:subjudul>Klik untuk melihat daftar PSN</x-slot:subjudul>
                <ol class="flex flex-col justify-around gap-0.5 layar:min-h-full">
                    <template x-for="(t, i) in d.tahapan ?? []" :key="t.kode">
                        <li>
                            <a :href="$store.filter.tautan('/proyek') + ($store.filter.qs() ? '&' : '?') + 'tahap=' + t.kode" class="flex items-center gap-2 rounded hover:bg-slate-50">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded text-[10.5px] font-bold" :class="t.kode === 'TANPA' ? 'bg-slate-100 text-slate-500' : 'bg-primer text-white'" x-text="t.kode === 'TANPA' ? '–' : i + 1"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex justify-between text-[11px] leading-[15px]"><span class="truncate text-slate-700" x-text="t.label"></span><b class="angka text-slate-900" x-text="angka(t.jumlah, 0)"></b></span>
                                    <span class="mt-0.5 block h-1.5 rounded-full bg-slate-100"><span class="block h-1.5 rounded-full" :class="t.kode === 'TANPA' ? 'bg-slate-400' : 'bg-aksen'" :style="{ width: persenLebar(persen(t.jumlah)) }"></span></span>
                                </span>
                            </a>
                        </li>
                    </template>
                </ol>
            </x-panel>

            {{-- Baris 4 --}}
            <x-panel kode="P8" judul="Kontribusi Trisula Pembangunan" padat class="layar:col-[span_6/span_6]">
                <x-slot:subjudul>% PSN aktif yang memiliki indikator — metodologi capaian belum ditetapkan</x-slot:subjudul>
                <div class="grid grid-cols-3 items-center gap-1 layar:h-full">
                    <template x-for="(t, i) in trisulaCincin" :key="t.judul">
                        <div class="flex h-full min-h-0 flex-col items-center justify-center text-center" :title="t.judul + ': ' + t.psn + ' PSN, ' + t.indikator + ' indikator'">
                            <svg viewBox="0 0 100 100" class="h-20 w-20 layar:h-auto layar:max-h-[5.5rem] layar:min-h-0 layar:w-auto layar:flex-1" role="img" :aria-label="t.judul + ': ' + angka(t.pct) + '% PSN aktif memiliki indikator'">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#eef2f7" stroke-width="11"/>
                                <circle x-show="t.pct > 0" cx="50" cy="50" r="42" fill="none" :stroke="['#1baf7a', '#eb6834', '#2a78d6'][i]" stroke-width="11" stroke-linecap="round" :stroke-dasharray="t.garis" transform="rotate(-90 50 50)"/>
                                <text x="50" y="57" text-anchor="middle" font-size="20" font-weight="700" fill="#0f172a" x-text="angka(t.pct, 0) + '%'"></text>
                            </svg>
                            <p class="mt-0.5 line-clamp-2 text-[10.5px] font-semibold leading-tight text-slate-700" x-text="t.judul"></p>
                            <p class="text-[10px] text-slate-500" x-text="t.psn + ' PSN'"></p>
                        </div>
                    </template>
                </div>
            </x-panel>

            <x-panel kode="TIMELINE_DP" judul="Timeline PSN Klaster Direktif Presiden menuju 2029" padat class="layar:col-[span_9/span_9]">
                <x-slot:subjudul>
                    <span x-text="timeline.baris.length + ' dari ' + (timeline.total ?? 0) + ' PSN DP · batang = s.d. tahun selesai · garis merah = cut-off'"></span>
                    @if ($idKlasterDp)· <a href="/proyek?klaster={{ $idKlasterDp }}" class="font-semibold text-aksen-700 hover:underline">lihat semua</a>@endif
                </x-slot:subjudul>
                <div class="flex flex-col layar:h-full">
                    <div class="mb-0.5 ml-[calc(36%+0.5rem)] mr-[4.5rem] flex text-[10px] text-slate-500" aria-hidden="true">
                        <template x-for="th in timeline.tahun" :key="th"><span class="flex-1 border-l border-slate-200 pl-1" x-text="th"></span></template>
                    </div>
                    <ul class="flex flex-col justify-around layar:min-h-0 layar:flex-1">
                        <template x-for="p in timeline.baris" :key="p.psn_id">
                            <li class="flex items-center gap-2">
                                <a :href="$store.filter.tautan('/proyek/' + p.psn_id)" class="w-[36%] shrink-0 truncate text-[11px] font-semibold leading-[15px] text-slate-800 hover:underline" :title="p.nama + (p.direktorat ? ' — ' + p.direktorat : '') + ' · selesai ' + (p.tahun_selesai ?? 'belum ada')" x-text="p.nama"></a>
                                <span class="relative h-2.5 flex-1 rounded-full bg-slate-100">
                                    <span class="absolute inset-y-0 left-0 rounded-full" :style="{ width: p.lebar, background: { TERLAMBAT: '#dc2626', BERISIKO: '#d97706', ON_TRACK: '#16a34a' }[p.status] ?? '#94a3b8' }"></span>
                                    <span class="absolute -inset-y-1 w-0.5 bg-red-600" :style="{ left: timeline.hariIni }" aria-hidden="true"></span>
                                </span>
                                <span class="w-16 shrink-0 truncate text-[10px] text-slate-600" x-text="labelStatus(p.status)"></span>
                            </li>
                        </template>
                        <li x-show="!timeline.baris.length" class="py-4 text-center text-[12px] text-slate-500">Tidak ada PSN Klaster Direktif Presiden pada filter ini.</li>
                    </ul>
                </div>
            </x-panel>

            <section class="kartu flex min-h-0 flex-col !p-3 md:col-span-2 layar:col-[span_9/span_9]" aria-labelledby="judul-aktivitas">
                <h2 id="judul-aktivitas" class="judul-panel !text-[13px] !leading-5">Aktivitas Terbaru</h2>
                <p class="mb-1.5 truncate text-[11px] text-slate-500">Riwayat perubahan data PSN</p>
                <ul class="min-h-0 divide-y divide-slate-100 overflow-y-auto layar:flex-1">
                    <template x-for="(a, i) in d.aktivitas ?? []" :key="i">
                        <li class="flex items-center gap-2 py-1">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="ikonAksi(a.aksi)[1]">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path :d="ikonAksi(a.aksi)[0]"/></svg>
                            </span>
                            <span class="min-w-0 flex-1 truncate text-[11.5px]">
                                <span class="font-semibold text-slate-800" x-text="teksAktivitas(a)"></span>
                                <template x-if="a.psn"><a class="text-aksen-700 hover:underline" :href="$store.filter.tautan('/proyek/' + a.psn_id)" x-text="' · ' + a.psn"></a></template>
                            </span>
                            <span class="shrink-0 text-[10.5px] text-slate-500" x-text="waktuRelatif(a.waktu)"></span>
                        </li>
                    </template>
                </ul>
                <p x-show="!(d.aktivitas ?? []).length" class="text-[12px] text-slate-500">Belum ada aktivitas.</p>
            </section>
        </div>
    </div>
</x-app-layout>
