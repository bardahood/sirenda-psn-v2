<x-app-layout judul="Kualitas Data">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>

    <div x-data="halamanKualitas" class="mx-auto max-w-[1376px]">
        <div x-show="galat" x-cloak role="alert" class="mb-6 rounded-kartu border border-red-200 bg-red-50 p-4 text-red-800" x-text="galat"></div>
        <div x-show="!memuat && meta && !meta.cutoff" x-cloak class="kartu text-center"><p class="judul-panel">Belum ada cut-off yang diterbitkan</p></div>

        <div x-show="d" x-cloak class="grid grid-cols-12 gap-6" :class="memuat && 'opacity-60'" :aria-busy="memuat">
            <section class="col-span-12 flex flex-wrap items-center gap-x-8 gap-y-2 rounded-kartu border border-primer-100 bg-primer-50 px-5 py-3" aria-label="Periode">
                <div><span class="label">Cut-off</span> <span class="ml-1 font-semibold text-primer" x-text="meta?.cutoff?.tanggal"></span></div>
                <div><span class="label">Dibandingkan dengan</span> <span class="ml-1 font-semibold text-primer" x-text="pembanding() ?? 'tidak ada cut-off sebelumnya'"></span></div>
                <p class="label ml-auto">Pilih cut-off lain melalui filter Periode di atas. "Sektor" sementara = direktorat pengampu.</p>
            </section>

            {{-- KPI --}}
            @foreach ([
                ['kelengkapan', 'Kelengkapan rata-rata', "angka(d?.kpi.kelengkapan.nilai) + '%'", 'Field wajib terisi ÷ field wajib × 100, dirata-rata atas PSN aktif.'],
                ['sektor_tepat_waktu', 'Sektor tepat waktu', "angka(d?.kpi.sektor_tepat_waktu.nilai, 0) + ' / ' + angka(d?.kpi.sektor_tepat_waktu.total_sektor, 0)", 'Direktorat yang seluruh PSN-nya diajukan paling lambat tanggal cut-off.'],
                ['belum_diperbarui', 'Proyek belum diperbarui', "angka(d?.kpi.belum_diperbarui.nilai, 0) + ' / ' + angka(d?.kpi.belum_diperbarui.total_psn, 0)", 'PSN aktif tanpa satu pun perubahan tercatat (jejak audit) sejak cut-off sebelumnya.'],
                ['menunggu_verifikasi', 'Menunggu verifikasi', "angka(d?.kpi.menunggu_verifikasi.nilai, 0)", 'Pengisian berstatus Diajukan dan belum diverifikasi direktorat.'],
            ] as [$k, $judul, $nilai, $rumus])
                <section class="kartu col-span-12 sm:col-span-6 xl:col-span-3" aria-label="{{ $judul }}">
                    <div class="flex items-start gap-2">
                        <span class="label mr-auto uppercase tracking-wide">{{ $judul }}</span>
                        <span class="relative" x-data="{ buka: false }" @click.outside="buka = false">
                            <button type="button" class="ikon-tombol" @click="buka = !buka" aria-label="Informasi {{ $judul }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                            </button>
                            <span x-show="buka" x-cloak role="tooltip" class="absolute right-0 z-30 mt-1 block w-64 rounded-lg border border-slate-200 bg-white p-3 text-label font-normal normal-case tracking-normal text-slate-700 shadow-lg"
                                  x-text="'{{ $rumus }}\nCut-off: ' + (meta?.cutoff?.tanggal ?? '')" style="white-space: pre-line"></span>
                        </span>
                    </div>
                    <div class="angka mt-2 text-kpi text-primer" x-text="{{ $nilai }}"></div>
                    <span class="angka mt-2 inline-flex rounded-md px-1.5 py-0.5 text-label" :class="kelasDelta(d?.kpi.{{ $k }})" x-text="teksDelta(d?.kpi.{{ $k }})"></span>
                </section>
            @endforeach

            <section class="kartu col-span-12 xl:col-span-6" aria-labelledby="h-sektor">
                <h2 id="h-sektor" class="judul-panel mb-3">Kelengkapan per Sektor</h2>
                <div x-ref="sektor" :style="{ height: tinggiSektor }" role="img" aria-label="Rata-rata kelengkapan isian per direktorat"></div>
                <p class="label mt-2">Klik batang untuk memfilter direktorat.</p>
            </section>

            <section class="kartu col-span-12 xl:col-span-6" aria-labelledby="h-heat">
                <h2 id="h-heat" class="judul-panel mb-3">Sektor × Bagian Profil</h2>
                <div class="overflow-x-auto"><div x-ref="heatmap" class="min-w-[760px]" :style="{ height: tinggiHeatmap }" role="img" aria-label="Persentase keterisian per direktorat dan bagian profil"></div></div>
            </section>

            <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-kosong">
                <h2 id="h-kosong" class="judul-panel mb-3">Proyek dengan Field Wajib Kosong <span class="label font-normal">(50 terendah)</span></h2>
                <div class="max-h-[640px] overflow-auto">
                    <table class="w-full text-left text-isi">
                        <thead class="sticky top-0 border-b border-slate-200 bg-white text-label text-slate-600">
                            <tr><th class="py-2 pr-3">PSN</th><th class="py-2 pr-3 text-right">Kelengkapan</th><th class="py-2">Belum diisi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="p in d?.field_kosong ?? []" :key="p.id">
                                <tr class="align-top">
                                    <td class="max-w-[18rem] py-2 pr-3"><a :href="tautanDetail(p.id)" class="text-aksen-700 hover:underline" x-text="p.nama"></a></td>
                                    <td class="angka py-2 pr-3 text-right" x-text="angka(p.kelengkapan_persen, 0) + '%'"></td>
                                    <td class="py-2 text-label text-slate-700" x-text="p.kosong.join('; ')"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-log">
                <h2 id="h-log" class="judul-panel mb-3">Log Aktivitas</h2>
                <ul class="divide-y divide-slate-100">
                    <template x-for="(a, i) in d?.aktivitas ?? []" :key="i">
                        <li class="py-2">
                            <p x-text="teksAktivitas(a)"></p>
                            <p class="label" x-text="(a.psn ? a.psn + ' · ' : '') + waktu(a.waktu)"></p>
                        </li>
                    </template>
                </ul>
            </section>
        </div>
    </div>
</x-app-layout>
