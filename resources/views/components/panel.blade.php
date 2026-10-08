@props(['kode', 'judul', 'grafik' => null, 'csv' => true, 'tinggi' => null])
{{-- Panel standar: judul, ikon ⓘ (rumus, sumber, cut-off), unduh PNG/CSV. --}}
<section {{ $attributes->merge(['class' => 'kartu flex flex-col']) }} aria-labelledby="judul-{{ $kode }}">
    <div class="mb-3 flex items-start gap-2">
        <h2 id="judul-{{ $kode }}" class="judul-panel mr-auto">{{ $judul }}</h2>
        <div class="relative" x-data="{ buka: false }" @click.outside="buka = false" @keydown.escape="buka = false">
            <button type="button" class="ikon-tombol" @click="buka = !buka" :aria-expanded="buka" aria-label="Informasi indikator {{ $judul }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
            </button>
            <div x-show="buka" x-cloak x-transition.opacity role="tooltip"
                 class="absolute right-0 z-30 mt-1 w-72 whitespace-pre-line rounded-lg border border-slate-200 bg-white p-3 text-label font-normal text-slate-700 shadow-lg"
                 x-text="info('{{ $kode }}')"></div>
        </div>
        <div class="relative" x-data="{ buka: false }" @click.outside="buka = false" @keydown.escape="buka = false">
            <button type="button" class="ikon-tombol" @click="buka = !buka" :aria-expanded="buka" aria-label="Unduh {{ $judul }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>
            </button>
            <div x-show="buka" x-cloak class="absolute right-0 z-30 mt-1 w-36 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                @if ($grafik)
                    <button type="button" class="block w-full px-3 py-1.5 text-left text-isi hover:bg-slate-50" @click="unduhPng('{{ $grafik }}', '{{ $kode }}'); buka = false">Unduh PNG</button>
                @endif
                @if ($csv)
                    <button type="button" class="block w-full px-3 py-1.5 text-left text-isi hover:bg-slate-50" @click="unduhCsv('{{ $kode }}'); buka = false">Unduh CSV</button>
                @endif
            </div>
        </div>
    </div>
    <div class="relative min-h-0 flex-1">
        {{ $slot }}
    </div>
</section>
