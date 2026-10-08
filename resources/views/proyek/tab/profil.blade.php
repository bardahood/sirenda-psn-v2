<div class="grid grid-cols-12 gap-6">
    <section class="kartu col-span-12 xl:col-span-7" aria-labelledby="h-gu">
        <h3 id="h-gu" class="judul-panel mb-3">Gambaran Umum</h3>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            @foreach ($data['gambaran_umum'] as $label => $nilai)
                <div><dt class="label">{{ $label }}</dt><dd class="mt-0.5 {{ $nilai === null ? 'text-slate-400' : '' }}">{{ $nilai ?? 'Belum diisi' }}</dd></div>
            @endforeach
        </dl>
        @foreach ($data['narasi'] as $label => $isi)
            <div class="mt-4 border-t border-slate-100 pt-3">
                <h4 class="label">{{ $label }}</h4>
                <p class="mt-1 whitespace-pre-line">{{ $isi }}</p>
            </div>
        @endforeach
    </section>

    <div class="col-span-12 space-y-6 xl:col-span-5">
        <section class="kartu" aria-labelledby="h-kl">
            <h3 id="h-kl" class="judul-panel mb-3">Kelembagaan</h3>
            <dl class="space-y-2">
                @forelse ($data['kelembagaan'] as $k)
                    <div><dt class="label">{{ $k['peran'] }}</dt><dd>{{ implode('; ', $k['nama']) }}</dd></div>
                @empty
                    <p class="text-slate-500">Belum ada data kelembagaan.</p>
                @endforelse
            </dl>
        </section>
        <section class="kartu" aria-labelledby="h-lok">
            <h3 id="h-lok" class="judul-panel mb-3">Lokasi, Pendanaan & Unit Pengampu</h3>
            @foreach (['Lokasi' => $data['lokasi'], 'Indikasi sumber pendanaan' => $data['sumber_dana'], 'Unit pengampu' => $data['unit_pengampu']] as $label => $daftar)
                <h4 class="label mt-3 first:mt-0">{{ $label }}</h4>
                @if ($daftar)
                    <ul class="mt-1 list-inside list-disc">@foreach ($daftar as $v)<li>{{ $v }}</li>@endforeach</ul>
                @else
                    <p class="mt-1 text-slate-500">Belum diisi.</p>
                @endif
            @endforeach
        </section>
    </div>

    <section class="kartu col-span-12" aria-labelledby="h-item">
        <h3 id="h-item" class="judul-panel mb-3">Isian Project Profile</h3>
        @forelse ($data['item_profil'] as $bagian => $isi)
            <details class="border-b border-slate-100 py-2 last:border-0" @if ($loop->first) open @endif>
                <summary class="cursor-pointer font-medium">{{ $bagian }} <span class="label">({{ count($isi) }})</span></summary>
                <ul class="mt-2 space-y-2 pl-4">
                    @foreach ($isi as $baris)<li class="whitespace-pre-line">{{ $baris }}</li>@endforeach
                </ul>
            </details>
        @empty
            <p class="text-slate-500">Belum ada isian Project Profile.</p>
        @endforelse
    </section>
</div>
