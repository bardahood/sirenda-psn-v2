@use('App\Support\Label')
<ul class="divide-y divide-slate-100">
    @forelse ($riwayat as $a)
        <li class="py-2">
            <div class="flex flex-wrap items-baseline gap-x-2">
                <span><b>{{ Label::pengguna($a['pengguna']) }}</b> {{ Label::aksi($a['aksi']) }} {{ Label::tabel($a['tabel']) }}</span>
                @if ($a['sumber'] === 'IMPOR_LEGACY')<span class="rounded bg-slate-100 px-1.5 text-label text-slate-600">riwayat sistem lama</span>@endif
                <span class="label ml-auto">{{ $a['waktu'] ? \Illuminate\Support\Carbon::parse($a['waktu'])->translatedFormat('j M Y H:i') : 'waktu tidak tercatat' }}</span>
            </div>
            @if (! ($ringkas ?? false) && $a['aksi'] === 'UPDATE' && is_array($a['nilai_baru']))
                <details class="mt-1">
                    <summary class="cursor-pointer text-label text-aksen-700">Lihat perubahan ({{ count($a['nilai_baru']) }} kolom)</summary>
                    <table class="mt-1 w-full text-label">
                        <thead class="text-slate-600"><tr><th class="py-1 pr-2 text-left">Kolom</th><th class="py-1 pr-2 text-left">Nilai lama</th><th class="py-1 text-left">Nilai baru</th></tr></thead>
                        <tbody>
                            @foreach ($a['nilai_baru'] as $kolom => $baru)
                                <tr class="align-top"><td class="py-1 pr-2 font-medium">{{ $kolom }}</td><td class="py-1 pr-2 text-slate-600">{{ \Illuminate\Support\Str::limit(json_encode($a['nilai_lama'][$kolom] ?? null, JSON_UNESCAPED_UNICODE), 120) }}</td><td class="py-1">{{ \Illuminate\Support\Str::limit(json_encode($baru, JSON_UNESCAPED_UNICODE), 120) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @elseif ($a['kolom'] && $a['aksi'] === 'UPDATE')
                <p class="label mt-0.5 truncate">Kolom: {{ implode(', ', array_slice($a['kolom'], 0, 8)) }}{{ count($a['kolom']) > 8 ? ', …' : '' }}</p>
            @endif
        </li>
    @empty
        <li class="py-2 text-slate-500">Belum ada riwayat perubahan.</li>
    @endforelse
</ul>
