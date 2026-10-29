@use('App\Models\UsulanDokumen')
@php($per = $usulan->dokumen->groupBy('jenis'))
<div class="grid gap-5 lg:grid-cols-3">
    @foreach (UsulanDokumen::JENIS as $jenis => $judul)
        <section class="kartu" aria-labelledby="h-dok-{{ $jenis }}">
            <h3 id="h-dok-{{ $jenis }}" class="judul-panel">{{ $judul }}</h3>
            <p class="label mb-3 font-normal text-slate-500">
                @switch($jenis)
                    @case('PDF_USULAN') Dokumen PDF yang dibangkitkan dari isian aplikasi PSI. @break
                    @case('SURAT_PENGUSULAN') PDF surat pengusulan PSN yang telah ditandatangani. @break
                    @default Dokumen pendukung: studi kelayakan, AMDAL, DED, dll.
                @endswitch
            </p>
            <ul class="space-y-2">
                @forelse ($per[$jenis] ?? [] as $d)
                    <li class="flex items-center gap-3 rounded-lg border border-slate-200 p-2.5">
                        <svg class="h-6 w-6 shrink-0 text-red-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6"/></svg>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-isi font-semibold text-slate-900">{{ $d->judul ?: $d->nama_berkas }}</span>
                            <span class="block text-[11px] text-slate-500">{{ $d->nama_berkas }}{{ $d->ukuran ? ' · '.number_format($d->ukuran / 1024, 0, ',', '.').' KB' : '' }}</span>
                        </span>
                        @if ($d->url)<a href="{{ route('perencanaan.dokumen', [$usulan, $d]) }}" target="_blank" rel="noopener noreferrer" class="tombol-garis px-2.5 py-1 text-label">Buka ↗</a>@endif
                    </li>
                @empty
                    <li class="rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[12px] text-amber-900">Belum ada {{ strtolower($judul) }}.</li>
                @endforelse
            </ul>
        </section>
    @endforeach
</div>
<p class="mt-3 text-label text-slate-500">Berkas tersimpan di aplikasi PSI (SIF) dan diambil oleh server SIRENDA saat dibuka, sehingga tidak memerlukan akun PSI.</p>
