@php
    $n = fn ($v, $d = 1) => $v === null ? '–' : number_format($v, $d, ',', '.');
    $delta = fn ($k) => $k['delta']['nilai'] === null ? 'tidak ada pembanding' : (($k['delta']['nilai'] > 0 ? '+' : '').$n($k['delta']['nilai']).($k['delta']['satuan'] === 'pp' ? ' pp' : '%'));
    $status = ['ON_TRACK' => 'On Track', 'BERISIKO' => 'Berisiko', 'TERLAMBAT' => 'Terlambat', 'TANPA_DATA' => 'Tanpa data'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Ringkasan Eksekutif PSN {{ $cutoff->kode }}</title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
    h1 { font-size: 16px; color: #0E2747; margin: 0; }
    h2 { font-size: 12px; color: #0E2747; margin: 16px 0 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
    .sub { color: #475569; margin: 2px 0 0; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #eef2f8; text-align: left; font-weight: bold; }
    th, td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
    .r { text-align: right; }
    .kpi td { border: 1px solid #cbd5e1; width: 25%; }
    .kpi .nilai { font-size: 18px; font-weight: bold; color: #0E2747; }
    .catatan { color: #475569; font-size: 9px; }
    .dua { width: 100%; } .dua > tbody > tr > td { width: 50%; border: 0; padding: 0 6px 0 0; }
    footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #64748b; }
</style>
</head>
<body>
<footer>SIRENDA PSN · Kementerian PPN/Bappenas · dicetak {{ $dibuat->translatedFormat('j F Y H:i') }} oleh {{ $oleh }}</footer>

<h1>Ringkasan Eksekutif Proyek Strategis Nasional</h1>
<p class="sub">Cut-off {{ $cutoff->tanggal_cutoff->translatedFormat('j F Y') }}@if ($sebelumnya) · pembanding {{ $sebelumnya->tanggal_cutoff->translatedFormat('F Y') }}@endif</p>
<p class="sub">Filter: {{ $filter ? collect($filter)->map(fn ($v, $k) => "$k: $v")->implode('; ') : 'semua PSN' }}</p>
<p class="sub">Kelengkapan data rata-rata {{ $n($statusData['kelengkapan_persen']) }}% · {{ $statusData['belum_terverifikasi'] }} dari {{ $statusData['total_psn'] }} PSN belum terverifikasi</p>

<h2>Indikator Utama</h2>
<table class="kpi"><tr>
    @foreach (['K1' => 'PSN', 'K2' => 'Rp triliun', 'K3' => '%', 'K4' => 'PSN'] as $k => $satuan)
        <td><div>{{ $kamus[$k]['nama'] }}</div><div class="nilai">{{ $k === 'K1' || $k === 'K4' ? $n($kpi[$k]['nilai'], 0) : $n($kpi[$k]['nilai']) }} <span style="font-size:10px">{{ $satuan }}</span></div><div class="catatan">Δ {{ $delta($kpi[$k]) }}</div></td>
    @endforeach
</tr></table>
<p class="catatan">K3 dihitung dari {{ $kpi['K3']['cakupan_psn'] }} PSN yang memiliki data progres terlapor. K4 = PSN Terlambat atau berisiko residual ≥ Tinggi.</p>

<h2>Progres Fisik, Anggaran, dan Capaian KP/RO</h2>
<table>
    <tr><th>Progres fisik (realisasi / rencana)</th><td>{{ $n($progres['P2']['realisasi_persen']) }}% / {{ $n($progres['P2']['rencana_persen']) }}% (deviasi {{ $n($progres['P2']['deviasi_pp']) }} pp, {{ $status[$progres['P2']['status']] }})</td></tr>
    <tr><th>Realisasi anggaran {{ $progres['P3']['tahun'] }}</th><td>{{ $progres['P3']['persen'] === null ? '–' : $n($progres['P3']['persen']).'%' }} (Rp {{ $n($progres['P3']['realisasi_triliun'], 2) }} T dari pagu Rp {{ $n($progres['P3']['pagu_triliun'], 2) }} T)</td></tr>
    <tr><th>KP/RO tercapai</th><td>{{ $progres['P4']['tercapai'] }} dari {{ $progres['P4']['total'] }} ({{ $progres['P4']['persen'] === null ? '–' : $n($progres['P4']['persen']).'%' }})</td></tr>
    <tr><th>Status progres PSN</th><td>{{ collect($progres['status_progres'])->map(fn ($s) => $s['label'].' '.$s['jumlah'])->implode(' · ') }}</td></tr>
</table>

<table class="dua"><tr>
<td>
    <h2>Distribusi Klaster</h2>
    <table><tr><th>Klaster</th><th class="r">PSN</th></tr>
        @foreach ($klaster as $r)<tr><td>{{ $r['label'] }}</td><td class="r">{{ $r['jumlah'] }}</td></tr>@endforeach</table>
    <h2>Tahapan Status</h2>
    <table><tr><th>Tahap</th><th class="r">PSN</th></tr>
        @foreach ($tahapan as $r)<tr><td>{{ $r['label'] }}</td><td class="r">{{ $r['jumlah'] }}</td></tr>@endforeach</table>
</td>
<td>
    <h2>10 Provinsi Teratas</h2>
    <table><tr><th>Provinsi</th><th class="r">PSN</th></tr>
        @foreach ($provinsi as $r)<tr><td>{{ $r['label'] }}</td><td class="r">{{ $r['jumlah'] }}</td></tr>@endforeach</table>
    <p class="catatan">PSN multi-lokasi dihitung di setiap provinsinya.</p>
    <h2>Sumber Pendanaan</h2>
    <table><tr><th>Skema</th><th class="r">Rp triliun</th><th class="r">PSN</th></tr>
        @foreach ($dana as $r)<tr><td>{{ $r['label'] }}</td><td class="r">{{ $n($r['investasi_triliun']) }}</td><td class="r">{{ $r['jumlah'] }}</td></tr>@endforeach</table>
</td>
</tr></table>

<h2>KP/RO Critical Path Berisiko</h2>
@if ($roKritis)
    <table><tr><th>KP/RO</th><th>PSN</th><th class="r">Rencana</th><th class="r">Realisasi</th><th class="r">Deviasi</th><th>Status</th></tr>
        @foreach ($roKritis as $r)<tr><td>{{ $r['kegiatan'] }}</td><td>{{ $r['psn'] }}</td><td class="r">{{ $n($r['rencana_persen']) }}%</td><td class="r">{{ $n($r['realisasi_persen']) }}%</td><td class="r">{{ $n($r['deviasi_pp']) }} pp</td><td>{{ $status[$r['status']] }}</td></tr>@endforeach</table>
@else
    <p class="catatan">Tidak ada KP/RO critical path berstatus Berisiko atau Terlambat pada cut-off dan filter ini.</p>
@endif
</body>
</html>
