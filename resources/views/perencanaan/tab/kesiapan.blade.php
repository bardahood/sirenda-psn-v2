@php($psi = $usulan->psi)
@php($html = fn ($h) => \App\Support\HtmlAman::bersihkan($h))
@php($nama = $usulan->jalurKritis->pluck('kegiatan', 'kode'))
<section class="kartu" aria-labelledby="h-jalur">
    <h3 id="h-jalur" class="judul-panel">Jalur Kritis Proyek</h3>
    <p class="label mb-4 font-normal text-slate-500">Kegiatan beserta target selesai dan kegiatan sebelumnya. Kotak kuning = jalur kritis.</p>
    @if ($usulan->jalurKritis->isEmpty())
        <p class="text-slate-500">PSI tidak menyertakan jalur kritis.</p>
    @else
        {{-- Diagram kotak-panah: kolom = tingkat ketergantungan --}}
        <div class="overflow-x-auto pb-2">
            <div class="flex min-w-max items-stretch gap-3" role="img" aria-label="Diagram jalur kritis proyek, {{ $usulan->jalurKritis->count() }} kegiatan">
                @foreach ($tingkatJalur as $t => $daftar)
                    @if (! $loop->first)<div class="flex items-center text-slate-400" aria-hidden="true">→</div>@endif
                    <div class="flex w-52 flex-col justify-center gap-2">
                        @foreach ($daftar as $l)
                            <div @class(['rounded-lg border p-2.5 text-[12px]', 'border-amber-400 bg-amber-100' => $l->is_jalur_kritis, 'border-slate-300 bg-white' => ! $l->is_jalur_kritis])>
                                <p class="font-semibold text-slate-900"><span class="text-slate-500">{{ $l->kode }}</span> · {{ $l->kegiatan }}</p>
                                <p class="text-slate-600">Target: {{ $l->target_selesai ?: '–' }}</p>
                                @if ($l->sebelumnya)<p class="text-[11px] text-slate-500">Setelah: {{ implode(', ', $l->sebelumnya) }}</p>@endif
                                @if ($l->is_jalur_kritis)<span class="mt-1 inline-block rounded bg-amber-300 px-1.5 text-[10px] font-semibold text-amber-950">Jalur kritis</span>@endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[640px] text-left text-isi">
                <thead class="border-b border-slate-200 text-label text-slate-600"><tr><th class="py-2 pr-3">Kode</th><th class="py-2 pr-3">Kegiatan</th><th class="py-2 pr-3">Target selesai</th><th class="py-2 pr-3">Kegiatan sebelumnya</th><th class="py-2">Jalur kritis</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($usulan->jalurKritis as $l)
                        <tr><td class="py-2 pr-3 font-semibold">{{ $l->kode }}</td><td class="py-2 pr-3">{{ $l->kegiatan }}</td><td class="py-2 pr-3">{{ $l->target_selesai ?: '–' }}</td>
                            <td class="py-2 pr-3 text-slate-600">{{ collect($l->sebelumnya)->map(fn ($k) => $k.' '.\Illuminate\Support\Str::limit($nama[$k] ?? '', 40))->implode('; ') ?: '–' }}</td>
                            <td class="py-2">{{ $l->is_jalur_kritis ? 'Ya' : 'Tidak' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if ($psi->diagram_jalur_kritis_url)<p class="mt-3 text-isi"><a href="{{ $psi->diagram_jalur_kritis_url }}" target="_blank" rel="noopener noreferrer" class="text-aksen-700 hover:underline">Lihat gambar diagram dari PSI ↗</a></p>@endif
    <div class="mt-5"><h4 class="text-[13px] font-semibold text-slate-800">Penjelasan tambahan</h4>
        <div class="isi-kaya mt-1 rounded-lg bg-slate-50 p-3">{!! $html($psi->penjelasan_jalur_kritis_html) ?? '–' !!}</div></div>
    @foreach ($psi->kesiapan_lain ?? [] as $b)
        <div class="mt-5"><h4 class="text-[13px] font-semibold text-slate-800">{{ $b['judul'] }}</h4>
            <div class="isi-kaya mt-1 rounded-lg bg-slate-50 p-3">{!! $html($b['isi_html'] ?? null) ?? '–' !!}</div></div>
    @endforeach
</section>
