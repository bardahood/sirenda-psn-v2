@php($r = $data['ringkasan'])
@php($rp = fn ($v) => $v === null ? '–' : 'Rp '.number_format($v / 1e9, 2, ',', '.').' M')
<div class="grid grid-cols-12 gap-6">
    <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-kurva" x-data="grafikKurvaS(@js($data['kurva_s']), @js(($r['status_progres'] ?? null) === 'TERLAMBAT'))">
        <div class="mb-3 flex items-center gap-2">
            <h3 id="h-kurva" class="judul-panel mr-auto">Kurva S Rencana vs Realisasi Kumulatif {{ $data['tahun'] }}</h3>
            <button type="button" class="ikon-tombol" @click="unduh()" aria-label="Unduh kurva S sebagai PNG">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 19h16"/></svg>
            </button>
        </div>
        <div x-ref="kanvas" class="h-72" role="img" aria-label="Kurva S rencana dan realisasi progres fisik kumulatif per bulan"></div>
        <p class="label mt-2">Titik per bulan berasal dari snapshot cut-off terbit. Penanda merah menunjukkan status Terlambat.</p>
    </section>

    <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-ring">
        <h3 id="h-ring" class="judul-panel mb-3">Ringkasan Deviasi & Status</h3>
        @if ($r)
            <span class="badge-{{ $r['status_progres'] }}"><span class="titik-{{ $r['status_progres'] }}"></span>{{ $labelStatus[$r['status_progres']] }}</span>
            <dl class="mt-4 grid grid-cols-2 gap-4">
                <div><dt class="label">Rencana</dt><dd class="angka text-2xl font-bold text-primer">{{ $r['rencana_persen'] !== null ? number_format($r['rencana_persen'], 1, ',', '.').'%' : '–' }}</dd></div>
                <div><dt class="label">Realisasi</dt><dd class="angka text-2xl font-bold text-primer">{{ $r['realisasi_persen'] !== null ? number_format($r['realisasi_persen'], 1, ',', '.').'%' : '–' }}</dd></div>
                <div><dt class="label">Deviasi</dt><dd class="angka font-semibold">{{ $r['deviasi_pp'] !== null ? number_format($r['deviasi_pp'], 1, ',', '.').' pp' : '–' }}</dd></div>
                <div><dt class="label">KP/RO tercapai</dt><dd class="angka font-semibold">{{ $r['jumlah_ro_tercapai'] }} dari {{ $r['jumlah_ro'] }}</dd></div>
                <div><dt class="label">Pagu</dt><dd class="angka">{{ $rp($r['pagu_rp']) }}</dd></div>
                <div><dt class="label">Realisasi anggaran</dt><dd class="angka">{{ $rp($r['realisasi_anggaran_rp']) }}</dd></div>
            </dl>
            <p class="label mt-4">Pelaporan terakhir: {{ $r['pembaruan_terakhir'] ?? 'belum pernah' }}</p>
        @else
            <p class="text-slate-500">PSN ini belum masuk snapshot cut-off terbit.</p>
        @endif
    </section>

    <section class="kartu col-span-12" aria-labelledby="h-ro">
        <h3 id="h-ro" class="judul-panel mb-3">Target dan Realisasi KP/RO</h3>
        @if ($data['ro'])
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">KP/RO</th><th class="py-2 pr-3 text-right">Target</th><th class="py-2 pr-3 text-right">Realisasi</th><th class="py-2 pr-3 text-right">Deviasi</th><th class="py-2 pr-3">Status</th><th class="py-2">Bukti</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($data['ro'] as $ro)
                            <tr>
                                <td class="max-w-[28rem] py-2 pr-3">{{ $ro['nama'] }} @if ($ro['is_critical_path'])<span class="ml-1 rounded bg-aksen-50 px-1.5 text-label text-aksen-700">Critical path</span>@endif</td>
                                <td class="angka py-2 pr-3 text-right">{{ $ro['target_persen'] !== null ? number_format($ro['target_persen'], 1, ',', '.').'%' : '–' }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ $ro['realisasi_persen'] !== null ? number_format($ro['realisasi_persen'], 1, ',', '.').'%' : '–' }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ $ro['deviasi_pp'] !== null ? number_format($ro['deviasi_pp'], 1, ',', '.').' pp' : '–' }}</td>
                                <td class="py-2 pr-3"><span class="badge-{{ $ro['status_progres'] }}"><span class="titik-{{ $ro['status_progres'] }}"></span>{{ $labelStatus[$ro['status_progres']] }}</span></td>
                                <td class="py-2">
                                    @if ($ro['bukti'])
                                        <a href="{{ \Illuminate\Support\Str::startsWith($ro['bukti'], ['http://', 'https://']) ? $ro['bukti'] : \Illuminate\Support\Facades\Storage::url($ro['bukti']) }}" class="text-aksen-700 hover:underline" target="_blank" rel="noopener">Lihat bukti</a>
                                    @else
                                        <span class="label">Belum ada</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-slate-500">Belum ada KP/RO bertarget pada tahun {{ $data['tahun'] }}.</p>
        @endif
    </section>

    <section class="kartu col-span-12 md:col-span-6" aria-labelledby="h-isu">
        <h3 id="h-isu" class="judul-panel mb-3">Isu Terbuka</h3>
        <ul class="divide-y divide-slate-100">
            @forelse ($data['isu_terbuka'] as $isu)
                <li class="py-2">
                    <p>{{ \Illuminate\Support\Str::limit($isu['uraian'], 220) }}</p>
                    <p class="label mt-1">
                        PIC: {{ $isu['pic_nama'] ?? 'belum ditetapkan' }} ·
                        Tenggat: <span @class(['font-semibold text-red-700' => $isu['lewat_tenggat']])>{{ $isu['tenggat'] ?? 'belum ditetapkan' }}{{ $isu['lewat_tenggat'] ? ' (lewat tenggat)' : '' }}</span>
                    </p>
                </li>
            @empty
                <li class="py-2 text-slate-500">Tidak ada isu terbuka.</li>
            @endforelse
        </ul>
    </section>

    <section class="kartu col-span-12 md:col-span-6" aria-labelledby="h-audit">
        <h3 id="h-audit" class="judul-panel mb-3">Jejak Audit</h3>
        @include('proyek.tab._riwayat', ['riwayat' => $data['jejak_audit'], 'ringkas' => true])
    </section>
</div>
