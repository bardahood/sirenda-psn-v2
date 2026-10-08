@php
    $tautan = fn (string $t) => route('proyek.show', $psn).'?'.ltrim($qsKembali.'&tab='.$t, '&');
    $kembali = route('proyek.index').($qsKembali ? '?'.$qsKembali : '');
    $labelStatus = ['ON_TRACK' => 'On Track', 'BERISIKO' => 'Berisiko', 'TERLAMBAT' => 'Terlambat', 'TANPA_DATA' => 'Tanpa data'];
@endphp
<x-app-layout :judul="'Detail Proyek'">
    <div class="mx-auto max-w-[1376px]">
        {{-- Breadcrumb; tautan Kembali mempertahankan filter & opsi tabel portofolio --}}
        <nav class="label mb-4 flex flex-wrap items-center gap-1" aria-label="Breadcrumb">
            <a href="{{ route('dashboard').'?'.http_build_query(request()->only(['periode', 'prov', 'klaster', 'dit', 'status', 'kat', 'dana'])) }}" class="text-aksen-700 hover:underline">Ringkasan Eksekutif</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $kembali }}" class="text-aksen-700 hover:underline">Portofolio PSN</a>
            <span aria-hidden="true">/</span>
            <span class="max-w-md truncate" aria-current="page">{{ $header['nama'] }}</span>
        </nav>

        {{-- Header proyek --}}
        <section class="kartu mb-6">
            <div class="flex flex-wrap items-start gap-4">
                <div class="min-w-0 flex-1">
                    <p class="label">{{ $header['kode'] ?? 'Tanpa kode' }} · {{ $header['klaster'] ?? 'Tanpa klaster' }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-primer">{{ $header['nama'] }}</h2>
                    <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-1 text-isi">
                        <div><dt class="label inline">Status PSN</dt> <dd class="inline">{{ $header['status_psn'] ?? '–' }}</dd></div>
                        <div><dt class="label inline">K/L penanggung jawab</dt> <dd class="inline">{{ $header['kementerian_lembaga'] ? implode('; ', $header['kementerian_lembaga']) : '–' }}</dd></div>
                        <div><dt class="label inline">Pembaruan terakhir</dt> <dd class="inline">{{ $header['pembaruan_terakhir'] ? \Illuminate\Support\Carbon::parse($header['pembaruan_terakhir'])->translatedFormat('j F Y H:i') : '–' }}</dd></div>
                    </dl>
                </div>
                <div class="flex flex-col items-end gap-2">
                    @if ($header['status_progres'])
                        <span class="badge-{{ $header['status_progres'] }}"><span class="titik-{{ $header['status_progres'] }}"></span>{{ $labelStatus[$header['status_progres']] }}</span>
                    @endif
                    @if ($header['is_kritis'])
                        <span class="badge bg-red-50 text-red-800 ring-red-600/40">Risiko kritis</span>
                    @endif
                    <span class="label">Cut-off {{ $header['cutoff'] ?? '–' }}</span>
                    <a href="{{ $kembali }}" class="tombol-garis py-1.5">← Kembali ke portofolio</a>
                </div>
            </div>
        </section>

        {{-- Tab bar --}}
        <nav class="mb-6 overflow-x-auto border-b border-slate-200" aria-label="Bagian detail proyek">
            <ul class="flex min-w-max gap-1">
                @foreach (\App\Http\Controllers\ProyekController::TAB as $kode => $label)
                    <li>
                        <a href="{{ $tautan($kode) }}" @if ($tab === $kode) aria-current="page" @endif
                           @class(['inline-block border-b-2 px-4 py-2.5 text-isi', 'border-aksen font-semibold text-primer' => $tab === $kode, 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' => $tab !== $kode])>
                            {{ $label }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @if (in_array($tab, \App\Http\Controllers\ProyekController::TAB_TERSEDIA, true))
            @include('proyek.tab.'.$tab, ['data' => $data, 'labelStatus' => $labelStatus])
        @else
            <section class="kartu text-center">
                <p class="judul-panel">{{ \App\Http\Controllers\ProyekController::TAB[$tab] }}</p>
                <p class="mt-1 text-slate-600">Tab ini disiapkan pada fase berikutnya ({{ $tab === 'perencanaan' ? 'Fase 4: hasil penilaian 14 kriteria' : 'rilis v2: risiko harapan vs aktual, regulasi, dan stakeholder 4 level' }}).</p>
            </section>
        @endif
    </div>
</x-app-layout>
