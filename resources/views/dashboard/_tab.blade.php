{{-- Pilihan tampilan dashboard; tautan membawa filter global (query string). --}}
<nav class="flex shrink-0 rounded-lg border border-slate-200 bg-white p-0.5 text-[12px]" aria-label="Tampilan dashboard">
    @foreach (['dashboard' => 'Ikhtisar Portofolio', 'dashboard.pelaksanaan' => 'Pemantauan Pelaksanaan'] as $rute => $label)
        @php($aktif = request()->routeIs($rute))
        <a :href="$store.filter.tautan('{{ parse_url(route($rute), PHP_URL_PATH) }}')" @if ($aktif) aria-current="page" @endif
           @class(['whitespace-nowrap rounded-md px-2.5 py-1', 'bg-primer font-semibold text-white' => $aktif, 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! $aktif])>{{ $label }}</a>
    @endforeach
</nav>
