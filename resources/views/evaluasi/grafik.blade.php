@php
    $status = array_diff_key($cfg['status'], ['BELUM_DITERIMA' => 1]);
    $n = fn ($v) => number_format((int) $v, 0, ',', '.');
    $hari = count($harian);
@endphp
<x-app-layout judul="Grafik Status Harian · RKP {{ $tahun }}" subjudul="Pergerakan Status Akhir PSN per hari dari kertas kerja dan riwayat status">
    <x-slot:aksi>
        <form method="GET" class="flex items-end gap-2">
            <label class="text-isi"><span class="sr-only">Tanggal mulai</span>
                <input type="date" name="mulai" value="{{ $mulai->toDateString() }}" class="rounded-lg border-slate-300 py-1 text-isi focus:border-aksen focus:ring-aksen"></label>
            <button class="tombol-garis py-1.5">Tampilkan {{ $hari }} hari</button>
        </form>
    </x-slot:aksi>

    <div class="mx-auto max-w-[1600px] space-y-5">
        @include('evaluasi._tab')

        <section class="kartu" aria-labelledby="h-gr" x-data="grafikStatusHarian(@js($harian), @js($status))">
            <h2 id="h-gr" class="judul-panel">Pergerakan Status Akhir PSN per Hari</h2>
            <p class="label">{{ $mulai->translatedFormat('j F Y') }} s.d. {{ $mulai->addDays($hari - 1)->translatedFormat('j F Y') }}. Status suatu hari = perubahan terakhir PSN s.d. hari itu; PSN belum dihitung sebelum formnya diterima.</p>
            <div x-ref="kanvas" class="mt-3 h-[340px] w-full" role="img" aria-label="Grafik batang bertumpuk jumlah PSN per status akhir per hari"></div>
        </section>

        <section class="kartu" aria-labelledby="h-tb">
            <h2 id="h-tb" class="judul-panel">Tabel Status Harian</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-500">
                        <tr>
                            <th class="py-2 pr-3 text-left">Tanggal</th>
                            @foreach ($status as $k => $l)<th class="py-2 pr-3 text-right"><span class="badge-EV_{{ $k }}"><span class="titik-EV_{{ $k }}"></span>{{ $l }}</span></th>@endforeach
                            <th class="py-2 text-right">Total PSN</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($harian as $h)
                            <tr>
                                <td class="angka py-1.5 pr-3">{{ \Carbon\Carbon::parse($h['tanggal'])->translatedFormat('D, j M Y') }}</td>
                                @foreach ($status as $k => $l)<td class="angka py-1.5 pr-3 text-right">{{ $n($h['status'][$k] ?? 0) }}</td>@endforeach
                                <td class="angka py-1.5 text-right font-semibold">{{ $n(array_sum($h['status'])) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-slate-300">
                        <tr class="font-semibold">
                            <td class="py-2 pr-3">Posisi saat ini di kertas kerja</td>
                            @foreach ($status as $k => $l)<td class="angka py-2 pr-3 text-right">{{ $n($posisi[$k] ?? 0) }}</td>@endforeach
                            <td class="angka py-2 text-right">{{ $n(collect($status)->keys()->sum(fn ($k) => $posisi[$k] ?? 0)) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <dl class="mt-4 grid grid-cols-1 gap-2 text-isi md:grid-cols-3">
                <div><dt class="label">Tanggal riwayat terakhir</dt><dd class="font-semibold">{{ $terakhir ? \Carbon\Carbon::parse($terakhir)->translatedFormat('j F Y') : '–' }}</dd></div>
                <div><dt class="label">Riwayat hari ini = posisi kertas kerja?</dt>
                    <dd>@if ($cocok)<span class="badge bg-green-50 text-green-800 ring-green-600/40">Cocok</span>@else<span class="badge bg-red-50 text-red-800 ring-red-600/40">Tidak cocok</span>@endif</dd></div>
                <div><dt class="label">Form belum diterima</dt><dd class="font-semibold">{{ $n($posisi['BELUM_DITERIMA'] ?? 0) }} PSN (tidak dihitung di grafik)</dd></div>
            </dl>
            <p class="mt-3 text-[12px] text-slate-500">Status Akhir: Indikasi Status bila sudah diisi; bila belum, Tindak Lanjut; bila belum ada Tindak Lanjut, Proses Pengecekan. Riwayat tercatat otomatis setiap kali status berubah di kertas kerja.</p>
        </section>
    </div>
</x-app-layout>
