<x-app-layout judul="Peta Sebaran PSN">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>

    <div class="mx-auto grid max-w-[1376px] grid-cols-12 gap-6"
         x-data="petaSebaran({ sumber: '/api/v1/peta', tileUrl: @js(config('psn_dashboard.peta.tile_url')), atribusi: @js(config('psn_dashboard.peta.tile_atribusi')), geojson: @js($geojson), kodeProp: @js(config('psn_dashboard.peta.kode_prop')) })">
        <section class="kartu col-span-12 xl:col-span-9" aria-labelledby="h-peta">
            <div class="mb-3 flex flex-wrap items-baseline gap-2">
                <h2 id="h-peta" class="judul-panel mr-auto">Sebaran PSN per Provinsi</h2>
                <span class="label" x-text="meta?.cutoff ? 'Cut-off ' + meta.cutoff.tanggal : ''"></span>
            </div>
            <div x-ref="peta" class="h-[560px] rounded-lg bg-slate-100" role="img" aria-label="Peta jumlah PSN per provinsi; klik provinsi untuk memfilter"></div>
            <p class="label mt-2">
                @if ($geojson)
                    Warna = jumlah PSN (biru lebih gelap = lebih banyak).
                @else
                    Ukuran lingkaran = jumlah PSN di provinsi tersebut (titik tengah provinsi). Peta choropleth aktif otomatis setelah berkas GeoJSON provinsi tersedia.
                @endif
                PSN multi-lokasi dihitung di setiap provinsinya. Klik provinsi untuk memfilter.
            </p>
        </section>
        <section class="kartu col-span-12 xl:col-span-3" aria-labelledby="h-daftar">
            <h2 id="h-daftar" class="judul-panel mb-3">Jumlah per Provinsi</h2>
            <p class="label mb-2" x-show="meta?.nasional" x-text="'Lingkup nasional (tanpa provinsi spesifik): ' + meta?.nasional + ' PSN'"></p>
            <ul class="max-h-[560px] divide-y divide-slate-100 overflow-y-auto">
                <template x-for="d in daftar" :key="d.kode">
                    <li class="flex items-center justify-between py-1.5">
                        <button type="button" class="text-left text-aksen-700 hover:underline" @click="$store.filter.tambah('prov', d.kode)" x-text="d.label"></button>
                        <span class="angka font-semibold" x-text="angka(d.jumlah)"></span>
                    </li>
                </template>
            </ul>
        </section>
    </div>
</x-app-layout>
