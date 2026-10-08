<nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="Bagian pengaturan">
    <ul class="flex min-w-max gap-1">
        @foreach (['pengaturan.pengguna.index' => 'Pengguna & Peran', 'pengaturan.cutoff' => 'Cut-off & Snapshot', 'pengaturan.master' => 'Master Data'] as $rute => $label)
            @php($aktif = request()->routeIs(str_replace('.index', '', $rute).'*'))
            <li><a href="{{ route($rute) }}" @if ($aktif) aria-current="page" @endif
                   @class(['inline-block border-b-2 px-4 py-2.5 text-isi', 'border-aksen font-semibold text-primer' => $aktif, 'border-transparent text-slate-600 hover:text-slate-900' => ! $aktif])>{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>
@if (session('status'))<div class="mb-4 rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
@if ($errors->any())<div class="mb-4 rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
