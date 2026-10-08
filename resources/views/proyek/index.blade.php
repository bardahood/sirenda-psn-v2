<x-app-layout judul="Portofolio PSN">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>

    <div x-data="halamanPortofolio" class="mx-auto max-w-[1376px]">
        <nav class="label mb-4" aria-label="Breadcrumb">
            <a :href="$store.filter.tautan('/dashboard')" href="/dashboard" class="text-aksen-700 hover:underline">Ringkasan Eksekutif</a>
            <span aria-hidden="true">/</span> <span aria-current="page">Portofolio PSN</span>
        </nav>

        <section class="kartu" aria-labelledby="judul-portofolio">
            {{-- Filter lanjutan khusus tabel --}}
            <div class="mb-4 flex flex-wrap items-end gap-3">
                <h2 id="judul-portofolio" class="judul-panel mr-auto">
                    Daftar PSN
                    <span class="label ml-2 font-normal" x-show="meta?.cutoff" x-text="angka(meta?.total, 0) + ' PSN · cut-off ' + meta?.cutoff?.tanggal"></span>
                </h2>
                <form @submit.prevent="cari()" class="flex items-center gap-2" role="search">
                    <label for="cari-psn" class="sr-only">Cari nama atau kode PSN</label>
                    <input id="cari-psn" type="search" x-model="o.q" placeholder="Cari nama atau kode PSN…" maxlength="100"
                           class="w-64 rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                    <button class="tombol-garis py-1.5">Cari</button>
                </form>
                <label class="flex items-center gap-2 text-isi">
                    <span class="label">Tahap</span>
                    <select class="rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" :value="o.tahap" @change="ubah('tahap', $event.target.value)">
                        <option value="">Semua</option>
                        <option value="PERENCANAAN">Perencanaan</option>
                        <option value="TRANSAKSI">Transaksi</option>
                        <option value="KONSTRUKSI">Konstruksi</option>
                        <option value="OPERASI">Operasi/Selesai</option>
                        <option value="TANPA">Tanpa status</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 text-isi">
                    <input type="checkbox" class="rounded border-slate-300 text-aksen focus:ring-aksen" :checked="o.kritis" @change="ubah('kritis', $event.target.checked)">
                    Hanya risiko kritis (K4)
                </label>
                <label class="flex items-center gap-2 text-isi">
                    <input type="checkbox" class="rounded border-slate-300 text-aksen focus:ring-aksen" :checked="o.nonaktif" @change="ubah('nonaktif', $event.target.checked)">
                    Sertakan PSN keluar
                </label>
                <a :href="tautanUnduh('xlsx')" class="tombol-garis py-1.5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>
                    Excel
                </a>
                <a :href="tautanUnduh('csv')" class="tombol-garis py-1.5">CSV</a>
            </div>

            <div x-show="galat" x-cloak role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800" x-text="galat"></div>
            <p x-show="!memuat && meta && !meta.cutoff" x-cloak class="py-8 text-center text-slate-600">Belum ada cut-off yang diterbitkan.</p>

            <div class="overflow-x-auto" :aria-busy="memuat" :class="memuat && 'opacity-60'">
                <table class="w-full min-w-[1180px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr>
                            @foreach ([['kode', 'Kode', ''], ['nama', 'Nama PSN', ''], ['klaster', 'Klaster', ''], [null, 'Lokasi', ''], ['investasi', 'Investasi (Rp T)', 'text-right'], ['progres', 'Realisasi', 'text-right'], ['deviasi', 'Deviasi', 'text-right'], ['status', 'Status', ''], ['risiko', 'Risiko', ''], ['kelengkapan', 'Kelengkapan', 'text-right']] as [$kunci, $label, $rata])
                                <th scope="col" class="py-2 pr-3 {{ $rata }}" @if ($kunci) :aria-sort="ariaSort('{{ $kunci }}')" @endif>
                                    @if ($kunci)
                                        <button type="button" class="inline-flex items-center gap-1 hover:text-slate-900" @click="urutkan('{{ $kunci }}')">
                                            {{ $label }}
                                            <span aria-hidden="true" x-text="o.urut === '{{ $kunci }}' ? (o.arah === 'asc' ? '▲' : '▼') : '↕'" class="text-[10px]"></span>
                                        </button>
                                    @else
                                        {{ $label }}
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="r in baris" :key="r.id">
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap py-2 pr-3 text-label text-slate-600" x-text="r.kode ?? '–'"></td>
                                <td class="max-w-[20rem] py-2 pr-3">
                                    <a :href="tautanDetail(r.id)" class="font-medium text-aksen-700 hover:underline" x-text="r.nama"></a>
                                    <span x-show="r.kategori === 'PKPN'" class="ml-1 rounded bg-primer-50 px-1.5 text-label text-primer">PKPN</span>
                                </td>
                                <td class="max-w-[12rem] truncate py-2 pr-3" :title="r.klaster" x-text="r.klaster ?? '–'"></td>
                                <td class="max-w-[10rem] truncate py-2 pr-3" :title="r.provinsi.join(', ')" x-text="r.provinsi.length ? (r.provinsi[0] + (r.provinsi.length > 1 ? ' +' + (r.provinsi.length - 1) : '')) : '–'"></td>
                                <td class="angka py-2 pr-3 text-right">
                                    <span x-text="angka(r.investasi_triliun, 2)"></span>
                                    <span x-show="r.investasi_anomali" class="text-amber-800" title="Nilai investasi ditandai anomali (salah satuan) dan dikecualikan dari total">⚠</span>
                                </td>
                                <td class="angka py-2 pr-3 text-right" x-text="r.realisasi_persen === null ? '–' : angka(r.realisasi_persen) + '%'"></td>
                                <td class="angka py-2 pr-3 text-right" x-text="r.deviasi_pp === null ? '–' : angka(r.deviasi_pp) + ' pp'"></td>
                                <td class="py-2 pr-3"><span :class="'badge-' + r.status_progres"><span :class="'titik-' + r.status_progres"></span><span x-text="labelStatus(r.status_progres)"></span></span></td>
                                <td class="whitespace-nowrap py-2 pr-3" x-text="r.risiko_level ?? '–'"></td>
                                <td class="angka py-2 pr-2 text-right" x-text="r.kelengkapan_persen === null ? '–' : angka(r.kelengkapan_persen, 0) + '%'"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p x-show="!memuat && meta?.cutoff && !baris.length" class="py-8 text-center text-slate-600">Tidak ada PSN yang sesuai filter.</p>
            </div>

            <nav class="mt-4 flex flex-wrap items-center justify-between gap-2" aria-label="Paginasi" x-show="meta?.halaman_terakhir > 1">
                <span class="label" x-text="'Halaman ' + meta?.halaman + ' dari ' + meta?.halaman_terakhir"></span>
                <div class="flex gap-2">
                    <button type="button" class="tombol-garis py-1.5" @click="ke(1)" :disabled="o.page <= 1">Pertama</button>
                    <button type="button" class="tombol-garis py-1.5" @click="ke(o.page - 1)" :disabled="o.page <= 1">Sebelumnya</button>
                    <button type="button" class="tombol-garis py-1.5" @click="ke(o.page + 1)" :disabled="o.page >= meta?.halaman_terakhir">Berikutnya</button>
                    <button type="button" class="tombol-garis py-1.5" @click="ke(meta.halaman_terakhir)" :disabled="o.page >= meta?.halaman_terakhir">Terakhir</button>
                </div>
            </nav>
        </section>
    </div>
</x-app-layout>
