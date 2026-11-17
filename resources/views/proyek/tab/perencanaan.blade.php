@php
    $n = fn ($v, $d = 2) => $v === null ? '–' : rtrim(rtrim(number_format($v, $d, ',', '.'), '0'), ',');
    $cfgEv = config('psn_dashboard.evaluasi_status');
    $tabelSasaran = [
        ['h-indikator', 'Indikator Kinerja', $data['indikator'], 'Indikator', null],
        ['h-penerima', 'Penerima Manfaat', $data['penerima'], 'Penerima manfaat', null],
        ['h-trisula', 'Kontribusi Trisula Pembangunan', $data['trisula'], 'Indikator Trisula', 'kategori'],
    ];
@endphp
<div class="grid grid-cols-12 gap-6">
    {{-- Ringkasan & tautan terkait --}}
    <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-target">
        <h3 id="h-target" class="judul-panel mb-3">Target Tahunan PSN</h3>
        @if ($data['target_tahunan'])
            <ol class="space-y-2">
                @foreach ($data['target_tahunan'] as $t)
                    <li class="flex gap-3 text-isi"><span class="badge h-fit shrink-0 bg-primer-50 text-primer ring-primer/30">{{ $t['tahun'] }}</span><span class="whitespace-pre-line">{{ $t['uraian'] }}</span></li>
                @endforeach
            </ol>
        @else
            <p class="text-slate-500">Target tahunan belum diisi di SIRENDA.</p>
        @endif
    </section>

    <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-asal">
        <h3 id="h-asal" class="judul-panel mb-3">Asal Penetapan & Evaluasi</h3>
        <h4 class="label">Usulan PSN (Aplikasi Perencanaan Dit. PSI)</h4>
        @forelse ($data['usulan'] as $u)
            <div class="mt-1 rounded-lg border border-slate-200 p-2 text-isi">
                <p class="font-semibold">{{ $u['nama'] }}</p>
                <p class="label">RKP {{ $u['tahun_rkp'] }} · nilai akhir {{ $u['nilai_akhir'] !== null ? $n((float) $u['nilai_akhir'], 2) : '–' }}
                    · rekomendasi {{ $u['rekomendasi'] ? \Illuminate\Support\Str::of($u['rekomendasi'])->replace('_', ' ')->lower()->ucfirst() : 'belum' }}</p>
                @can('perencanaan.lihat')<a href="{{ route('perencanaan.show', $u['id']) }}" class="label text-aksen-700 hover:underline">Lihat penilaian usulan →</a>@endcan
            </div>
        @empty
            <p class="mt-1 text-slate-500">Tidak tercatat berasal dari usulan PSI (PSN ditetapkan sebelum alur usulan baru).</p>
        @endforelse
        <h4 class="label mt-4">Evaluasi keberlanjutan status RKP {{ $cfgEv['tahun_rkp'] }}</h4>
        @if ($data['evaluasi'])
            <p class="mt-1"><span class="badge-EV_{{ $data['evaluasi']['status_akhir'] }}"><span class="titik-EV_{{ $data['evaluasi']['status_akhir'] }}"></span>{{ $cfgEv['status'][$data['evaluasi']['status_akhir']] ?? $data['evaluasi']['status_akhir'] }}</span></p>
        @else
            <p class="mt-1 text-slate-500">Form Pengendalian belum diterima.</p>
        @endif
        @can('evaluasi.lihat')<a href="{{ route('evaluasi.show', $psn) }}" class="label mt-1 inline-block text-aksen-700 hover:underline">Buka evaluasi PSN →</a>@endcan
    </section>

    {{-- Indikator, penerima manfaat, trisula: baseline, target akhir, target/realisasi per tahun --}}
    @foreach ($tabelSasaran as [$id, $judul, $baris, $kolom, $kelompok])
        <section class="kartu col-span-12" aria-labelledby="{{ $id }}">
            <h3 id="{{ $id }}" class="judul-panel mb-3">{{ $judul }} <span class="label font-normal">({{ count($baris) }})</span></h3>
            @if ($baris)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-left text-isi">
                        <thead class="border-b border-slate-200 text-label text-slate-600">
                            <tr>
                                @if ($kelompok)<th rowspan="2" class="py-2 pr-3">Kategori</th>@endif
                                <th rowspan="2" class="py-2 pr-3">{{ $kolom }}</th><th rowspan="2" class="py-2 pr-3">Satuan</th>
                                <th rowspan="2" class="py-2 pr-3 text-right">Baseline</th><th rowspan="2" class="py-2 pr-3 text-right">Target akhir</th>
                                @foreach ($data['tahun'] as $th)<th colspan="2" class="border-l border-slate-100 px-2 py-1 text-center">{{ $th }}</th>@endforeach
                                <th rowspan="2" class="py-2 pl-2 text-right">Capaian</th>
                            </tr>
                            <tr>@foreach ($data['tahun'] as $th)<th class="border-l border-slate-100 px-2 pb-1 text-right font-normal">T</th><th class="px-2 pb-1 text-right font-normal">R</th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($baris as $b)
                                <tr class="align-top">
                                    @if ($kelompok)<td class="py-2 pr-3 text-label">{{ $b['kategori'] }}</td>@endif
                                    <td class="max-w-[24rem] py-2 pr-3">{{ $b['uraian'] ?? '–' }}@if (! empty($b['kontribusi']))<span class="block text-label text-slate-500">{{ $b['kontribusi'] }}</span>@endif</td>
                                    <td class="py-2 pr-3">{{ $b['satuan'] ?? '–' }}</td>
                                    <td class="angka py-2 pr-3 text-right">{{ $n($b['baseline']) }}@if ($b['baseline_tahun'])<span class="block text-label text-slate-500">{{ $b['baseline_tahun'] }}</span>@endif</td>
                                    <td class="angka py-2 pr-3 text-right">{{ $n($b['target_akhir']) }}</td>
                                    @foreach ($data['tahun'] as $th)
                                        <td class="angka border-l border-slate-100 px-2 py-2 text-right">{{ $n($b['tahun'][$th]['target']) }}</td>
                                        <td class="angka px-2 py-2 text-right">{{ $n($b['tahun'][$th]['realisasi']) }}</td>
                                    @endforeach
                                    <td class="angka py-2 pl-2 text-right">{{ $n($b['capaian']) }}@if ($b['persen'] !== null)<span class="block text-label text-slate-500">{{ $n($b['persen'], 1) }}%</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="label mt-2">T = target, R = realisasi per tahun. Capaian = capaian kumulatif terakhir terhadap target akhir.</p>
            @else
                <p class="text-slate-500">Belum diisi di SIRENDA.</p>
            @endif
        </section>
    @endforeach

    <section class="kartu col-span-12 lg:col-span-4" aria-labelledby="h-sdgs">
        <h3 id="h-sdgs" class="judul-panel mb-3">Kontribusi SDGs</h3>
        @forelse ($data['sdgs'] as $s)
            <p class="flex gap-2 text-isi"><span class="badge h-fit shrink-0 bg-slate-100 text-slate-700 ring-slate-400/40">{{ $s['kode'] }}</span><span>{{ $s['nama'] }}@if ($s['keterangan'])<span class="block text-label text-slate-500">{{ $s['keterangan'] }}</span>@endif</span></p>
        @empty
            <p class="text-slate-500">Belum diisi.</p>
        @endforelse
    </section>

    <section class="kartu col-span-12 lg:col-span-4" aria-labelledby="h-dukungan">
        <h3 id="h-dukungan" class="judul-panel mb-3">Dukungan yang Dibutuhkan</h3>
        @if ($data['dukungan'])
            <ul class="list-inside list-disc space-y-1 text-isi">@foreach ($data['dukungan'] as $d)<li class="whitespace-pre-line">{{ $d }}</li>@endforeach</ul>
        @else
            <p class="text-slate-500">Belum diisi.</p>
        @endif
    </section>

    <section class="kartu col-span-12 lg:col-span-4" aria-labelledby="h-fasilitas">
        <h3 id="h-fasilitas" class="judul-panel mb-3">Fasilitas Kemudahan yang Diterima</h3>
        @forelse ($data['fasilitas'] as $f)
            <p class="text-isi"><span class="font-semibold">{{ $f['nama'] }}</span> <span class="label">{{ $f['tahun'] ? implode(', ', $f['tahun']) : '' }}</span>
                @if ($f['keterangan'])<span class="block text-label text-slate-500">{{ $f['keterangan'] }}</span>@endif</p>
        @empty
            <p class="text-slate-500">Belum ada fasilitas tercatat.</p>
        @endforelse
    </section>
</div>
