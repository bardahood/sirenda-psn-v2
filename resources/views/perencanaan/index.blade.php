@use('App\Enums\Rekomendasi')
@use('App\Services\AlurUsulan')
@php
    $tautanFilter = fn (array $ubah) => route('perencanaan.index', array_filter(array_merge($filter, $ubah), fn ($v) => $v !== null && $v !== ''));
    $urutanAlur = ['DITERIMA', 'PENILAIAN', 'DINILAI', 'PLENO', 'REKOMENDASI', 'TERKIRIM'];
@endphp
<x-app-layout judul="Perencanaan & Penilaian Usulan PSN" subjudul="Usulan dari aplikasi PSI dan input SIRENDA: penilaian, pleno, rekomendasi, dan pengiriman hasil">
    <x-slot:aksi>
        <a href="{{ route('perencanaan.pleno.index') }}" class="tombol-garis py-1.5">Sidang Pleno</a>
        @can('create', App\Models\UsulanPsn::class)
            <a href="{{ route('perencanaan.create') }}" class="tombol-primer py-1.5">+ Usulan manual</a>
        @endcan
    </x-slot:aksi>

    <div class="mx-auto max-w-[1600px] space-y-5">
        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif

        {{-- Alur bisnis: jumlah usulan per tahap (klik untuk memfilter) --}}
        <section aria-labelledby="h-alur">
            <h2 id="h-alur" class="sr-only">Alur usulan</h2>
            <ol class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @foreach ($urutanAlur as $i => $kode)
                    @php($aktif = ($filter['tahap'] ?? null) === $kode)
                    <li>
                        <a href="{{ $tautanFilter(['tahap' => $aktif ? null : $kode, 'page' => null]) }}" aria-pressed="{{ $aktif ? 'true' : 'false' }}"
                           @class(['kartu relative block !p-4 transition hover:shadow-md', 'ring-2 ring-aksen' => $aktif])>
                            <span class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primer text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                                <span class="badge-ALUR_{{ $kode }}"><span class="titik-ALUR_{{ $kode }}"></span>{{ AlurUsulan::TAHAP[$kode] }}</span>
                            </span>
                            <span class="angka mt-2 block text-[26px] font-bold leading-8 text-slate-900">{{ number_format($rekap[$kode] ?? 0, 0, ',', '.') }}</span>
                            <span class="block text-[11px] text-slate-500">
                                @switch($kode)
                                    @case('DITERIMA') belum dinilai @break
                                    @case('PENILAIAN') penilaian masih draf @break
                                    @case('DINILAI') siap dibahas pleno @break
                                    @case('PLENO') menunggu rekomendasi @break
                                    @case('REKOMENDASI') siap dikirim ke PSI @break
                                    @case('TERKIRIM') hasil diterima PSI @break
                                @endswitch
                            </span>
                        </a>
                    </li>
                @endforeach
            </ol>
            @if ($rekap['DITARIK'] ?? 0)
                <p class="mt-2 text-label"><a href="{{ $tautanFilter(['tahap' => 'DITARIK', 'page' => null]) }}" class="text-red-700 hover:underline">{{ $rekap['DITARIK'] }} usulan ditarik pengusul</a></p>
            @endif
        </section>

        <section class="kartu" aria-labelledby="h-usulan">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <h2 id="h-usulan" class="judul-panel mr-auto">Daftar Usulan PSN <span class="label font-normal">{{ $usulan->total() }} usulan · {{ $jumlahPsi }} dari aplikasi PSI</span></h2>
                @isset($filter['tahap'])<input type="hidden" name="tahap" value="{{ $filter['tahap'] }}">@endisset
                <label class="text-isi"><span class="label block">Cari</span>
                    <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100" class="mt-1 w-56 rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" placeholder="Nama usulan…">
                </label>
                @foreach ([
                    ['sumber', 'Sumber', collect(['PSI' => 'Aplikasi PSI', 'MANUAL' => 'Input SIRENDA'])],
                    ['tahun', 'Tahun RKP', $opsi['tahun']->mapWithKeys(fn ($t) => [$t => $t])],
                    ['klaster', 'Klaster', $opsi['klaster']],
                    ['status', 'Status penilaian', collect(['BELUM_DINILAI' => 'Belum dinilai', 'DRAFT' => 'Draf', 'FINAL' => 'Final'])],
                    ['rekomendasi', 'Rekomendasi sistem', collect(Rekomendasi::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])],
                ] as [$nama, $label, $pilihan])
                    <label class="text-isi"><span class="label block">{{ $label }}</span>
                        <select name="{{ $nama }}" class="mt-1 max-w-[14rem] rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                            <option value="">Semua</option>
                            @foreach ($pilihan as $nilai => $teks)<option value="{{ $nilai }}" @selected((string) ($filter[$nama] ?? '') === (string) $nilai)>{{ $teks }}</option>@endforeach
                        </select>
                    </label>
                @endforeach
                <button class="tombol-garis py-1.5">Terapkan</button>
                <a href="{{ route('perencanaan.index') }}" class="tombol-garis py-1.5">Reset</a>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">Usulan</th><th class="py-2 pr-3">Sumber</th><th class="py-2 pr-3">Tahun</th><th class="py-2 pr-3">Klaster</th><th class="py-2 pr-3">Pengusul</th><th class="py-2 pr-3">Direktorat pengampu</th><th class="py-2 pr-3 text-right">Nilai akhir</th><th class="py-2 pr-3">Rekomendasi</th><th class="py-2">Tahap alur</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($usulan as $u)
                            @php($rekSistem = $u->rekomendasi ? Rekomendasi::tryFrom($u->rekomendasi) : null)
                            @php($rekResmi = $u->rekomendasiFinal ? Rekomendasi::tryFrom($u->rekomendasiFinal->rekomendasi) : null)
                            <tr class="align-top hover:bg-slate-50">
                                <td class="max-w-[24rem] py-2 pr-3">
                                    <a href="{{ route('perencanaan.show', $u) }}" class="font-medium text-aksen-700 hover:underline">{{ $u->nama }}</a>
                                    @if ($u->perlu_tinjau_ulang)<span class="badge ml-1 bg-amber-50 text-amber-900 ring-amber-600/40" title="PSI mengirim versi baru setelah penilaian FINAL">Versi baru dari PSI</span>@endif
                                </td>
                                <td class="whitespace-nowrap py-2 pr-3">
                                    @if ($u->psi_id)<span class="badge bg-aksen-50 text-aksen-700 ring-aksen/30" title="ID PSI: {{ $u->psi_id }}">PSI</span>
                                        <span class="block text-[11px] text-slate-500">{{ $u->psi_id }}</span>
                                    @else<span class="badge bg-slate-100 text-slate-700 ring-slate-400/40">SIRENDA</span>@endif
                                </td>
                                <td class="py-2 pr-3">{{ $u->tahun_rkp }}</td>
                                <td class="max-w-[11rem] truncate py-2 pr-3" title="{{ $u->klaster?->nama }}">{{ $u->klaster?->nama ?? '–' }}</td>
                                <td class="max-w-[12rem] truncate py-2 pr-3" title="{{ $u->pengusulInstansi?->nama ?? $u->pengusul_teks }}">{{ $u->pengusulInstansi?->nama ?? $u->pengusul_teks ?? '–' }}</td>
                                <td class="max-w-[12rem] truncate py-2 pr-3">{{ $u->unitKerja?->nama ?? '–' }}</td>
                                <td class="angka py-2 pr-3 text-right font-semibold">{{ $u->nilai_akhir !== null ? number_format($u->nilai_akhir, 2, ',', '.') : '–' }}</td>
                                <td class="py-2 pr-3">
                                    @if ($rekResmi)<span class="{{ $rekResmi->badge() }}" title="Rekomendasi resmi pasca pleno">{{ $rekResmi->label() }}</span><span class="block text-[11px] text-slate-500">resmi</span>
                                    @elseif ($rekSistem)<span class="{{ $rekSistem->badge() }}" title="Hasil hitung sistem dari skor">{{ $rekSistem->label() }}</span><span class="block text-[11px] text-slate-500">hitungan sistem</span>
                                    @else<span class="label">Belum dinilai</span>@endif
                                </td>
                                <td class="whitespace-nowrap py-2"><span class="badge-ALUR_{{ $u->tahap }}"><span class="titik-ALUR_{{ $u->tahap }}"></span>{{ AlurUsulan::TAHAP[$u->tahap] ?? $u->tahap }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-8 text-center text-slate-600">Belum ada usulan yang sesuai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $usulan->links() }}</div>
        </section>
    </div>
</x-app-layout>
