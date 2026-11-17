@php
    $tgl = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j F Y') : null;
    $jumlahTahap = count($data['tahap']);
@endphp
<div class="grid grid-cols-12 gap-6">
    <section class="kartu col-span-12" aria-labelledby="h-pipeline">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h3 id="h-pipeline" class="judul-panel mr-auto">Pipeline Kebutuhan Regulasi <span class="label font-normal">({{ count($data['regulasi']) }} regulasi)</span></h3>
            <a href="{{ route('risiko').'?psn='.$psn->id }}" class="label text-aksen-700 hover:underline">Buka di menu Risiko, Isu &amp; Regulasi →</a>
        </div>
        <ol class="grid grid-cols-2 gap-2 md:grid-cols-4">
            @foreach ($data['tahap'] as $i => $t)
                <li class="rounded-lg border border-slate-200 p-3">
                    <span class="flex items-center gap-2 text-label text-slate-600"><span class="flex h-5 w-5 items-center justify-center rounded-full bg-primer text-[11px] font-bold text-white">{{ $i + 1 }}</span>{{ $t['label'] }}</span>
                    <span class="angka mt-1 block text-2xl font-bold text-primer">{{ $t['jumlah'] }}</span>
                </li>
            @endforeach
        </ol>
        <p class="label mt-2">SIRENDA mencatat nama, justifikasi, dan target tahun regulasi. Tahap awal setiap regulasi = Identifikasi, lalu dimutakhirkan hingga Ditetapkan beserta nomor dan tanggal penetapan.</p>
    </section>

    <section class="kartu col-span-12" aria-labelledby="h-daftar-reg">
        <h3 id="h-daftar-reg" class="judul-panel mb-3">Daftar Regulasi</h3>
        @if ($data['regulasi'])
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">Regulasi</th><th class="py-2 pr-3">Justifikasi</th><th class="py-2 pr-3">Penanggung jawab</th><th class="py-2 pr-3">Tahap</th><th class="py-2 pr-3">Target tahun</th><th class="py-2">Penetapan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data['regulasi'] as $r)
                            <tr class="align-top">
                                <td class="max-w-[22rem] py-2 pr-3 font-semibold">{{ $r['nama'] }}@if ($r['catatan'])<span class="block font-normal text-label text-slate-500">{{ $r['catatan'] }}</span>@endif</td>
                                <td class="max-w-[22rem] py-2 pr-3 whitespace-pre-line">{{ $r['justifikasi'] ?? '–' }}</td>
                                <td class="py-2 pr-3">{{ $r['pj'] ?? '–' }}</td>
                                <td class="py-2 pr-3">
                                    <span @class(['badge', 'bg-green-50 text-green-800 ring-green-600/40' => $r['tahap'] === 'DITETAPKAN', 'bg-slate-100 text-slate-700 ring-slate-400/40' => $r['tahap'] !== 'DITETAPKAN'])>{{ $r['tahap_label'] }}</span>
                                    <span class="mt-1 flex gap-0.5" role="img" aria-label="Tahap {{ ($r['tahap_ke'] === false ? 0 : $r['tahap_ke']) + 1 }} dari {{ $jumlahTahap }}">
                                        @for ($k = 0; $k < $jumlahTahap; $k++)<span @class(['h-1.5 w-6 rounded-full', 'bg-aksen' => $r['tahap_ke'] !== false && $k <= $r['tahap_ke'], 'bg-slate-200' => $r['tahap_ke'] === false || $k > $r['tahap_ke']])></span>@endfor
                                    </span>
                                </td>
                                <td class="py-2 pr-3">{{ $r['target_tahun'] ? implode(', ', $r['target_tahun']) : '–' }}</td>
                                <td class="py-2">{{ $r['nomor'] ?? '–' }}@if ($tgl($r['tanggal']))<span class="block text-label text-slate-500">{{ $tgl($r['tanggal']) }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-slate-500">Tidak ada kebutuhan regulasi tercatat di SIRENDA.</p>
        @endif
    </section>
</div>
