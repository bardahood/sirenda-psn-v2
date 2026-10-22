@php($psi = $usulan->psi)
@php($html = fn ($h) => \App\Support\HtmlAman::bersihkan($h))
<section class="kartu" aria-labelledby="h-profil">
    <h3 id="h-profil" class="judul-panel mb-4">Profil Proyek</h3>
    <div class="space-y-5">
        <div><h4 class="text-[13px] font-semibold text-slate-800">Tujuan</h4>
            <p class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-isi text-slate-800">{{ $psi->tujuan ?: '–' }}</p></div>
        <div><h4 class="text-[13px] font-semibold text-slate-800">Sasaran</h4>
            <p class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-isi text-slate-800">{{ $psi->sasaran ?: '–' }}</p></div>
        <div><h4 class="text-[13px] font-semibold text-slate-800">Rangkuman Lingkup Pekerjaan</h4>
            <div class="isi-kaya mt-1 rounded-lg bg-slate-50 p-3">{!! $html($psi->lingkup_pekerjaan_html) ?? '–' !!}</div></div>
        @foreach ($psi->rincian_lain ?? [] as $b)
            <div><h4 class="text-[13px] font-semibold text-slate-800">{{ $b['judul'] }}</h4>
                <div class="isi-kaya mt-1 rounded-lg bg-slate-50 p-3">{!! $html($b['isi_html'] ?? null) ?? '–' !!}</div></div>
        @endforeach
    </div>
</section>
