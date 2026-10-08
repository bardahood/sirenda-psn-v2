@php
    $cfg = config('psn_dashboard');
    $kamus = \App\Support\Dashboard\KamusIndikator::DAFTAR;
@endphp
<x-app-layout judul="Kamus Indikator">
    <div class="mx-auto max-w-5xl space-y-6">
        <section class="kartu" aria-labelledby="h-ind">
            <h2 id="h-ind" class="judul-panel mb-3">Definisi Indikator</h2>
            <table class="w-full text-left text-isi">
                <thead class="border-b border-slate-200 text-label text-slate-600"><tr><th class="w-24 py-2 pr-3">Kode</th><th class="py-2 pr-3">Indikator & rumus</th><th class="py-2">Sumber</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($kamus as $kode => $k)
                        <tr class="align-top"><td class="py-2 pr-3 font-semibold">{{ str_replace('_', ' ', $kode) }}</td><td class="py-2 pr-3"><b>{{ $k['nama'] }}</b><br>{{ $k['rumus'] }}</td><td class="py-2 text-slate-600">{{ $k['sumber'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </section>
        <section class="kartu" aria-labelledby="h-ambang">
            <h2 id="h-ambang" class="judul-panel mb-3">Ambang & Bobot yang Berlaku</h2>
            <p class="label mb-3">Nilai dibaca langsung dari konfigurasi aplikasi (<code>config/psn_dashboard.php</code>).</p>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div><dt class="font-semibold">Status progres</dt><dd>
                    <span class="badge-ON_TRACK"><span class="titik-ON_TRACK"></span>On Track</span> deviasi &gt; {{ $cfg['status_progres']['on_track_min_deviasi'] }} pp<br>
                    <span class="badge-BERISIKO"><span class="titik-BERISIKO"></span>Berisiko</span> {{ $cfg['status_progres']['on_track_min_deviasi'] }} s.d. {{ $cfg['status_progres']['terlambat_max_deviasi'] }} pp<br>
                    <span class="badge-TERLAMBAT"><span class="titik-TERLAMBAT"></span>Terlambat</span> &lt; {{ $cfg['status_progres']['terlambat_max_deviasi'] }} pp<br>
                    <span class="badge-TANPA_DATA"><span class="titik-TANPA_DATA"></span>Tanpa data</span> tidak ada pembaruan &gt; {{ $cfg['status_progres']['tanpa_data_hari'] }} hari</dd></div>
                <div><dt class="font-semibold">Level risiko (kemungkinan × dampak)</dt><dd>@foreach ($cfg['risiko']['level'] as $l => [$a, $b]){{ $l }}: {{ $a }}–{{ $b }}<br>@endforeach Risiko kritis mulai level {{ $cfg['risiko']['kritis_min_level'] }}</dd></div>
                <div><dt class="font-semibold">Penilaian usulan</dt><dd>@foreach ($cfg['penilaian']['bobot'] as $k => $b){{ ucfirst(strtolower($k)) }} {{ $b * 100 }}% · @endforeach<br>
                    Ambang Direkomendasikan: {{ $cfg['penilaian']['ambang']['direkomendasikan'] ?? 'belum ditetapkan' }}; Dipertimbangkan: {{ $cfg['penilaian']['ambang']['dipertimbangkan'] ?? 'belum ditetapkan' }}<br>Gate KU1–KU3: satu "Tidak" = Ditolak</dd></div>
                <div><dt class="font-semibold">Lain-lain</dt><dd>K3: metode {{ $cfg['k3_metode'] }} · P1: {{ $cfg['p1_top_n'] }} klaster teratas + Lainnya<br>
                    Skema dana: @foreach ($cfg['skema_dana'] as $k => $s){{ $k }}→{{ $s }} @endforeach<br>Investasi anomali: di luar Rp{{ number_format($cfg['investasi']['min_rp'], 0, ',', '.') }} – Rp{{ number_format($cfg['investasi']['max_rp'] / 1e12, 0, ',', '.') }} T</dd></div>
            </dl>
        </section>
    </div>
</x-app-layout>
