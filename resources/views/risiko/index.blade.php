<x-app-layout judul="Risiko, Isu & Regulasi">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>

    <div x-data="halamanRisiko" class="mx-auto max-w-[1376px]">
        <div x-show="galat" x-cloak role="alert" class="mb-6 rounded-kartu border border-red-200 bg-red-50 p-4 text-red-800" x-text="galat"></div>
        <div x-show="!memuat && meta && !meta.cutoff" x-cloak class="kartu text-center"><p class="judul-panel">Belum ada cut-off yang diterbitkan</p></div>

        <div x-show="ring" x-cloak class="grid grid-cols-12 gap-6" :class="memuat && 'opacity-60'" :aria-busy="memuat">
            <section class="col-span-12 flex flex-wrap items-center gap-x-8 gap-y-3 rounded-kartu border border-primer-100 bg-primer-50 px-5 py-3" aria-label="Periode dan kategori">
                <div><span class="label">Cut-off</span> <span class="ml-1 font-semibold text-primer" x-text="meta?.cutoff?.tanggal"></span></div>
                <div><span class="label">Total risiko</span> <span class="angka ml-1 font-semibold text-primer" x-text="angka(ring?.total_risiko)"></span></div>
                <label class="flex items-center gap-2">
                    <span class="label">Kategori risiko</span>
                    <select x-model="o.kategori" @change="ubahKategori()" class="w-72 rounded-lg border-slate-300 py-1 text-isi">
                        <option value="">Semua kategori</option>
                        <template x-for="k in ring?.kategori ?? []" :key="k.nilai"><option :value="k.nilai" x-text="k.label"></option></template>
                    </select>
                </label>
                <p class="label ml-auto">Heatmap & register membaca snapshot cut-off; isu & regulasi membaca data terkini.</p>
            </section>

            {{-- Heatmap 5x5 --}}
            @foreach (['harapan' => 'Risiko Harapan (inheren)', 'aktual' => 'Risiko Aktual (residual)'] as $jenis => $judul)
                <section class="kartu col-span-12 lg:col-span-6" aria-labelledby="h-{{ $jenis }}">
                    <div class="mb-3 flex items-start gap-2">
                        <h2 id="h-{{ $jenis }}" class="judul-panel mr-auto">{{ $judul }}</h2>
                        <span class="label" x-text="angka(ring?.{{ $jenis }}.berskala) + ' berskala · ' + angka(ring?.{{ $jenis }}.tanpa_skala) + ' tanpa skala'"></span>
                    </div>
                    <div x-ref="hm_{{ $jenis }}" class="h-[320px]" role="img" aria-label="Matriks 5×5 kemungkinan × dampak, {{ strtolower($judul) }}"></div>
                    <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Jumlah per level, klik untuk memfilter register">
                        <template x-for="l in ring?.{{ $jenis }}.per_level ?? []" :key="l.level">
                            <button type="button" @click="pilihLevel('{{ $jenis }}', l.level)" :class="[kelasLevel(l.level), o.jenis === '{{ $jenis }}' && o.level === l.level ? 'ring-2' : '']" :aria-pressed="o.jenis === '{{ $jenis }}' && o.level === l.level">
                                <span x-text="l.level + ': ' + angka(l.jumlah)"></span>
                            </button>
                        </template>
                    </div>
                    <p class="label mt-2">Klik sel untuk memfilter register. Risiko lama yang hanya berlabel level (tanpa skala 1–5) tidak ditempatkan di matriks, tetapi dihitung pada level dan tampil di register.</p>
                </section>
            @endforeach

            {{-- Register --}}
            <section class="kartu col-span-12" aria-labelledby="h-register">
                <div class="mb-3 flex flex-wrap items-center gap-3">
                    <h2 id="h-register" class="judul-panel mr-auto">Register Risiko <span class="label font-normal" x-text="'(' + angka(metaReg?.total) + ')'"></span></h2>
                    <template x-if="keteranganSorotan">
                        <span class="inline-flex items-center gap-2 rounded-lg bg-aksen-50 px-2 py-1 text-label text-aksen-700">
                            <span x-text="keteranganSorotan"></span>
                            <button type="button" class="font-semibold underline" @click="hapusSorotan()">Hapus</button>
                        </span>
                    </template>
                    <form @submit.prevent="cari()" class="flex gap-2" role="search">
                        <label class="sr-only" for="cari-risiko">Cari risiko atau PSN</label>
                        <input id="cari-risiko" type="search" x-model="o.q" placeholder="Cari uraian risiko / nama PSN" class="w-64 rounded-lg border-slate-300 py-1 text-isi">
                        <button type="submit" class="rounded-lg bg-primer px-3 py-1 text-isi text-white">Cari</button>
                    </form>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-isi">
                        <thead class="border-b border-slate-200 text-label text-slate-600">
                            <tr><th class="py-2 pr-3">Uraian risiko</th><th class="py-2 pr-3">PSN</th><th class="py-2 pr-3">Kategori</th><th class="py-2 pr-3">Harapan</th><th class="py-2 pr-3">Aktual</th><th class="py-2 pr-3">Penanggung jawab</th><th class="py-2">Mitigasi</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="r in register" :key="r.risiko_id">
                                <tr class="align-top">
                                    <td class="max-w-[22rem] py-2 pr-3" x-text="r.uraian ?? '–'"></td>
                                    <td class="max-w-[14rem] py-2 pr-3"><a :href="tautanPsn(r.psn_id)" class="text-aksen-700 hover:underline" x-text="r.psn"></a></td>
                                    <td class="py-2 pr-3" x-text="r.kategori ?? '–'"></td>
                                    <td class="whitespace-nowrap py-2 pr-3"><span :class="kelasLevel(r.harapan.level)" x-text="teksLevel(r.harapan)"></span></td>
                                    <td class="whitespace-nowrap py-2 pr-3"><span :class="kelasLevel(r.aktual.level)" x-text="teksLevel(r.aktual)"></span></td>
                                    <td class="max-w-[12rem] py-2 pr-3" x-text="r.pic ?? '–'"></td>
                                    <td class="max-w-[20rem] py-2 text-label text-slate-700" x-text="r.mitigasi ?? '–'"></td>
                                </tr>
                            </template>
                            <tr x-show="!register.length"><td colspan="7" class="py-6 text-center text-slate-500">Tidak ada risiko yang sesuai.</td></tr>
                        </tbody>
                    </table>
                </div>
                <nav class="mt-3 flex items-center justify-end gap-2" aria-label="Halaman register">
                    <span class="label" x-text="'Halaman ' + (metaReg?.halaman ?? 1) + ' dari ' + (metaReg?.halaman_terakhir ?? 1)"></span>
                    <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 disabled:opacity-40" :disabled="(metaReg?.halaman ?? 1) <= 1" @click="keReg(o.page - 1)">Sebelumnya</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 disabled:opacity-40" :disabled="(metaReg?.halaman ?? 1) >= (metaReg?.halaman_terakhir ?? 1)" @click="keReg(o.page + 1)">Berikutnya</button>
                </nav>
            </section>

            {{-- Isu & debottlenecking --}}
            <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-isu">
                <div class="mb-3 flex flex-wrap items-center gap-3">
                    <h2 id="h-isu" class="judul-panel mr-auto">Isu & Debottlenecking</h2>
                    <span class="label" x-text="angka(ring?.isu.terbuka) + ' terbuka · ' + angka(ring?.isu.lewat_tenggat) + ' lewat tenggat · ' + angka(ring?.isu.tanpa_pic) + ' tanpa PIC'"></span>
                    <label class="flex items-center gap-2 text-isi"><input type="checkbox" class="rounded border-slate-300" :checked="o.lewatTenggat" @change="toggleTenggat()"> Hanya lewat tenggat</label>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-isi">
                        <thead class="border-b border-slate-200 text-label text-slate-600">
                            <tr><th class="py-2 pr-3">Isu</th><th class="py-2 pr-3">PSN</th><th class="py-2 pr-3">PIC</th><th class="py-2 pr-3">Tenggat</th><th class="py-2">Status</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="i in isu" :key="i.id">
                                <tr class="align-top" :class="i.lewat_tenggat && 'bg-red-50/60'">
                                    <td class="max-w-[22rem] py-2 pr-3"><p x-text="i.uraian"></p><p class="label" x-show="i.kebutuhan_dukungan" x-text="'Dukungan: ' + i.kebutuhan_dukungan"></p></td>
                                    <td class="max-w-[14rem] py-2 pr-3"><a :href="tautanPsn(i.psn_id)" class="text-aksen-700 hover:underline" x-text="i.psn"></a></td>
                                    <td class="py-2 pr-3" x-text="i.pic ?? 'Belum ditetapkan'"></td>
                                    <td class="whitespace-nowrap py-2 pr-3">
                                        <span x-text="tanggal(i.tenggat)"></span>
                                        <span x-show="i.lewat_tenggat" class="badge ml-1 bg-red-50 text-red-800 ring-red-600/40">Lewat tenggat</span>
                                    </td>
                                    <td class="py-2" x-text="statusIsu(i.status)"></td>
                                </tr>
                            </template>
                            <tr x-show="!isu.length"><td colspan="5" class="py-6 text-center text-slate-500">Tidak ada isu terbuka.</td></tr>
                        </tbody>
                    </table>
                </div>
                <nav class="mt-3 flex items-center justify-end gap-2" aria-label="Halaman isu">
                    <span class="label" x-text="'Halaman ' + (metaIsu?.halaman ?? 1) + ' dari ' + (metaIsu?.halaman_terakhir ?? 1)"></span>
                    <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 disabled:opacity-40" :disabled="(metaIsu?.halaman ?? 1) <= 1" @click="keIsu(o.isuPage - 1)">Sebelumnya</button>
                    <button type="button" class="rounded-lg border border-slate-300 px-2 py-1 disabled:opacity-40" :disabled="(metaIsu?.halaman ?? 1) >= (metaIsu?.halaman_terakhir ?? 1)" @click="keIsu(o.isuPage + 1)">Berikutnya</button>
                </nav>
            </section>

            {{-- Pipeline regulasi --}}
            <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-reg">
                <h2 id="h-reg" class="judul-panel mb-3">Pipeline Regulasi</h2>
                <ol class="space-y-3">
                    <template x-for="(t, n) in ring?.regulasi ?? []" :key="t.tahap">
                        <li>
                            <div class="flex justify-between text-isi"><span x-text="(n + 1) + '. ' + t.label"></span><span class="angka font-semibold" x-text="angka(t.jumlah)"></span></div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-aksen" :style="{ width: (t.jumlah / maksRegulasi() * 100) + '%' }"></div></div>
                        </li>
                    </template>
                </ol>
                <p class="label mt-3">Jumlah regulasi pendukung per tahap untuk PSN yang lolos filter.</p>
            </section>
        </div>
    </div>
</x-app-layout>
