@php
    $kelasLevel = fn (?string $l) => [
        'Rendah' => 'badge bg-green-50 text-green-800 ring-green-600/40', 'Sedang' => 'badge bg-amber-50 text-amber-900 ring-amber-600/40',
        'Tinggi' => 'badge bg-orange-50 text-orange-900 ring-orange-600/40', 'Sangat Tinggi' => 'badge bg-red-50 text-red-800 ring-red-600/40',
    ][$l] ?? 'badge bg-slate-100 text-slate-700 ring-slate-400/40';
    $teksLevel = fn (?array $x) => ! $x || ! $x['level'] ? '–' : $x['level'].($x['kemungkinan'] && $x['dampak'] ? ' ('.$x['kemungkinan'].'×'.$x['dampak'].')' : '');
    $tgl = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('j M Y') : '–';
    $statusIsu = config('psn_dashboard.pengisian.status_isu');
    $statusPerlakuan = config('psn_dashboard.pengisian.status_perlakuan');
    $ri = $data['isu_ringkas'];
@endphp
<div class="grid grid-cols-12 gap-6">
    <section class="kartu col-span-12 lg:col-span-7" aria-labelledby="h-ring-risiko">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h3 id="h-ring-risiko" class="judul-panel mr-auto">Ringkasan Risiko <span class="label font-normal">({{ $data['jumlah_risiko'] }} risiko)</span></h3>
            <a href="{{ route('risiko').'?psn='.$psn->id }}" class="label text-aksen-700 hover:underline">Buka di menu Risiko &amp; Isu →</a>
        </div>
        <table class="w-full text-left text-isi">
            <thead class="border-b border-slate-200 text-label text-slate-600"><tr><th class="py-1 pr-3">Level</th><th class="py-1 pr-3 text-right">Residual harapan</th><th class="py-1 text-right">Aktual (pemantauan terakhir)</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($data['per_level'] as $l)
                    <tr><td class="py-1.5 pr-3"><span class="{{ $kelasLevel($l['level']) }}">{{ $l['level'] }}</span></td><td class="angka py-1.5 pr-3 text-right">{{ $l['harapan'] }}</td><td class="angka py-1.5 text-right">{{ $l['aktual'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <p class="label mt-2">Risiko kritis = level residual ≥ {{ $data['kritis_min'] }}. Level aktual berasal dari pemantauan triwulanan di menu Pengisian; bila belum ada, dipakai level harapan.</p>
    </section>

    <section class="kartu col-span-12 lg:col-span-5" aria-labelledby="h-ring-isu">
        <h3 id="h-ring-isu" class="judul-panel mb-3">Ringkasan Isu</h3>
        <dl class="grid grid-cols-2 gap-4">
            <div><dt class="label">Isu terbuka</dt><dd class="angka text-2xl font-bold text-primer">{{ $ri['terbuka'] }}</dd></div>
            <div><dt class="label">Lewat tenggat</dt><dd @class(['angka text-2xl font-bold', 'text-red-700' => $ri['lewat_tenggat'], 'text-primer' => ! $ri['lewat_tenggat']])>{{ $ri['lewat_tenggat'] }}</dd></div>
            <div><dt class="label">Menghambat jalur kritis</dt><dd class="angka font-semibold">{{ $ri['jalur_kritis'] }}</dd></div>
            <div><dt class="label">Selesai</dt><dd class="angka font-semibold">{{ $ri['selesai'] }}</dd></div>
        </dl>
        @can('detail.input')<a href="{{ route('pengisian.index').'?q='.urlencode($psn->kode_psn ?? $psn->nama) }}" class="tombol-garis mt-4 py-1.5">Perbarui pemantauan risiko &amp; isu di Pengisian</a>@endcan
    </section>

    <section class="kartu col-span-12" aria-labelledby="h-register">
        <h3 id="h-register" class="judul-panel mb-3">Register Risiko</h3>
        @if ($data['risiko'])
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">Risiko</th><th class="py-2 pr-3">Kategori</th><th class="py-2 pr-3">Inheren</th><th class="py-2 pr-3">Residual harapan</th>
                            <th class="py-2 pr-3">Aktual</th><th class="py-2 pr-3">Rencana &amp; perlakuan</th><th class="py-2">Tahun perlakuan</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data['risiko'] as $r)
                            <tr class="align-top">
                                <td class="min-w-[14rem] max-w-[24rem] py-2 pr-3">
                                    <span class="whitespace-pre-line font-semibold">{{ $r['uraian'] }}</span>
                                    @if ($r['jumlah'] > 1)<span class="badge ml-1 bg-slate-100 text-slate-700 ring-slate-400/40" title="Entri dengan isi sama digabung">{{ $r['jumlah'] }} entri serupa</span>@endif
                                    @if ($r['titik_kritis'])<span class="badge ml-1 bg-red-50 text-red-800 ring-red-600/40">Titik kritis</span>@endif
                                    @if ($r['kegiatan'])<span class="block text-label text-slate-500">KP/RO: {{ $r['kegiatan'] }}@if ($r['critical_path']) · critical path @endif</span>@endif
                                    @if ($r['pj'])<span class="block text-label text-slate-500">PJ: {{ $r['pj'] }}</span>@endif
                                </td>
                                <td class="max-w-[14rem] py-2 pr-3 text-label text-slate-600">{{ $r['kategori'] ?? '–' }}</td>
                                <td class="whitespace-nowrap py-2 pr-3"><span class="{{ $kelasLevel($r['awal']['level']) }}">{{ $teksLevel($r['awal']) }}</span></td>
                                <td class="whitespace-nowrap py-2 pr-3"><span class="{{ $kelasLevel($r['harapan']['level']) }}">{{ $teksLevel($r['harapan']) }}</span></td>
                                <td class="whitespace-nowrap py-2 pr-3">
                                    @if ($r['aktual'])<span class="{{ $kelasLevel($r['aktual']['level']) }}">{{ $teksLevel($r['aktual']) }}</span>
                                        <span class="block text-label text-slate-500">{{ $tgl($r['aktual']['tanggal']) }}{{ $r['aktual']['status'] ? ' · '.($statusPerlakuan[$r['aktual']['status']] ?? $r['aktual']['status']) : '' }}{{ $r['aktual']['progres'] !== null ? ' · perlakuan '.rtrim(rtrim(number_format($r['aktual']['progres'], 1, ',', '.'), '0'), ',').'%' : '' }}</span>
                                    @else<span class="text-label text-slate-500">Belum dipantau</span>@endif
                                </td>
                                <td class="max-w-[22rem] py-2 pr-3">
                                    @if ($r['rencana'])<span class="whitespace-pre-line">{{ $r['rencana'] }}</span>@endif
                                    @if ($r['perlakuan'])
                                        <ul class="mt-1 list-inside list-disc text-label text-slate-600">@foreach ($r['perlakuan'] as $p)<li>{{ $p['uraian'] }}@if ($p['instansi']) — {{ $p['instansi'] }}@endif</li>@endforeach</ul>
                                    @endif
                                    @if (! $r['rencana'] && ! $r['perlakuan'])<span class="text-slate-500">–</span>@endif
                                </td>
                                <td class="py-2">{{ $r['tahun'] ? implode(', ', $r['tahun']) : '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="label mt-2">Urutan: level aktual/harapan tertinggi lebih dulu. Inheren = sebelum perlakuan; residual harapan = target setelah perlakuan; angka dalam kurung = kemungkinan × dampak.</p>
        @else
            <p class="text-slate-500">Belum ada risiko tercatat di SIRENDA.</p>
        @endif
    </section>

    <section class="kartu col-span-12" aria-labelledby="h-isu">
        <h3 id="h-isu" class="judul-panel mb-3">Isu &amp; Debottlenecking <span class="label font-normal">({{ count($data['isu']) }})</span></h3>
        @if ($data['isu'])
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">Isu</th><th class="py-2 pr-3">Kebutuhan dukungan</th><th class="py-2 pr-3">PIC</th><th class="py-2 pr-3">Tenggat</th><th class="py-2 pr-3">Status</th><th class="py-2">Tindak lanjut</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data['isu'] as $i)
                            <tr class="align-top">
                                <td class="max-w-[22rem] py-2 pr-3"><span class="whitespace-pre-line">{{ $i['uraian'] }}</span>
                                    @if ($i['kegiatan'])<span class="block text-label text-slate-500">KP/RO: {{ $i['kegiatan'] }}</span>@endif
                                    @if ($i['critical_path'] && $i['status'] !== 'SELESAI')<span class="badge mt-1 bg-orange-50 text-orange-900 ring-orange-600/40">Menghambat jalur kritis</span>@endif</td>
                                <td class="max-w-[18rem] py-2 pr-3 whitespace-pre-line">{{ $i['kebutuhan_dukungan'] ?? '–' }}</td>
                                <td class="py-2 pr-3">{{ $i['pic'] ?? '–' }}</td>
                                <td class="whitespace-nowrap py-2 pr-3">{{ $tgl($i['tenggat']) }}@if ($i['lewat_tenggat'])<span class="badge mt-1 block w-fit bg-red-50 text-red-800 ring-red-600/40">Lewat tenggat</span>@endif</td>
                                <td class="whitespace-nowrap py-2 pr-3"><span @class(['badge', 'bg-green-50 text-green-800 ring-green-600/40' => $i['status'] === 'SELESAI', 'bg-amber-50 text-amber-900 ring-amber-600/40' => $i['status'] !== 'SELESAI'])>{{ $statusIsu[$i['status']] ?? $i['status'] }}</span>
                                    @if ($i['tanggal_selesai'])<span class="block text-label text-slate-500">{{ $tgl($i['tanggal_selesai']) }}</span>@endif</td>
                                <td class="max-w-[18rem] py-2 whitespace-pre-line">{{ $i['tindak_lanjut'] ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-slate-500">Belum ada isu tercatat.</p>
        @endif
    </section>
</div>
