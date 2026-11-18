@php
    $T = \Database\Seeders\PeranSeeder::TINGKAT;
    $labelT = ['lihat' => 'Lihat', 'input' => 'Input', 'verifikasi' => 'Verifikasi', 'kelola' => 'Kelola'];
    $tetap = $pilih?->name === \App\Http\Controllers\Pengaturan\PeranController::PERAN_TETAP;
    // Indeks tingkat terpilih per modul (-1 = tanpa akses) dan indeks tingkat yang tersedia, untuk Alpine.
    $awal = collect($tingkat)->map(fn ($t) => $t === null ? -1 : array_search($t, $T))->all();
    $tersedia = collect($modul)->map(fn ($m) => array_map(fn ($a) => array_search($a, $T), array_keys($m['aksi'])))->all();
@endphp
<x-app-layout judul="Pengaturan">
    <div class="mx-auto max-w-[1376px]">
        @include('pengaturan._tab')
        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12 space-y-6 lg:col-span-4">
                <section class="kartu" aria-labelledby="h-daftar-peran">
                    <h2 id="h-daftar-peran" class="judul-panel mb-3">Daftar Peran</h2>
                    <ul class="divide-y divide-slate-100">
                        @foreach ($peran as $p)
                            <li>
                                <a href="{{ route('pengaturan.peran.index', ['peran' => $p->id]) }}" @if ($pilih?->is($p)) aria-current="true" @endif
                                   @class(['flex items-center gap-2 rounded-lg px-3 py-2', 'bg-aksen-50 font-semibold text-primer' => $pilih?->is($p), 'hover:bg-slate-50' => ! $pilih?->is($p)])>
                                    <span class="mr-auto">{{ $p->name }}</span>
                                    @if (! in_array($p->name, $bawaan, true))<span class="badge bg-slate-100 text-slate-700 ring-slate-400/40">Tambahan</span>@endif
                                    <span class="label" title="Jumlah pengguna">{{ $p->users_count }} pengguna</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <form method="POST" action="{{ route('pengaturan.peran.store') }}" class="kartu space-y-3" aria-labelledby="h-peran-baru">
                    @csrf
                    <h2 id="h-peran-baru" class="judul-panel">Peran Baru</h2>
                    <label class="block"><span class="label">Nama peran *</span><input name="nama" value="{{ old('nama') }}" required maxlength="100" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Contoh: Analis Direktorat"></label>
                    <label class="block"><span class="label">Salin hak akses dari</span>
                        <select name="salin_dari" class="mt-1 w-full rounded-lg border-slate-300"><option value="">Tanpa akses (atur sendiri)</option>
                            @foreach ($peran as $p)<option value="{{ $p->id }}" @selected((int) old('salin_dari') === $p->id)>{{ $p->name }}</option>@endforeach
                        </select></label>
                    <p class="label">Cakupan data peran tambahan tidak dibatasi per unit kerja. Untuk pengguna yang hanya boleh melihat sektor atau proyeknya sendiri, pakai peran Operator K/L atau Direktorat Sektor.</p>
                    <div class="flex justify-end"><button class="tombol-primer py-1.5">Buat peran</button></div>
                </form>
            </div>

            @if ($pilih)
                <form method="POST" action="{{ route('pengaturan.peran.update', $pilih->id) }}" class="kartu col-span-12 lg:col-span-8" aria-labelledby="h-hak"
                      x-data="{ t: @js($awal), ada: @js($tersedia), T: @js($T),
                                ubah(m, i, cek) { if (cek) { this.t[m] = i; return; } const bawah = this.ada[m].filter((k) => k < i); this.t[m] = bawah.length ? Math.max(...bawah) : -1; },
                                semua(penuh) { for (const m in this.t) this.t[m] = penuh ? Math.max(...this.ada[m]) : -1; } }">
                    @csrf @method('PUT')
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <h2 id="h-hak" class="judul-panel mr-auto">Hak Akses: {{ $pilih->name }} <span class="label font-normal">({{ $pilih->users_count }} pengguna)</span></h2>
                        @unless ($tetap)
                            <button type="button" class="tombol-garis py-1" @click="semua(true)">Centang semua</button>
                            <button type="button" class="tombol-garis py-1" @click="semua(false)">Kosongkan</button>
                        @endunless
                    </div>
                    @if ($tetap)
                        <p class="mb-3 rounded-kartu border border-blue-200 bg-blue-50 p-3 text-blue-900">Super Admin selalu memiliki seluruh hak akses dan tidak dapat diubah, agar pengaturan aplikasi tidak terkunci.</p>
                    @else
                        <p class="label mb-3">Centang menu yang boleh dibuka peran ini. Tingkat bersifat bertingkat: mencentang <b>Verifikasi</b> otomatis mencentang <b>Lihat</b> dan <b>Input</b>; menghapus centang <b>Lihat</b> menutup menu itu sepenuhnya.</p>
                    @endif
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-isi">
                            <thead class="border-b border-slate-200 text-label text-slate-600">
                                <tr><th class="py-2 pr-3">Menu / modul</th>@foreach ($T as $a)<th class="w-24 py-2 pr-3 text-center">{{ $labelT[$a] }}</th>@endforeach</tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($modul as $kode => $m)
                                    <tr class="align-top">
                                        <td class="py-2.5 pr-3">
                                            <span class="font-semibold">{{ $m['label'] }}</span>
                                            <span class="block text-label text-slate-500">Menu: {{ implode(', ', $m['menu']) }}</span>
                                            <input type="hidden" name="hak[{{ $kode }}]" :value="t['{{ $kode }}'] >= 0 ? T[t['{{ $kode }}']] : ''" value="{{ $tingkat[$kode] ?? '' }}">
                                        </td>
                                        @foreach ($T as $i => $a)
                                            <td class="py-2.5 pr-3 text-center">
                                                @if (isset($m['aksi'][$a]))
                                                    <label class="inline-flex cursor-pointer flex-col items-center gap-1" title="{{ $m['aksi'][$a] }}">
                                                        <input type="checkbox" class="h-5 w-5 rounded border-slate-300 text-aksen disabled:opacity-60"
                                                               @checked(($awal[$kode] ?? -1) >= $i) :checked="t['{{ $kode }}'] >= {{ $i }}"
                                                               @change="ubah('{{ $kode }}', {{ $i }}, $event.target.checked)" @disabled($tetap)
                                                               aria-label="{{ $m['label'] }}: {{ $labelT[$a] }}">
                                                        <span class="max-w-[7rem] text-[11px] leading-tight text-slate-500">{{ $m['aksi'][$a] }}</span>
                                                    </label>
                                                @else
                                                    <span class="text-slate-300" title="Tidak dipakai di menu ini">–</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="label mt-3">Menu Kamus Indikator selalu terbuka untuk semua pengguna. Pembatasan data per sektor atau proyek untuk Operator K/L dan Direktorat Sektor tetap berlaku sesuai unit kerja pengguna.</p>
                    @unless ($tetap)
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
                            @unless (in_array($pilih->name, $bawaan, true))
                                <button type="submit" form="hapus-peran" class="tombol-garis mr-auto border-red-300 text-red-700 hover:bg-red-50" @disabled($pilih->users_count > 0)
                                        title="{{ $pilih->users_count > 0 ? 'Masih dipakai pengguna' : 'Hapus peran ini' }}">Hapus peran</button>
                            @endunless
                            <button class="tombol-primer">Simpan hak akses</button>
                        </div>
                    @endunless
                </form>
                @unless ($tetap || in_array($pilih->name, $bawaan, true))
                    <form id="hapus-peran" method="POST" action="{{ route('pengaturan.peran.destroy', $pilih->id) }}" onsubmit="return confirm('Hapus peran {{ $pilih->name }}?')">@csrf @method('DELETE')</form>
                @endunless
            @endif
        </div>
    </div>
</x-app-layout>
