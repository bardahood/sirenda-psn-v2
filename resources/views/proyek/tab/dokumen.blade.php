<div class="grid grid-cols-12 gap-6">
    <section class="kartu col-span-12 xl:col-span-5" aria-labelledby="h-dok">
        <h3 id="h-dok" class="judul-panel mb-3">Dokumen</h3>
        <ul class="divide-y divide-slate-100">
            @forelse ($data['dokumen'] as $d)
                <li class="py-2">
                    @if ($d->path)
                        <a href="{{ \Illuminate\Support\Str::startsWith($d->path, ['http://', 'https://']) ? $d->path : \Illuminate\Support\Facades\Storage::url($d->path) }}" target="_blank" rel="noopener" class="font-medium text-aksen-700 hover:underline">{{ $d->judul }}</a>
                    @else
                        <span class="font-medium">{{ $d->judul }}</span>
                    @endif
                    <p class="label">{{ $d->kategori?->nama ?? 'Tanpa kategori' }}@if ($d->deskripsi) · {{ \Illuminate\Support\Str::limit($d->deskripsi, 100) }}@endif</p>
                </li>
            @empty
                <li class="py-2 text-slate-500">Belum ada dokumen.</li>
            @endforelse
        </ul>
    </section>
    <section class="kartu col-span-12 xl:col-span-7" aria-labelledby="h-riw">
        <h3 id="h-riw" class="judul-panel mb-3">Riwayat Perubahan (100 terakhir)</h3>
        @include('proyek.tab._riwayat', ['riwayat' => $data['riwayat']])
    </section>
</div>
