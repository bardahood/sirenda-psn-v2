<x-app-layout judul="Ringkasan Eksekutif">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>

    <div x-data="halamanDashboard(@js(\App\Support\Dashboard\KamusIndikator::DAFTAR))" class="mx-auto max-w-[1376px]">
        <div x-show="galat" x-cloak role="alert" class="mb-6 rounded-kartu border border-red-200 bg-red-50 p-4 text-red-800" x-text="galat"></div>

        <div x-show="!memuat && meta && !meta.cutoff" x-cloak class="kartu text-center">
            <p class="judul-panel">Belum ada cut-off yang diterbitkan</p>
            <p class="mt-1 text-slate-600">Jalankan <code class="rounded bg-slate-100 px-1">php artisan psn:snapshot YYYY-MM --terbit</code> untuk menerbitkan snapshot pertama.</p>
        </div>

        <div x-show="meta?.cutoff" x-cloak :aria-busy="memuat" :class="memuat && 'opacity-60 transition-opacity'" class="grid grid-cols-12 gap-6">

            {{-- Baris 2: status data --}}
            <section class="col-span-12 flex flex-wrap items-center gap-x-8 gap-y-2 rounded-kartu border border-primer-100 bg-primer-50 px-5 py-3" aria-label="Status data">
                <div><span class="label">Cut-off</span> <span class="ml-1 font-semibold text-primer" x-text="tanggalCutoff"></span></div>
                <div><span class="label">Kelengkapan rata-rata</span> <span class="angka ml-1 font-semibold text-primer" x-text="angka(d.statusData?.kelengkapan_persen) + '%'"></span></div>
                <div><span class="label">Belum terverifikasi</span> <span class="angka ml-1 font-semibold text-primer" x-text="angka(d.statusData?.belum_terverifikasi, 0) + ' dari ' + angka(d.statusData?.total_psn, 0) + ' PSN'"></span></div>
                <div class="ml-auto flex flex-wrap items-center gap-2" aria-label="Status progres">
                    <template x-for="s in d.progres?.status_progres ?? []" :key="s.kode">
                        <button type="button" :class="'badge-' + s.kode" @click="$store.filter.toggle('status', s.kode.toLowerCase())" :title="'Filter status ' + s.label">
                            <span :class="'titik-' + s.kode"></span><span x-text="s.label"></span><span class="angka font-semibold" x-text="s.jumlah"></span>
                        </button>
                    </template>
                </div>
                <a :href="'{{ route('laporan.ringkasan-pdf') }}' + ($store.filter.qs() ? '?' + $store.filter.qs() : '')" class="tombol-garis py-1 text-label">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>
                    Unduh PDF
                </a>
                <div class="relative" x-data="{ buka: false }" @click.outside="buka = false">
                    <button type="button" class="ikon-tombol" @click="buka = !buka" aria-label="Informasi status data">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                    </button>
                    <div x-show="buka" x-cloak role="tooltip" class="absolute right-0 z-30 mt-1 w-72 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-label font-normal text-slate-700 shadow-lg" x-text="info('STATUS_DATA')"></div>
                </div>
            </section>

            {{-- Baris 3: K1-K4. Klik kartu -> Portofolio dengan filter yang sama. --}}
            @foreach (['K1', 'K2', 'K3', 'K4'] as $k)
                <a :href="$store.filter.tautan('/proyek') + ({{ $k === 'K4' ? 'true' : 'false' }} ? ($store.filter.qs() ? '&' : '?') + 'kritis=1' : '')"
                   class="kartu col-span-12 block transition hover:border-aksen hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-aksen sm:col-span-6 xl:col-span-3"
                   aria-labelledby="kpi-{{ $k }}">
                    <div class="flex items-start gap-2">
                        <span id="kpi-{{ $k }}" class="label mr-auto uppercase tracking-wide">{{ \App\Support\Dashboard\KamusIndikator::DAFTAR[$k]['nama'] }}</span>
                        <span class="relative" x-data="{ buka: false }" @click.prevent.stop="buka = !buka" @click.outside="buka = false">
                            <span class="ikon-tombol" role="button" tabindex="0" aria-label="Informasi {{ \App\Support\Dashboard\KamusIndikator::DAFTAR[$k]['nama'] }}" @keydown.enter.prevent.stop="buka = !buka">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                            </span>
                            <span x-show="buka" x-cloak role="tooltip" class="absolute right-0 z-30 mt-1 block w-72 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-label font-normal normal-case tracking-normal text-slate-700 shadow-lg" x-text="info('{{ $k }}')"></span>
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="angka text-kpi text-primer" x-text="nilaiKartu(kartu('{{ $k }}'))"></span>
                        @if ($k === 'K2') <span class="text-isi text-slate-600">Rp triliun</span> @endif
                        @if ($k === 'K1' || $k === 'K4') <span class="text-isi text-slate-600">PSN</span> @endif
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="angka inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-label" :class="kelasDelta(kartu('{{ $k }}'))">
                            <span aria-hidden="true" x-text="panahDelta(kartu('{{ $k }}'))"></span><span x-text="teksDelta(kartu('{{ $k }}'))"></span>
                        </span>
                        @if ($k === 'K3')
                            <span class="label" x-text="'dari ' + angka(kartu('K3')?.cakupan_psn, 0) + ' PSN berdata'"></span>
                        @endif
                    </div>
                </a>
            @endforeach

            {{-- Baris 4: P2+P3 [6], P5 [6] --}}
            <x-panel kode="P2" judul="Progres Fisik & Realisasi Anggaran" class="col-span-12 md:col-span-6">
                <div class="space-y-6">
                    <div>
                        <div class="flex items-baseline justify-between">
                            <span class="label">Progres fisik (tertimbang investasi)</span>
                            <span :class="'badge-' + (d.progres?.P2.status ?? 'TANPA_DATA')"><span :class="'titik-' + (d.progres?.P2.status ?? 'TANPA_DATA')"></span><span x-text="labelStatus(d.progres?.P2.status)"></span></span>
                        </div>
                        <div class="mt-2 flex items-baseline gap-4">
                            <span class="angka text-kpi text-primer" x-text="angka(d.progres?.P2.realisasi_persen) + '%'"></span>
                            <span class="angka text-isi text-slate-600">rencana <b class="text-slate-800" x-text="angka(d.progres?.P2.rencana_persen) + '%'"></b> · deviasi <b class="text-slate-800" x-text="angka(d.progres?.P2.deviasi_pp) + ' pp'"></b></span>
                        </div>
                        {{-- Bullet: batang realisasi + penanda rencana --}}
                        <div class="relative mt-3 h-3 rounded-full bg-slate-100" role="img" :aria-label="'Realisasi ' + angka(d.progres?.P2.realisasi_persen) + '% dari rencana ' + angka(d.progres?.P2.rencana_persen) + '%'">
                            <div class="h-3 rounded-full bg-aksen" :style="{ width: persenLebar(d.progres?.P2.realisasi_persen) }"></div>
                            <div class="absolute -top-1 h-5 w-0.5 bg-slate-900" :style="{ left: persenLebar(d.progres?.P2.rencana_persen) }" title="Rencana"></div>
                        </div>
                        <p class="label mt-2" x-text="'Dihitung dari ' + angka(d.progres?.P2.cakupan_psn, 0) + ' dari ' + angka(d.progres?.P2.total_psn, 0) + ' PSN yang memiliki data progres terlapor.'"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-6 border-t border-slate-100 pt-4">
                        <div>
                            <span class="label">Realisasi anggaran <span x-text="d.progres?.P3.tahun"></span></span>
                            <div class="angka mt-1 text-2xl font-bold text-primer" x-text="d.progres?.P3.persen === null ? '–' : angka(d.progres?.P3.persen) + '%'"></div>
                            <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-aksen" :style="{ width: persenLebar(d.progres?.P3.persen) }"></div></div>
                            <p class="label angka mt-2" x-text="'Rp ' + angka(d.progres?.P3.realisasi_triliun, 2) + ' T dari pagu Rp ' + angka(d.progres?.P3.pagu_triliun, 2) + ' T'"></p>
                        </div>
                        <div>
                            <span class="label">KP/RO tercapai</span>
                            <div class="angka mt-1 text-2xl font-bold text-primer" x-text="d.progres?.P4.persen === null ? '–' : angka(d.progres?.P4.persen) + '%'"></div>
                            <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-aksen" :style="{ width: persenLebar(d.progres?.P4.persen) }"></div></div>
                            <p class="label angka mt-2" x-text="angka(d.progres?.P4.tercapai, 0) + ' dari ' + angka(d.progres?.P4.total, 0) + ' KP/RO'"></p>
                        </div>
                    </div>
                </div>
            </x-panel>

            <x-panel kode="P5" judul="Tren Bulanan Progres Fisik" grafik="p5" class="col-span-12 md:col-span-6">
                <div x-ref="p5" class="h-72" role="img" aria-label="Grafik garis rencana dan realisasi progres fisik kumulatif per bulan"></div>
            </x-panel>

            {{-- Baris 5: RO critical path [8], Tahapan [4] --}}
            <x-panel kode="RO_KRITIS" judul="KP/RO Critical Path Berisiko (10 terburuk)" class="col-span-12 xl:col-span-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-isi">
                        <thead class="border-b border-slate-200 text-label text-slate-600">
                            <tr><th class="py-2 pr-3">KP/RO</th><th class="py-2 pr-3">PSN</th><th class="py-2 pr-3 text-right">Rencana</th><th class="py-2 pr-3 text-right">Realisasi</th><th class="py-2 pr-3 text-right">Deviasi</th><th class="py-2">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="r in d.roKritis ?? []" :key="r.kegiatan_id">
                                <tr>
                                    <td class="max-w-[13rem] truncate py-2 pr-3" :title="r.kegiatan" x-text="r.kegiatan"></td>
                                    <td class="max-w-[11rem] truncate py-2 pr-3"><a class="text-aksen-700 hover:underline" :href="$store.filter.tautan('/proyek/' + r.psn_id)" :title="r.psn" x-text="r.psn"></a></td>
                                    <td class="angka py-2 pr-3 text-right" x-text="angka(r.rencana_persen) + '%'"></td>
                                    <td class="angka py-2 pr-3 text-right" x-text="angka(r.realisasi_persen) + '%'"></td>
                                    <td class="angka py-2 pr-3 text-right font-semibold" x-text="angka(r.deviasi_pp) + ' pp'"></td>
                                    <td class="whitespace-nowrap py-2"><span :class="'badge-' + r.status"><span :class="'titik-' + r.status"></span><span x-text="labelStatus(r.status)"></span></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="!(d.roKritis ?? []).length" class="py-6 text-center text-slate-500">Tidak ada KP/RO critical path berstatus Berisiko atau Terlambat pada cut-off dan filter ini.</p>
                </div>
            </x-panel>

            <x-panel kode="TAHAPAN" judul="Tahapan Status" grafik="tahapan" class="col-span-12 md:col-span-6 xl:col-span-4">
                <div x-ref="tahapan" class="h-64" role="img" aria-label="Jumlah PSN per tahapan"></div>
            </x-panel>

            {{-- Baris 6: P1 [4], P7 [5], P6 [3] --}}
            <x-panel kode="P1" judul="Distribusi Klaster" grafik="p1" class="col-span-12 md:col-span-6 xl:col-span-4">
                <div x-ref="p1" class="h-80" role="img" aria-label="Jumlah PSN per klaster"></div>
            </x-panel>

            <x-panel kode="P7" judul="Sebaran Provinsi (12 teratas)" grafik="p7" class="col-span-12 md:col-span-6 xl:col-span-5">
                <div x-ref="p7" class="h-80" role="img" aria-label="Jumlah PSN per provinsi"></div>
                <p class="label mt-2">PSN multi-lokasi dihitung di setiap provinsinya. Peta choropleth menyusul setelah sumber GeoJSON ditetapkan.</p>
            </x-panel>

            <x-panel kode="P6" judul="Sumber Pendanaan" grafik="p6" class="col-span-12 md:col-span-6 xl:col-span-3">
                <div x-ref="p6" class="h-64" role="img" aria-label="Investasi per skema pendanaan (Rp triliun)"></div>
                <p class="label mt-2">Rp triliun. PSN multi-sumber dihitung penuh di tiap skema.</p>
            </x-panel>

            {{-- Baris 7: P8 [6], Timeline DP [6] --}}
            <x-panel kode="P8" judul="Kontribusi Trisula" class="col-span-12 md:col-span-6">
                <div class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-label text-amber-900" role="note">
                    Metodologi belum ditetapkan. Data di bawah ini hanya gambaran jumlah indikator, bukan nilai kontribusi.
                </div>
                <ul class="divide-y divide-slate-100">
                    <template x-for="k in d.trisula?.kategori ?? []" :key="k.label">
                        <li class="flex items-center justify-between py-2"><span x-text="k.label"></span><span class="angka text-slate-600" x-text="k.indikator + ' indikator · ' + k.psn + ' PSN'"></span></li>
                    </template>
                </ul>
            </x-panel>

            <x-panel kode="TIMELINE_DP" judul="Klaster Direktif Presiden menuju 2029" grafik="dp" class="col-span-12 md:col-span-6">
                <div x-ref="dp" class="h-64" role="img" aria-label="Jumlah PSN klaster Direktif Presiden per tahun target selesai"></div>
            </x-panel>

            {{-- Aktivitas terbaru (jejak audit) --}}
            <section class="kartu col-span-12" aria-labelledby="judul-aktivitas">
                <h2 id="judul-aktivitas" class="judul-panel mb-3">Aktivitas Terbaru</h2>
                <ul class="divide-y divide-slate-100">
                    <template x-for="(a, i) in d.aktivitas ?? []" :key="i">
                        <li class="flex flex-wrap items-baseline gap-x-2 py-2">
                            <span x-text="teksAktivitas(a)"></span>
                            <template x-if="a.psn"><a class="max-w-md truncate text-aksen-700 hover:underline" :href="$store.filter.tautan('/proyek/' + a.psn_id)" x-text="a.psn"></a></template>
                            <span class="label ml-auto" x-text="waktuRelatif(a.waktu)"></span>
                        </li>
                    </template>
                </ul>
                <p x-show="!(d.aktivitas ?? []).length" class="text-slate-500">Belum ada aktivitas.</p>
            </section>
        </div>
    </div>
</x-app-layout>
