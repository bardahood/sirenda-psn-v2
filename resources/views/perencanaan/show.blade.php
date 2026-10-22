@use('App\Models\UsulanPsn')
@use('App\Services\AlurUsulan')
@php
    $psi = $usulan->psi;
    $rekResmi = $usulan->rekomendasiFinal;
@endphp
<x-app-layout judul="Usulan PSN" :subjudul="$dariPsi ? 'Diterima dari aplikasi PSI · ID '.$psi->psi_id.' · versi '.$psi->versi : 'Usulan diinput di SIRENDA'">
    <x-slot:aksi>
        <a href="{{ route('perencanaan.index') }}" class="tombol-garis py-1.5">← Daftar usulan</a>
    </x-slot:aksi>

    <div class="mx-auto max-w-[1600px] space-y-5">
        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
        @if ($psi?->status_psi === 'DITARIK')<div class="rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert">Usulan ini <b>ditarik</b> oleh pengusul di aplikasi PSI.</div>@endif
        @if ($psi?->perlu_tinjau_ulang)<div class="rounded-kartu border border-amber-200 bg-amber-50 p-3 text-amber-900" role="status">PSI mengirim <b>versi {{ $psi->versi }}</b> setelah penilaian ditetapkan FINAL. Tinjau perubahan isian; buka kembali penilaian bila perlu.</div>@endif

        {{-- Identitas & alur --}}
        <section class="kartu">
            <div class="flex flex-wrap items-start gap-4">
                <div class="min-w-0 flex-1">
                    <p class="label">Usulan RKP {{ $usulan->tahun_rkp }} · {{ $usulan->klaster?->nama ?? ($psi?->klaster_teks ? $psi->klaster_teks.' (belum terpetakan)' : 'Tanpa klaster') }}</p>
                    <h2 class="mt-1 text-xl font-bold text-primer">{{ $usulan->nama }}</h2>
                    <dl class="mt-2 flex flex-wrap gap-x-8 gap-y-1 text-isi">
                        <div><dt class="label inline">Pengusul</dt> <dd class="inline">{{ $usulan->pengusulInstansi?->nama ?? $usulan->pengusul_teks ?? '–' }} ({{ UsulanPsn::JENIS_PENGUSUL[$usulan->jenis_pengusul] ?? 'jenis belum ditetapkan' }})</dd></div>
                        <div><dt class="label inline">Direktorat pengampu</dt> <dd class="inline">{{ $usulan->unitKerja?->nama ?? 'Belum ditetapkan' }}</dd></div>
                        <div><dt class="label inline">Nilai investasi</dt> <dd class="angka inline">{{ $usulan->nilai_investasi_rp !== null ? 'Rp '.number_format($usulan->nilai_investasi_rp / 1e12, 2, ',', '.').' T' : '–' }}</dd></div>
                        <div><dt class="label inline">Lokasi</dt> <dd class="inline">{{ $usulan->lokasi->map(fn ($l) => $l->provinsi?->nama)->filter()->unique()->implode(', ') ?: '–' }}</dd></div>
                    </dl>
                </div>
                <div class="text-right">
                    <span class="badge-ALUR_{{ $tahap }}"><span class="titik-ALUR_{{ $tahap }}"></span>{{ AlurUsulan::TAHAP[$tahap] }}</span>
                    @if ($rekResmi)<p class="mt-1"><span class="{{ $rekResmi->enum()->badge() }}">Rekomendasi: {{ $rekResmi->enum()->label() }}</span></p>@endif
                </div>
            </div>

            {{-- Stepper alur bisnis --}}
            <ol class="mt-4 grid gap-2 border-t border-slate-100 pt-4 {{ count($langkah) === 5 ? 'sm:grid-cols-5' : 'sm:grid-cols-4' }}" aria-label="Tahapan alur usulan">
                @foreach ($langkah as $i => $l)
                    <li class="flex items-start gap-2" @if ($l['status'] === 'aktif') aria-current="step" @endif>
                        <span @class(['flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-bold',
                            'bg-green-600 text-white' => $l['status'] === 'selesai', 'bg-aksen text-white ring-4 ring-aksen/20' => $l['status'] === 'aktif', 'bg-slate-200 text-slate-600' => $l['status'] === 'menunggu'])>
                            {!! $l['status'] === 'selesai' ? '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12l5 5L20 7"/></svg>' : $i + 1 !!}
                        </span>
                        <span class="min-w-0">
                            <span @class(['block text-[13px] font-semibold', 'text-slate-900' => $l['status'] !== 'menunggu', 'text-slate-500' => $l['status'] === 'menunggu'])>{{ $l['judul'] }}
                                <span class="sr-only">({{ $l['status'] }})</span></span>
                            <span class="block text-[11px] leading-snug text-slate-500">{{ $l['uraian'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        {{-- Tab --}}
        <nav class="flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Bagian usulan">
            @foreach ($tabTersedia as $kode => $label)
                <a href="{{ route('perencanaan.show', [$usulan, 'tab' => $kode]) }}" @if ($tab === $kode) aria-current="page" @endif
                   @class(['-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-[13px]', 'border-aksen font-semibold text-aksen-700' => $tab === $kode, 'border-transparent text-slate-600 hover:text-slate-900' => $tab !== $kode])>{{ $label }}</a>
            @endforeach
        </nav>

        @include('perencanaan.tab.'.$tab)
    </div>
</x-app-layout>
