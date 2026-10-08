{{-- Filter global: semua nilai di query string, opsi dari tabel referensi. --}}
<div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter global">
    <label class="sr-only" for="f-periode">Periode cut-off</label>
    <select id="f-periode" class="rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-isi focus:border-aksen focus:ring-aksen"
            :value="$store.filter.nilai.periode" @change="$store.filter.set('periode', $event.target.value)">
        <option value="">Cut-off terbaru</option>
        <template x-for="o in $store.filter.opsi.periode" :key="o.nilai">
            <option :value="o.nilai" x-text="o.label" :selected="o.nilai === $store.filter.nilai.periode"></option>
        </template>
    </select>

    @foreach ([['prov', 'provinsi', 'Provinsi'], ['klaster', 'klaster', 'Klaster'], ['dit', 'direktorat', 'Direktorat'], ['dana', 'dana', 'Sumber dana']] as [$kunci, $opsi, $judul])
        <div class="relative" x-data="multiPilih('{{ $kunci }}', '{{ $opsi }}', '{{ $judul }}')" @keydown.escape="buka = false" @click.outside="buka = false">
            <button type="button" class="tombol-garis py-1.5" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="listbox"
                    :class="terpilih.length && 'border-aksen text-aksen-700'">
                <span class="text-slate-500">{{ $judul }}:</span> <span class="max-w-[9rem] truncate" x-text="ringkas"></span>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div x-show="buka" x-cloak x-transition.opacity class="absolute right-0 z-40 mt-1 w-72 rounded-kartu border border-slate-200 bg-white p-2 shadow-lg">
                @if ($kunci === 'prov' || $kunci === 'dit')
                    <input type="search" x-model="cari" placeholder="Cari {{ strtolower($judul) }}…" class="mb-2 w-full rounded-md border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" aria-label="Cari {{ strtolower($judul) }}">
                @endif
                <ul class="max-h-64 overflow-y-auto" role="listbox" aria-multiselectable="true">
                    <template x-for="o in daftar" :key="o.nilai">
                        <li>
                            <label class="flex cursor-pointer items-start gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50">
                                <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-aksen focus:ring-aksen"
                                       :checked="terpilih.includes(String(o.nilai))" @change="pilih(o.nilai)">
                                <span class="text-isi" x-text="o.label"></span>
                            </label>
                        </li>
                    </template>
                    <li x-show="!daftar.length" class="px-2 py-1.5 text-label text-slate-500">Tidak ada pilihan.</li>
                </ul>
                <button type="button" class="mt-1 w-full rounded-md px-2 py-1 text-left text-label text-aksen-700 hover:bg-slate-50" @click="bersihkan()">Hapus pilihan</button>
            </div>
        </div>
    @endforeach

    {{-- Status: chip multi-pilih --}}
    <div class="flex items-center gap-1" role="group" aria-label="Status progres">
        <template x-for="o in $store.filter.opsi.status" :key="o.nilai">
            <button type="button" @click="$store.filter.toggle('status', o.nilai)"
                    :aria-pressed="$store.filter.nilai.status.includes(o.nilai)"
                    :class="$store.filter.nilai.status.includes(o.nilai) ? 'badge-' + o.nilai.toUpperCase() + ' ring-2' : 'badge bg-white text-slate-600 ring-slate-300'"
                    class="cursor-pointer">
                <span :class="'titik-' + o.nilai.toUpperCase()"></span><span x-text="o.label"></span>
            </button>
        </template>
    </div>

    {{-- Kategori: PSN / PKPN / keduanya --}}
    <div class="inline-flex rounded-lg border border-slate-300 p-0.5" role="radiogroup" aria-label="Kategori">
        <template x-for="o in [{nilai: '', label: 'Semua'}, ...$store.filter.opsi.kategori]" :key="o.nilai">
            <button type="button" role="radio" :aria-checked="$store.filter.nilai.kat === o.nilai" @click="$store.filter.set('kat', o.nilai)"
                    :class="$store.filter.nilai.kat === o.nilai ? 'bg-primer text-white' : 'text-slate-600 hover:bg-slate-50'"
                    class="rounded-md px-2.5 py-1 text-label" x-text="o.label"></button>
        </template>
    </div>

    <button type="button" class="tombol-garis py-1.5" @click="$store.filter.reset()" :disabled="!$store.filter.aktif()" :class="!$store.filter.aktif() && 'opacity-50'">Reset</button>
</div>
