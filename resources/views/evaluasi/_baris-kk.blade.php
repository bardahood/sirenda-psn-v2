{{-- Satu baris kertas kerja. Variabel: $b (FormatRingkas::semua), $format, $kolom, $editor. Dipakai juga oleh respons isian sel. --}}
@php
    $u = auth()->user();
    $jenisBaris = $b['r']['jenis'];
    $bisa = fn (array $ed) => $u->can("evaluasi.{$ed['izin']}") && (! $ed['jenis'] || $ed['jenis'] === $jenisBaris);
    $jangkar = ['b3' => 'b3', 'c2' => 'c2', 'lampiran' => 'lampiran'];
@endphp
<tr class="align-top" data-psn="{{ $b['r']['psn_id'] }}" data-no="{{ $b['nilai']['no'] }}">
    @foreach ($kolom as $ki => $k)
        @php
            $kunci = $k['kunci'];
            $v = $b['nilai'][$kunci] ?? null;
            $asal = $b['asal'][$kunci] ?? 'ISIAN';
            $kurang = in_array($kunci, $b['kurang'], true);
            $ed = $editor[$kunci] ?? null;
            $dapat = $ed && $bisa($ed);
        @endphp
        <td @class(['group relative border-b border-r border-slate-100 px-2 py-1.5', $format::lebar($kunci),
            'bg-amber-100' => $kurang, 'bg-sky-50' => ! $kurang && $asal === 'PROFIL' && $ki >= 3, 'bg-slate-100' => ! $kurang && $asal === 'OTOMATIS' && $ki >= 3,
            'sticky z-10 bg-white' => $ki < 3, 'left-0' => $ki === 0, 'left-[4.5rem]' => $ki === 1, 'left-[13rem] shadow-[2px_0_0_#e2e8f0]' => $ki === 2,
            'text-right angka' => $format::angka($kunci), 'cursor-pointer hover:outline hover:outline-2 hover:-outline-offset-2 hover:outline-aksen/60 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-aksen' => $dapat])
            @if ($dapat)
                data-kunci="{{ $kunci }}" data-edit="1" tabindex="0" title="Klik untuk mengisi"
                @if ($ed['tipe'] === 'rincian') data-tautan="{{ route('evaluasi.show', $b['r']['psn_id']) }}#{{ $jangkar[$ed['opsi']] }}"
                @else data-nilai="{{ json_encode($b['mentah'][$kunci] ?? null) }}" @if ($kunci === 'c1') data-lain="{{ json_encode($b['mentah']['c1_lainnya'] ?? null) }}" @endif @endif
            @endif>
            @if ($kunci === 'a1')
                <a href="{{ route('evaluasi.show', $b['r']['psn_id']) }}#h-isian" class="font-semibold text-slate-900 hover:underline">{{ $v }}</a>
            @elseif ($kunci === 'status_akhir')
                <span class="badge-EV_{{ $b['r']['status_akhir'] }}"><span class="titik-EV_{{ $b['r']['status_akhir'] }}"></span>{{ $v }}</span>
            @else
                <span class="line-clamp-5 whitespace-pre-line" title="{{ is_string($v) && strlen($v) > 120 ? $v : '' }}">{{ $format::tampil($kunci, $v) }}</span>
                @if ($dapat && blank($v))<span class="text-[11px] text-slate-400 group-hover:text-aksen">{{ $ed['tipe'] === 'rincian' ? 'isi di formulir' : 'klik untuk isi' }}</span>@endif
            @endif
        </td>
    @endforeach
</tr>
