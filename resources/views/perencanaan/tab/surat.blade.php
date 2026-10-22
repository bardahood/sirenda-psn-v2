@php($surat = $usulan->psi?->surat)
<section class="kartu" aria-labelledby="h-kop">
    <h3 id="h-kop" class="judul-panel">Kop Surat</h3>
    <p class="label mb-4 font-normal text-slate-500">Identitas surat pengusulan sebagaimana diisi di aplikasi PSI.</p>
    @if ($surat)
        <dl class="grid gap-x-8 gap-y-4 md:grid-cols-2">
            @foreach ([['Nomor Surat', $surat->nomor], ['Sifat Surat', $surat->sifat], ['Lampiran', $surat->lampiran], ['Tempat Surat', $surat->tempat],
                ['Tanggal Surat', $surat->tanggal?->translatedFormat('j F Y')], ['Ditujukan Kepada', $surat->ditujukan_kepada], ['Perihal', $surat->perihal], ['Instansi Pengusul', $surat->instansi_pengusul]] as [$l, $v])
                <div><dt class="label">{{ $l }}</dt><dd class="mt-0.5 text-isi text-slate-900">{{ $v ?: '–' }}</dd></div>
            @endforeach
        </dl>
    @else
        <p class="text-slate-500">PSI tidak menyertakan data surat pengusulan.</p>
    @endif
</section>
@if ($surat && $surat->usulanPsi()->count() > 1)
    <section class="kartu mt-5" aria-labelledby="h-jamak">
        <h3 id="h-jamak" class="judul-panel">Proyek yang Diusulkan dalam Surat Ini</h3>
        <ul class="mt-2 divide-y divide-slate-100">
            @foreach ($surat->usulanPsi()->with(['usulan' => fn ($q) => $q->withoutGlobalScopes()])->get() as $lain)
                <li class="py-2"><a href="{{ route('perencanaan.show', [$lain->usulan_id, 'tab' => 'surat']) }}" @class(['hover:underline', 'font-semibold text-slate-900' => $lain->usulan_id === $usulan->id, 'text-aksen-700' => $lain->usulan_id !== $usulan->id])>{{ $lain->usulan?->nama }}</a>
                    <span class="label">· {{ $lain->psi_id }}</span></li>
            @endforeach
        </ul>
    </section>
@endif
