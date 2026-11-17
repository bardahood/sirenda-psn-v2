@php
    $bagian = [
        ['h-pemetaan', 'Pemetaan Stakeholder', 'Siapa saja yang terlibat dan perannya dalam pelaksanaan PSN.', $data['pemetaan']],
        ['h-kelembagaan', 'Kerangka Kelembagaan', 'Susunan kelembagaan pengelolaan PSN (penanggung jawab, pengelola, pelaksana, dan pendukung).', $data['kelembagaan']],
    ];
@endphp
<div class="grid grid-cols-12 gap-6">
    @foreach ($bagian as [$id, $judul, $uraian, $kelompok])
        <section class="kartu col-span-12" aria-labelledby="{{ $id }}">
            <h3 id="{{ $id }}" class="judul-panel">{{ $judul }} <span class="label font-normal">({{ collect($kelompok)->sum(fn ($k) => count($k['baris'])) }})</span></h3>
            <p class="label mb-3">{{ $uraian }}</p>
            @if ($kelompok)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-isi">
                        <thead class="border-b border-slate-200 text-label text-slate-600">
                            <tr><th class="py-2 pr-3">Kategori</th><th class="py-2 pr-3">Instansi utama</th><th class="py-2 pr-3">Instansi pendukung</th><th class="py-2 pr-3">Peran</th><th class="py-2">Keterangan</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($kelompok as $k)
                                @foreach ($k['baris'] as $i => $b)
                                    <tr class="align-top">
                                        @if ($i === 0)<td rowspan="{{ count($k['baris']) }}" class="py-2 pr-3"><span class="badge bg-primer-50 text-primer ring-primer/30">{{ $k['kategori'] }}</span></td>@endif
                                        <td class="py-2 pr-3 font-semibold">{{ $b['instansi_utama'] ?? '–' }}</td>
                                        <td class="py-2 pr-3">{{ $b['instansi_pendukung'] ?? '–' }}</td>
                                        <td class="max-w-[24rem] py-2 pr-3 whitespace-pre-line">{{ $b['peran'] ?? '–' }}</td>
                                        <td class="max-w-[18rem] py-2 whitespace-pre-line">{{ collect([$b['keterangan'], $b['catatan']])->filter()->implode("\n") ?: '–' }}</td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-slate-500">Belum diisi di SIRENDA.</p>
            @endif
        </section>
    @endforeach
    @if ($data['lainnya'])
        <section class="kartu col-span-12" aria-labelledby="h-st-lain">
            <h3 id="h-st-lain" class="judul-panel mb-3">Stakeholder Lainnya</h3>
            <ul class="list-inside list-disc text-isi">@foreach ($data['lainnya'] as $b)<li>{{ $b['instansi_utama'] ?? '–' }} — {{ $b['peran'] ?? '–' }}</li>@endforeach</ul>
        </section>
    @endif
</div>
