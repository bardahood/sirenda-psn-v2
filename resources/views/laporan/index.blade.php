@use('App\Services\PengisianService')
<x-app-layout judul="Laporan">
    <div class="mx-auto max-w-[1376px] space-y-6">
        <section class="kartu" aria-labelledby="h-arsip">
            <h2 id="h-arsip" class="judul-panel">Arsip Laporan per Cut-off</h2>
            <p class="label mb-4">Angka setiap laporan dibaca dari snapshot cut-off yang sudah diterbitkan sehingga tidak berubah meskipun data diperbarui kemudian. Laporan di halaman ini bersifat nasional; untuk laporan terfilter gunakan tombol unduh pada Ringkasan Eksekutif atau Portofolio.</p>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">Periode</th><th class="py-2 pr-3">Tanggal cut-off</th><th class="py-2 pr-3">Diterbitkan</th><th class="py-2 pr-3 text-right">PSN</th><th class="py-2 pr-3 text-right">Risiko</th><th class="py-2 pr-3">Pengisian</th><th class="py-2">Unduhan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 align-top">
                        @forelse ($periode as $c)
                            @php($g = ($pengisian[$c->id] ?? collect())->pluck('n', 'status'))
                            <tr>
                                <td class="py-2 pr-3 font-semibold text-primer">{{ $c->kode }}</td>
                                <td class="py-2 pr-3">{{ $c->tanggal_cutoff->translatedFormat('j F Y') }}</td>
                                <td class="py-2 pr-3 text-label">{{ $c->diterbitkan_at?->translatedFormat('j M Y H:i') ?? '–' }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ number_format($psn[$c->id] ?? 0, 0, ',', '.') }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ number_format($risiko[$c->id] ?? 0, 0, ',', '.') }}</td>
                                <td class="py-2 pr-3">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (['DIVERIFIKASI', 'DIAJUKAN', 'DRAFT', 'DIKEMBALIKAN'] as $st)
                                            @if ($g[$st] ?? 0)<span class="badge-{{ $st }}"><span class="titik-{{ $st }}"></span>{{ PengisianService::STATUS[$st] }}: {{ $g[$st] }}</span>@endif
                                        @endforeach
                                        @if ($g->isEmpty())<span class="label">Belum ada isian</span>@endif
                                    </div>
                                </td>
                                <td class="py-2">
                                    <ul class="space-y-1">
                                        <li><a class="text-aksen-700 hover:underline" href="{{ route('laporan.ringkasan-pdf', ['periode' => $c->kode]) }}">PDF Ringkasan Eksekutif</a></li>
                                        @can('portofolio.lihat')<li><a class="text-aksen-700 hover:underline" href="{{ route('api.proyek.index', ['periode' => $c->kode, 'format' => 'xlsx']) }}">Excel Portofolio PSN</a></li>@endcan
                                        @can('risiko.lihat')<li><a class="text-aksen-700 hover:underline" href="{{ route('laporan.risiko-xlsx', ['periode' => $c->kode]) }}">Excel Register Risiko</a></li>@endcan
                                        <li><a class="text-aksen-700 hover:underline" href="{{ route('laporan.pengisian-xlsx', ['periode' => $c->kode]) }}">Excel Rekap Pengisian & Verifikasi</a></li>
                                    </ul>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-500">Belum ada cut-off yang diterbitkan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
