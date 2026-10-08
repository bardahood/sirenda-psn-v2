{{-- Filter global: semua nilai di query string (Alpine.store('filter')), opsi dari tabel referensi. --}}
@php($chevron = '<svg class="h-4 w-4 shrink-0 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>')
<div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter global">
    {{-- Periode data (cut-off) --}}
    <label class="kotak-filter cursor-pointer">
        <svg class="h-5 w-5 shrink-0 text-primer" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
        <span>
            <span class="kotak-filter-label">Periode Data</span>
            <select class="kotak-filter-nilai -ml-0.5 cursor-pointer appearance-none border-0 bg-none p-0 pr-1 focus:ring-0"
                    :value="$store.filter.nilai.periode" @change="$store.filter.set('periode', $event.target.value)" aria-label="Periode cut-off">
                <option value="">Cut-off terbaru</option>
                <template x-for="o in $store.filter.opsi.periode" :key="o.nilai">
                    <option :value="o.nilai" x-text="o.label" :selected="o.nilai === $store.filter.nilai.periode"></option>
                </template>
            </select>
        </span>
        {!! $chevron !!}
    </label>

    {{-- Provinsi --}}
    <div class="relative" x-data="multiPilih('prov', 'provinsi', 'Provinsi')" @keydown.escape="buka = false" @click.outside="buka = false">
        <button type="button" class="kotak-filter" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="listbox" :class="terpilih.length && '!border-aksen'">
            <span><span class="kotak-filter-label">Provinsi</span><span class="kotak-filter-nilai" x-text="ringkas === 'Semua' ? 'Semua Provinsi' : ringkas"></span></span>
            {!! $chevron !!}
        </button>
        <div x-show="buka" x-cloak x-transition.opacity class="absolute right-0 z-40 mt-1 w-72 rounded-kartu border border-slate-200 bg-white p-2 shadow-lg">
            <input type="search" x-model="cari" placeholder="Cari provinsi…" class="mb-2 w-full rounded-md border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" aria-label="Cari provinsi">
            @include('components.partials.daftar-pilih')
        </div>
    </div>

    {{-- Sektor: klaster & direktorat dalam satu kotak --}}
    <div class="relative" x-data="{ buka: false, tab: 'klaster' }" @keydown.escape="buka = false" @click.outside="buka = false">
        <button type="button" class="kotak-filter" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="dialog"
                :class="($store.filter.nilai.klaster.length || $store.filter.nilai.dit.length) && '!border-aksen'">
            <span><span class="kotak-filter-label">Sektor</span>
                <span class="kotak-filter-nilai" x-text="(() => { const k = $store.filter.nilai.klaster.length, d = $store.filter.nilai.dit.length; return !k && !d ? 'Semua Sektor' : [k && k + ' klaster', d && d + ' direktorat'].filter(Boolean).join(', '); })()"></span></span>
            {!! $chevron !!}
        </button>
        <div x-show="buka" x-cloak x-transition.opacity class="absolute right-0 z-40 mt-1 w-80 rounded-kartu border border-slate-200 bg-white p-2 shadow-lg">
            <div class="mb-2 inline-flex rounded-lg border border-slate-200 p-0.5" role="tablist">
                <button type="button" role="tab" :aria-selected="tab === 'klaster'" @click="tab = 'klaster'" :class="tab === 'klaster' ? 'bg-primer text-white' : 'text-slate-600'" class="rounded-md px-3 py-1 text-label">Klaster</button>
                <button type="button" role="tab" :aria-selected="tab === 'dit'" @click="tab = 'dit'" :class="tab === 'dit' ? 'bg-primer text-white' : 'text-slate-600'" class="rounded-md px-3 py-1 text-label">Direktorat</button>
            </div>
            <div x-show="tab === 'klaster'" x-data="multiPilih('klaster', 'klaster', 'Klaster')">@include('components.partials.daftar-pilih')</div>
            <div x-show="tab === 'dit'" x-data="multiPilih('dit', 'direktorat', 'Direktorat')">
                <input type="search" x-model="cari" placeholder="Cari direktorat…" class="mb-2 w-full rounded-md border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" aria-label="Cari direktorat">
                @include('components.partials.daftar-pilih')
            </div>
        </div>
    </div>

    {{-- Status proyek (status progres) --}}
    <div class="relative" x-data="{ buka: false }" @keydown.escape="buka = false" @click.outside="buka = false">
        <button type="button" class="kotak-filter" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="listbox" :class="$store.filter.nilai.status.length && '!border-aksen'">
            <span><span class="kotak-filter-label">Status Proyek</span>
                <span class="kotak-filter-nilai" x-text="$store.filter.nilai.status.length ? $store.filter.nilai.status.map(s => $store.filter.label('status', s)).join(', ') : 'Semua Status'"></span></span>
            {!! $chevron !!}
        </button>
        <div x-show="buka" x-cloak x-transition.opacity class="absolute right-0 z-40 mt-1 w-56 rounded-kartu border border-slate-200 bg-white p-2 shadow-lg">
            <ul role="listbox" aria-multiselectable="true">
                <template x-for="o in $store.filter.opsi.status" :key="o.nilai">
                    <li><label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 hover:bg-slate-50">
                        <input type="checkbox" class="rounded border-slate-300 text-aksen focus:ring-aksen" :checked="$store.filter.nilai.status.includes(o.nilai)" @change="$store.filter.toggle('status', o.nilai)">
                        <span :class="'badge-' + o.nilai.toUpperCase()"><span :class="'titik-' + o.nilai.toUpperCase()"></span><span x-text="o.label"></span></span>
                    </label></li>
                </template>
            </ul>
        </div>
    </div>

    {{-- Lainnya: sumber dana & kategori PSN/PKPN --}}
    <div class="relative" x-data="multiPilih('dana', 'dana', 'Sumber dana')" @keydown.escape="buka = false" @click.outside="buka = false">
        <button type="button" class="kotak-filter" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="dialog" :class="(terpilih.length || $store.filter.nilai.kat) && '!border-aksen'">
            <span><span class="kotak-filter-label">Dana & Kategori</span>
                <span class="kotak-filter-nilai" x-text="[terpilih.length ? ringkas : '', $store.filter.nilai.kat ? $store.filter.label('kategori', $store.filter.nilai.kat) : ''].filter(Boolean).join(' · ') || 'Semua'"></span></span>
            {!! $chevron !!}
        </button>
        <div x-show="buka" x-cloak x-transition.opacity class="absolute right-0 z-40 mt-1 w-64 rounded-kartu border border-slate-200 bg-white p-3 shadow-lg">
            <p class="label mb-1">Kategori</p>
            <div class="mb-3 inline-flex rounded-lg border border-slate-300 p-0.5" role="radiogroup" aria-label="Kategori">
                <template x-for="o in [{nilai: '', label: 'Semua'}, ...$store.filter.opsi.kategori]" :key="o.nilai">
                    <button type="button" role="radio" :aria-checked="$store.filter.nilai.kat === o.nilai" @click="$store.filter.set('kat', o.nilai)"
                            :class="$store.filter.nilai.kat === o.nilai ? 'bg-primer text-white' : 'text-slate-600 hover:bg-slate-50'" class="rounded-md px-2.5 py-1 text-label" x-text="o.label"></button>
                </template>
            </div>
            <p class="label mb-1">Sumber dana</p>
            @include('components.partials.daftar-pilih')
        </div>
    </div>

    <button type="button" x-show="$store.filter.aktif()" x-cloak @click="$store.filter.reset()" class="rounded-lg px-2 py-1 text-label font-semibold text-aksen-700 hover:bg-aksen-50">Reset filter</button>
</div>
