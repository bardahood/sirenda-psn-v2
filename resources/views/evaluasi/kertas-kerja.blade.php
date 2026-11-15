@php
    $labelSumber = config('psn_dashboard.label_sumber_psn');
    $kolom = $format->kolom();
    $angkaKunci = fn ($k) => preg_match('/_(target_akhir|target_kumulatif|realisasi_kumulatif)$/', $k);
    $tampil = function ($k, $v) use ($angkaKunci) {
        if ($v === null || $v === '') return '';
        if ($k === 'a9') return 'Rp '.number_format((float) $v, 0, ',', '.');
        if ($angkaKunci($k)) return number_format((float) $v, fmod((float) $v, 1) == 0 ? 0 : 2, ',', '.');
        return (string) $v;
    };
    $lebar = fn ($k) => match (true) {
        $k === 'no' => 'min-w-[4.5rem]',
        in_array($k, ['a6', 'a7', 'd3_tahun'], true) => 'min-w-[6.5rem]',
        in_array($k, ['a1', 'a4', 'a10', 'c2_uraian', 'c2_dukungan', 'catatan', 'b3a_jenis', 'b3b_jenis', 'b4', 'd2', 'c1', 'cek_kurang'], true) || str_ends_with($k, 'keterangan') => 'min-w-[16rem] max-w-[22rem]',
        default => 'min-w-[8.5rem] max-w-[14rem]',
    };
@endphp
<x-app-layout judul="Kertas Kerja Pengendalian PSN · RKP {{ $tahun }}" subjudul="Format ringkas: 1 baris = 1 PSN, terisi dari profil SIRENDA dan isian evaluasi setiap PSN">
    <x-slot:infoJudul>Data profil per {{ $cutoff?->tanggal_cutoff?->translatedFormat('j F Y') ?? '–' }}</x-slot:infoJudul>
    <x-slot:aksi>
        <a href="{{ route('evaluasi.kertas-kerja-xlsx', $filter) }}" class="tombol-primer py-1.5">Unduh kertas kerja (Excel)</a>
    </x-slot:aksi>

    <div class="mx-auto max-w-[1800px] space-y-4">
        @include('evaluasi._tab')

        <section class="kartu !p-4" aria-labelledby="h-kk">
            <form method="GET" class="mb-3 flex flex-wrap items-end gap-3">
                <h2 id="h-kk" class="judul-panel mr-auto">Kertas Kerja <span class="label font-normal">{{ number_format($baris->total(), 0, ',', '.') }} PSN · 92 kolom</span></h2>
                <label class="text-isi"><span class="label block">Cari</span>
                    <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100" class="mt-1 w-48 rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" placeholder="Nama atau kode PSN…"></label>
                @foreach ([['status', 'Status akhir', $cfg['status']], ['kelengkapan', 'Kelengkapan', ['LENGKAP' => 'Lengkap', 'KURANG' => 'Belum lengkap']],
                    ['sumber', 'Sumber', $labelSumber], ['klaster', 'Klaster', $klaster->mapWithKeys(fn ($k) => [$k => $k])->all()]] as [$nama, $label, $pilihan])
                    <label class="text-isi"><span class="label block">{{ $label }}</span>
                        <select name="{{ $nama }}" class="mt-1 max-w-[13rem] rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                            <option value="">Semua</option>
                            @foreach ($pilihan as $nilai => $teks)<option value="{{ $nilai }}" @selected((string) ($filter[$nama] ?? '') === (string) $nilai)>{{ $teks }}</option>@endforeach
                        </select></label>
                @endforeach
                <button class="tombol-garis py-1.5">Terapkan</button>
                <a href="{{ route('evaluasi.kertas-kerja') }}" class="tombol-garis py-1.5">Reset</a>
            </form>

            <p class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[12px] text-slate-600" aria-label="Legenda warna sel">
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-sky-200 bg-sky-50"></span>Data profil SIRENDA (otomatis)</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-slate-300 bg-white"></span>Isian evaluasi PSN</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-amber-300 bg-amber-100"></span>Isian wajib masih kosong</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-5 rounded-sm border border-slate-200 bg-slate-100"></span>Kolom otomatis</span>
                <span><span class="font-semibold text-orange-800">Judul kolom berwarna</span> = wajib (bersyarat)</span>
                <span>Klik nama PSN untuk mengisi atau mengubah.</span>
            </p>

            <div class="max-h-[70vh] overflow-auto rounded-lg border border-slate-200">
                <table class="border-separate border-spacing-0 text-left text-[12px]">
                    <thead class="sticky top-0 z-20">
                        <tr>
                            @foreach ($format->grup() as $gi => [$nama, $n])
                                <th colspan="{{ $n }}" @class(['border-b border-r border-slate-200 bg-[#e8eef7] px-2 py-1.5 text-[11.5px] font-bold text-slate-800', 'sticky left-0 z-30' => $gi === 0, 'sticky left-[13rem] z-30 shadow-[2px_0_0_#e2e8f0]' => $gi === 1])>{{ trim($nama) }}</th>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach ($kolom as $ki => $k)
                                <th @class(['border-b border-r border-slate-200 bg-[#f3f6fb] px-2 py-1.5 align-bottom text-[11px] font-semibold', $lebar($k['kunci']),
                                    'text-orange-800' => $k['wajib'], 'text-slate-600' => ! $k['wajib'], 'sticky z-30' => $ki < 3,
                                    'left-0' => $ki === 0, 'left-[4.5rem]' => $ki === 1, 'left-[13rem]' => $ki === 2])>{{ $k['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($baris as $b)
                            @foreach ($kolom as $ki => $k)
                                @if ($ki === 0)<tr class="align-top">@endif
                                @php($kunci = $k['kunci'])
                                @php($v = $b['nilai'][$kunci] ?? null)
                                @php($asal = $b['asal'][$kunci] ?? 'ISIAN')
                                @php($kurang = in_array($kunci, $b['kurang'], true))
                                <td @class(['border-b border-r border-slate-100 px-2 py-1.5', $lebar($kunci),
                                    'bg-amber-100' => $kurang, 'bg-sky-50' => ! $kurang && $asal === 'PROFIL' && $ki >= 3, 'bg-slate-100' => ! $kurang && $asal === 'OTOMATIS' && $ki >= 3,
                                    'sticky z-10 bg-white' => $ki < 3, 'left-0' => $ki === 0, 'left-[4.5rem]' => $ki === 1, 'left-[13rem] shadow-[2px_0_0_#e2e8f0]' => $ki === 2,
                                    'text-right angka' => $angkaKunci($kunci) || in_array($kunci, ['no', 'a9'], true)])>
                                    @if ($kunci === 'a1')
                                        <a href="{{ route('evaluasi.show', $b['r']['psn_id']) }}#h-isian" class="font-semibold text-slate-900 hover:underline">{{ $v }}</a>
                                    @elseif ($kunci === 'status_akhir')
                                        <span class="badge-EV_{{ $b['r']['status_akhir'] }}"><span class="titik-EV_{{ $b['r']['status_akhir'] }}"></span>{{ $v }}</span>
                                    @else
                                        <span class="line-clamp-5 whitespace-pre-line" title="{{ is_string($v) && strlen($v) > 120 ? $v : '' }}">{{ $tampil($kunci, $v) }}</span>
                                    @endif
                                </td>
                                @if ($loop->last)</tr>@endif
                            @endforeach
                        @empty
                            <tr><td colspan="{{ count($kolom) }}" class="px-3 py-6 text-center text-slate-500">Tidak ada PSN yang sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $baris->links() }}</div>
        </section>
    </div>
</x-app-layout>
