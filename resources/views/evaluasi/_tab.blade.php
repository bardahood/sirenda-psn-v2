{{-- Navigasi halaman evaluasi (padanan sheet kertas kerja). --}}
<nav class="flex flex-wrap gap-1 border-b border-slate-200" aria-label="Halaman evaluasi">
    @foreach ([['evaluasi.index', 'Pemantauan'], ['evaluasi.kertas-kerja', 'Kertas Kerja (Format Ringkas)'], ['evaluasi.grafik', 'Grafik Status Harian']] as [$rute, $label])
        @php($aktif = request()->routeIs($rute))
        <a href="{{ route($rute) }}" @class(['-mb-px border-b-2 px-4 py-2 text-[13.5px] font-semibold', 'border-aksen text-aksen' => $aktif, 'border-transparent text-slate-600 hover:text-slate-900' => ! $aktif])
           @if ($aktif) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
