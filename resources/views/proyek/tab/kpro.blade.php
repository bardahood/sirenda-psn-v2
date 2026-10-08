<section class="kartu" aria-labelledby="h-kpro">
    <h3 id="h-kpro" class="judul-panel mb-3">Kegiatan Prioritas / Rincian Output</h3>
    @if ($data)
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-isi">
                <thead class="border-b border-slate-200 text-label text-slate-600">
                    <tr><th class="py-2 pr-3">KP/RO</th><th class="py-2 pr-3">Jenis</th><th class="py-2 pr-3">Lokasi</th><th class="py-2 pr-3 text-right">Target akhir</th><th class="py-2 pr-3">Status isian</th><th class="py-2 pr-3 text-right">Deviasi</th><th class="py-2">Status progres</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($data as $k)
                        @foreach ([$k, ...$k['turunan']] as $i => $r)
                            <tr @class(['bg-slate-50/60' => $i > 0])>
                                <td class="max-w-[24rem] py-2 pr-3 {{ $i > 0 ? 'pl-6' : 'font-medium' }}">
                                    @if ($i > 0)<span aria-hidden="true" class="text-slate-400">↳ </span>@endif{{ $r['nama'] }}
                                    @if ($r['is_critical_path'])<span class="ml-1 rounded bg-aksen-50 px-1.5 text-label text-aksen-700">Critical path</span>@endif
                                </td>
                                <td class="py-2 pr-3">{{ $r['jenis'] ?? '–' }}</td>
                                <td class="max-w-[12rem] truncate py-2 pr-3" title="{{ $r['lokasi'] }}">{{ $r['lokasi'] ?? '–' }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ $r['target_akhir'] !== null ? number_format($r['target_akhir'], 2, ',', '.').' '.$r['satuan'] : '–' }}</td>
                                <td class="py-2 pr-3">{{ $r['status_teks'] ?? '–' }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ $r['deviasi_pp'] !== null ? number_format($r['deviasi_pp'], 1, ',', '.').' pp' : '–' }}</td>
                                <td class="py-2">
                                    @if ($r['status_progres'])
                                        <span class="badge-{{ $r['status_progres'] }}"><span class="titik-{{ $r['status_progres'] }}"></span>{{ $labelStatus[$r['status_progres']] }}</span>
                                    @else
                                        <span class="label">Tidak bertarget tahun ini</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-slate-500">Belum ada KP/RO untuk PSN ini.</p>
    @endif
</section>
