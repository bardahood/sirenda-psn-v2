@php
    $labelSumber = config('psn_dashboard.label_sumber_psn');
    $kolom = $format->kolom();
    $editor = $format->editor();
    $angkaKunci = fn ($k) => $format::angka($k);
    $lebar = fn ($k) => $format::lebar($k);
@endphp
<x-app-layout judul="Kertas Kerja Pengendalian PSN · RKP {{ $tahun }}" subjudul="Format ringkas: 1 baris = 1 PSN, terisi dari profil SIRENDA dan isian evaluasi setiap PSN">
    <x-slot:infoJudul>Data profil per {{ $cutoff?->tanggal_cutoff?->translatedFormat('j F Y') ?? '–' }}</x-slot:infoJudul>
    <x-slot:aksi>
        @can('evaluasi.input')<a href="{{ route('evaluasi.unggah') }}" class="tombol-garis py-1.5">Unggah kertas kerja</a>@endcan
        <a href="{{ route('evaluasi.kertas-kerja-xlsx', $filter) }}" class="tombol-primer py-1.5">Unduh kertas kerja (Excel)</a>
    </x-slot:aksi>

    <div class="mx-auto max-w-[1800px] space-y-4">
        @include('evaluasi._tab')
        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
        @if (session('galat_unggah'))
            <div class="rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert"><p class="font-semibold">Sebagian isian tidak tersimpan:</p>
                <ul class="mt-1 list-disc pl-5">@foreach (session('galat_unggah') as $g)<li>{{ $g }}</li>@endforeach</ul></div>
        @endif

        <section class="kartu !p-4" aria-labelledby="h-kk">
            <form method="GET" class="mb-3 flex flex-wrap items-end gap-3">
                <h2 id="h-kk" class="judul-panel mr-auto">Kertas Kerja <span class="label font-normal">{{ number_format($baris->total(), 0, ',', '.') }} PSN · 92 kolom</span></h2>
                <label class="text-isi"><span class="label block">Cari</span>
                    <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100" class="mt-1 w-48 rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" placeholder="Nama atau kode PSN…"></label>
                @foreach ([['status', 'Status akhir', $cfg['status']], ['kelengkapan', 'Kelengkapan', ['LENGKAP' => 'Lengkap', 'KURANG' => 'Belum lengkap']],
                    ['sumber', 'Sumber', $labelSumber], ['klaster', 'Klaster', $klaster->mapWithKeys(fn ($k) => [$k => $k])->all()]] as [$nama, $label, $pilihan])
                    <label class="text-isi"><span class="label block">{{ $label }}</span>
                        <select name="{{ $nama }}" class="mt-1 max-w-[13rem] rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                            <option value="">Semua</option>
                            @foreach ($pilihan as $nilai => $teks)<option value="{{ $nilai }}" @selected((string) ($filter[$nama] ?? '') === (string) $nilai)>{{ $teks }}</option>@endforeach
                        </select></label>
                @endforeach
                <button class="tombol-garis py-1.5">Terapkan</button>
                <a href="{{ route('evaluasi.kertas-kerja') }}" class="tombol-garis py-1.5">Reset</a>
            </form>

            <p class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-slate-600" aria-label="Legenda warna sel">
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-sky-200 bg-sky-50"></span>Data profil SIRENDA (otomatis)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-slate-300 bg-white"></span>Isian evaluasi PSN</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-amber-300 bg-amber-100"></span>Isian wajib masih kosong</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-slate-200 bg-slate-100"></span>Kolom otomatis</span>
                <span><span class="font-semibold text-orange-800">Judul kolom berwarna</span> = wajib (bersyarat)</span>
                @can('evaluasi.input')<span><b>Klik sel</b> untuk mengisi langsung; sel B3, C2, dan lampiran membuka formulir PSN.</span>@else<span>Klik nama PSN untuk melihat rincian.</span>@endcan
            </p>

            <div x-data="kertasKerja(@js(collect($editor)->map(fn ($e) => ['tipe' => $e['tipe'], 'opsi' => $e['opsi']])), @js(route('evaluasi.sel', ['psn' => '__ID__'])))"
                 @click="klik($event)" @keydown.enter="klik($event)" class="relative">
            <div role="status" aria-live="polite" x-show="pesan" x-cloak x-text="pesan" class="mb-2 rounded-lg px-3 py-1.5 text-[12.5px]" :class="galat ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800'"></div>
            <div x-ref="gulir" class="max-h-[70vh] overflow-auto rounded-lg border border-slate-200">
                <table class="border-separate border-spacing-0 text-left text-[12px]">
                    <thead class="sticky top-0 z-20">
                        <tr>
                            @foreach ($format->grup() as $gi => [$nama, $n])
                                <th colspan="{{ $n }}" @class(['border-b border-r border-slate-200 bg-[#e8eef7] px-2 py-1.5 text-[11.5px] font-bold text-slate-800', 'sticky left-0 z-30' => $gi === 0, 'sticky left-[13rem] z-30 shadow-[2px_0_0_#e2e8f0]' => $gi === 1])>{{ trim($nama) }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($kolom as $ki => $k)
                                <th @class(['border-b border-r border-slate-200 bg-[#f3f6fb] px-2 py-1.5 align-bottom text-[11px] font-semibold', $lebar($k['kunci']),
                                    'text-orange-800' => $k['wajib'], 'text-slate-600' => ! $k['wajib'], 'sticky z-30' => $ki < 3,
                                    'left-0' => $ki === 0, 'left-[4.5rem]' => $ki === 1, 'left-[13rem]' => $ki === 2])>{{ $k['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($baris as $b)
                            @include('evaluasi._baris-kk', ['b' => $b, 'kolom' => $kolom, 'editor' => $editor])
                        @empty
                            <tr><td colspan="{{ count($kolom) }}" class="px-3 py-6 text-center text-slate-500">Tidak ada PSN yang sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Editor sel mengambang --}}
            <div x-show="buka" x-cloak x-ref="editor" @click.stop @keydown.escape.prevent="tutup()" @click.outside="tutup()"
                 class="fixed z-50 w-[22rem] max-w-[92vw] rounded-kartu border border-slate-300 bg-white p-3 shadow-xl" :style="`top:${posisi.top}px;left:${posisi.left}px`"
                 role="dialog" aria-label="Isi sel kertas kerja">
                <p class="mb-2 text-[12px] font-semibold text-slate-700" x-text="judul"></p>
                <template x-if="tipe === 'teks' || tipe === 'angka' || tipe === 'tanggal'">
                    <input x-ref="masukan" :type="tipe === 'angka' ? 'number' : (tipe === 'tanggal' ? 'date' : 'text')" step="any" x-model="nilai" @keydown.enter.prevent="simpan()"
                           class="w-full rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                </template>
                <template x-if="tipe === 'panjang'">
                    <textarea x-ref="masukan" rows="4" x-model="nilai" @keydown.ctrl.enter.prevent="simpan()" @keydown.enter.stop
                              class="w-full rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen"></textarea>
                </template>
                <template x-if="tipe === 'pilihan'">
                    <select x-ref="masukan" x-model="nilai" @keydown.enter.prevent="simpan()" class="w-full rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                        <option value="">(kosongkan)</option>
                        <template x-for="[k, l] in Object.entries(opsi || {})" :key="k"><option :value="k" x-text="l" :selected="k === nilai"></option></template>
                    </select>
                </template>
                <template x-if="tipe === 'multi'">
                    <div class="max-h-60 space-y-1 overflow-auto text-isi">
                        <template x-for="([k, l], i) in Object.entries(opsi || {})" :key="k">
                            <label class="flex items-start gap-2"><input type="checkbox" :value="k" x-model="multi" class="mt-0.5 rounded border-slate-300 text-aksen"><span x-text="`${i + 1}. ${l}`"></span></label>
                        </template>
                        <template x-if="kunci === 'c1'">
                            <label class="block pt-1"><span class="label">11. Lainnya (tulis "Tidak ada isu" bila tidak ada)</span>
                                <input type="text" x-model="lain" class="mt-1 w-full rounded-lg border-slate-300 py-1.5 text-isi"></label>
                        </template>
                    </div>
                </template>
                <p x-show="galatEditor" x-text="galatEditor" class="mt-2 text-[12px] font-semibold text-red-700"></p>
                <div class="mt-3 flex items-center justify-end gap-2">
                    <span class="mr-auto text-[11px] text-slate-500" x-text="tipe === 'panjang' ? 'Ctrl+Enter simpan · Esc batal' : (tipe === 'multi' ? 'Klik Simpan · Esc batal' : 'Enter simpan · Esc batal')"></span>
                    <button type="button" class="tombol-garis py-1" @click="tutup()">Batal</button>
                    <button type="button" class="tombol-primer py-1" :disabled="menyimpan" @click="simpan()" x-text="menyimpan ? 'Menyimpan…' : 'Simpan'"></button>
                </div>
            </div>
            </div>
            <div class="mt-3">{{ $baris->links() }}</div>
        </section>
    </div>
</x-app-layout>
