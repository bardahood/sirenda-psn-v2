{{-- Baris "Pembiayaan (penyerapan)" B2.a/B2.b Form Pengendalian. --}}
<tr><td><b>Pembiayaan (penyerapan)</b></td><td>Rp miliar</td><td class="kanan auto">{{ $n($b2['pembiayaan_akhir']) }}</td><td></td><td></td>
    <td><span class="hint">Tuliskan sumber dana yang terealisasi.</span>
        @if ($b2['pembiayaan_target'] !== null || $b2['pembiayaan_realisasi'] !== null)
            <div class="auto kecil">SIRENDA (KP/RO tahun {{ $cutoff->tanggal_cutoff->year }}): pagu Rp {{ $n($b2['pembiayaan_target']) ?: '-' }} miliar, realisasi Rp {{ $n($b2['pembiayaan_realisasi']) ?: '-' }} miliar.</div>
        @endif
        @if ($a['sumber_dana'])<div class="auto kecil">Sumber dana: {{ implode(', ', $a['sumber_dana']) }}</div>@endif</td></tr>
