{{-- Baris kartu KPI dashboard (K1-K4, opsional K5 PKPN): klik -> Portofolio dengan filter yang sama. --}}
@php($ikonInfo = '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>')
            <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:col-span-2 {{ $kolom }} layar:col-span-full">
                @foreach ($kpi as $k => [$judul, $ikon])
                    <a :href="$store.filter.tautan('/proyek') + ({{ in_array($k, ['K1', 'K4', 'K5']) ? 'true' : 'false' }} ? ($store.filter.qs() ? '&' : '?') + '{{ ['K1' => 'nonaktif=1', 'K4' => 'kritis=1', 'K5' => 'kat=pkpn'][$k] ?? '' }}' : '')"
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
                                        @case('K1') <span x-text="angka(kartu('K1')?.aktif, 0) + ' aktif · ' + angka(kartu('K1')?.keluar, 0) + ' keluar PSN'"></span> @break
                                        @case('K2') investasi PSN aktif @break
                                        @case('K3') <span x-text="angka(kartu('K3')?.cakupan_psn, 0) + ' PSN berdata'"></span> @break
                                        @case('K4') <span x-text="'dari ' + angka(totalPsn, 0) + ' PSN'"></span> @break
                                        @case('K5') <span x-text="'Rp ' + angka(kartu('K5')?.investasi_triliun, 1) + ' T · ' + angka(kartu('K5')?.persen_investasi) + '% investasi'"></span> @break
                                    @endswitch
                                </span>
                            </div>
                        </div>
                        @if ($k !== 'K5')<div x-ref="spark_{{ $k }}" @class(['ml-auto h-10 w-20 shrink-0 min-[1700px]:w-32', 'hidden min-[1800px]:block' => count($kpi) > 4]) role="img" aria-label="Tren {{ $judul }} per cut-off terbit"></div>@endif
                    </a>
                @endforeach
            </div>
