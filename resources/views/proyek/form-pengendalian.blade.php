@use('App\Services\FormPengendalianService')
@php
    $n = fn ($v, $d = 2) => $v === null ? '' : number_format($v, $d, ',', '.');
    $kotak = fn (bool $c = false) => $c ? '☒' : '☐';
    $titik = '.....................................................................................................................';
    $tw = $triwulan ? "Triwulan {$triwulan} {$cutoff->tanggal_cutoff->year}" : null;
    $posisi = $cutoff ? $cutoff->tanggal_cutoff->translatedFormat('j F Y') : null;
    $sdTw = $cutoff ? ($akhir_triwulan ? "s.d. TW {$triwulan} {$cutoff->tanggal_cutoff->year}" : 's.d. '.$cutoff->tanggal_cutoff->translatedFormat('F Y')) : 's.d. periode laporan';
    $fisik = $jenis !== 'nonfisik';
    $nonfisik = $jenis !== 'fisik';
    $logo = $pdf ? public_path('img/logo-bappenas.jpg') : asset('img/logo-bappenas.jpg');
    $pilihan = fn (array $opsi) => collect($opsi)->map(fn ($o) => '☐ '.e($o))->implode('&nbsp;&nbsp; ');
    $status3 = $pilihan(['Selesai', 'Proses', 'Belum']);
    $milestoneFisik = ['Studi kelayakan (Pra-FS / FS; OBC untuk KPBU)', 'Kesesuaian kegiatan pemanfaatan ruang (KKPR)', 'Persetujuan lingkungan (AMDAL / UKL-UPL)',
        'Penetapan lokasi pengadaan tanah (Penlok)', 'Desain teknis (Basic Design / DED)*', 'Kepastian skema dan sumber pendanaan (FBC / penjaminan untuk KPBU)',
        'Pengadaan badan usaha / kontraktor', 'Penandatanganan kontrak / PPJT / financial close (FID untuk migas dan swasta)', 'Dimulainya konstruksi (groundbreaking)',
        'Milestone lain sesuai sektor: ........................'];
    $milestoneNonFisik = ['Kajian / desain program (naskah kebijakan, peta jalan)', 'Penetapan regulasi dasar (Perpres / PP / Permen)', 'Petunjuk teknis / NSPK pelaksanaan',
        'Penetapan kelembagaan dan pelaksana (K/L, BUMN, satgas, mitra)', 'Penetapan sasaran dan basis data penerima (mis. DTSEN)',
        'Kepastian skema dan sumber pendanaan (alokasi anggaran / DIPA)', 'Pengadaan barang/jasa / perjanjian kerja sama mitra', 'Sistem informasi, monitoring, dan pelaporan',
        'Uji coba / pilot', 'Peluncuran / pelaksanaan penuh program', 'Milestone lain sesuai program: ........................'];
    $tipologi = ['Pengadaan tanah dan legalitas lahan', 'Perizinan dan regulasi', 'Tata ruang, kawasan hutan, dan lingkungan', 'Pendanaan, pembiayaan, dan investasi',
        'Teknis, konstruksi, dan pelaksanaan', 'Pengadaan barang/jasa dan kontrak', 'Kelembagaan, koordinasi, dan kerja sama', 'Sosial dan kemasyarakatan',
        'Infrastruktur pendukung, utilitas, dan konektivitas', 'Operasional dan optimalisasi pemanfaatan', 'Lainnya: ..............................'];
    $fasilitas = ['Perizinan usaha', 'Pembiayaan proyek', 'Percepatan pengadaan barang/jasa', 'Rencana tata ruang dan ruang laut', 'Penyiapan proyek',
        'Penanganan dampak sosial', 'Perencanaan dalam rencana induk sektor', 'Penyelesaian masalah hukum', 'Pendanaan pengadaan lahan melalui LMAN'];
    $isuKosong = max(config('psn_dashboard.form_pengendalian.baris_isu_kosong'), 3 - count($isu));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Form Pengendalian PSN · {{ $a['nama'] }}</title>
@unless ($pdf) @vite(['resources/js/app.js']) @endunless
<style>
    @page { size: A4 portrait; margin: 18mm 16mm 16mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9.5px; color: #111827; line-height: 1.35; margin: 0; }
    .lembar { background: #fff; }
    table { width: 100%; border-collapse: collapse; }
    .t td, .t th { border: 0.6px solid #6b7280; padding: 4px 5px; vertical-align: top; }
    .t th { background: #e5e7eb; font-weight: bold; text-align: left; }
    .no { width: 26px; text-align: center; font-weight: bold; }
    .komponen { width: 34%; font-weight: bold; }
    .bagian { background: #0E2747; color: #fff; font-weight: bold; padding: 5px 7px; margin: 12px 0 4px; font-size: 10.5px; }
    h3 { font-size: 10px; margin: 10px 0 3px; }
    .u { text-decoration: underline; font-weight: bold; margin: 8px 0 3px; }
    .petunjuk { border: 0.8px solid #6b7280; padding: 6px 8px; margin: 8px 0; }
    .petunjuk p { margin: 2px 0; }
    .hint { color: #6b7280; font-style: italic; font-size: 8.5px; }
    .auto { color: #0b4a8b; }
    .kecil { font-size: 8.5px; }
    .titik { color: #9ca3af; }
    .tengah { text-align: center; }
    .kanan { text-align: right; }
    .baris-kosong td { height: 22px; }
    .ttd td { border: 0.6px solid #6b7280; padding: 6px 8px; }
    .judul { text-align: center; }
    .judul h1 { font-size: 12px; margin: 4px 0 2px; }
    .judul h2 { font-size: 11.5px; margin: 2px 0; }
    .judul p { margin: 2px 0; }
    footer { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7.5px; color: #6b7280; text-align: center; }
    .pecah { page-break-before: always; }
    .alat { font-family: system-ui, Arial, sans-serif; background: #0E2747; color: #fff; padding: 10px 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 13px; }
    .alat a, .alat button { background: #fff; color: #0E2747; border: 0; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; text-decoration: none; cursor: pointer; }
    .alat a.garis { background: transparent; color: #fff; border: 1px solid #94a3b8; }
    .alat select { padding: 5px 6px; border-radius: 6px; font-size: 13px; }
    .alat form { display: flex; gap: 8px; align-items: center; margin: 0; }
    .t table td { border: 0; padding: 1px 4px 1px 0; }
    .bagian, h3, .u { page-break-after: avoid; }
    tr { page-break-inside: avoid; }
</style>
@unless ($pdf)
{{-- Hanya untuk tampilan peramban (dompdf juga membaca @media screen). --}}
<style>
    @media screen { body { background: #f1f5f9; } .lembar { max-width: 190mm; padding: 16mm 14mm; margin: 16px auto; box-shadow: 0 1px 4px rgba(0,0,0,.12); } footer { display: none; } }
    @media print { .alat { display: none; } .lembar { max-width: none; } }
</style>
@endunless
</head>
<body>
@unless ($pdf)
    <div class="alat" role="toolbar" aria-label="Pilihan cetak">
        <a href="{{ route('proyek.show', $psn) }}" class="garis">← Detail proyek</a>
        <form method="GET" action="{{ route('proyek.form-pengendalian', $psn) }}">
            <label>Periode
                <select name="periode">
                    @foreach ($periodeOpsi as $p)<option value="{{ $p->kode }}" @selected($cutoff?->kode === $p->kode)>{{ $p->tanggal_cutoff->translatedFormat('F Y') }}</option>@endforeach
                </select></label>
            <label>Jenis PSN
                <select name="jenis">
                    <option value="">Belum ditentukan (cetak kedua versi)</option>
                    <option value="fisik" @selected($jenis === 'fisik')>Fisik</option>
                    <option value="nonfisik" @selected($jenis === 'nonfisik')>Non-Fisik</option>
                </select></label>
            <button type="submit">Terapkan</button>
        </form>
        <span style="flex:1"></span>
        <button type="button" x-data @click="window.print()">Cetak</button>
        <a href="{{ route('proyek.form-pengendalian', ['psn' => $psn, 'periode' => $cutoff?->kode, 'jenis' => $jenis, 'format' => 'docx']) }}" title="Form Word resmi yang sudah terisi; dapat dilengkapi PIC di Microsoft Word">Unduh Word</a>
        <a href="{{ route('proyek.form-pengendalian', ['psn' => $psn, 'periode' => $cutoff?->kode, 'jenis' => $jenis, 'format' => 'pdf']) }}">Unduh PDF</a>
    </div>
@endunless

<footer>Form Pengendalian PSN · {{ $a['nama'] }} · dicetak dari SIRENDA PSN {{ $dicetak->translatedFormat('j F Y H:i') }} oleh {{ $oleh }}</footer>

<div class="lembar">
    <table><tr>
        <td style="width:110px; vertical-align:middle"><img src="{{ $logo }}" alt="Logo Bappenas" style="width:108px"></td>
        <td class="judul" style="vertical-align:middle">
            <h1>KEMENTERIAN PERENCANAAN PEMBANGUNAN NASIONAL / BAPPENAS</h1>
            <h2>FORM PENGENDALIAN PROYEK STRATEGIS NASIONAL (PSN) TAHUN {{ $cutoff?->tanggal_cutoff->year ?? now()->year }}</h2>
            <p>Evaluasi Kinerja PSN Berjalan – Data Kumulatif {{ $sdTw }}@if ($posisi) (posisi {{ $posisi }})@endif</p>
        </td>
        <td style="width:110px"></td>
    </tr></table>

    <div class="petunjuk">
        <p><b>Petunjuk Pengisian:</b></p>
        <p>1. Form ini adalah versi ringkas Project Profile SIRENDA dan berlaku untuk PSN fisik (output berupa aset terbangun) maupun non-fisik (output berupa layanan, program, kelembagaan, regulasi, atau data). Isian dipakai untuk Laporan Evaluasi PSN dan pemutakhiran Daftar PSN RKP tahun berikutnya.</p>
        <p>2. Seluruh angka progres adalah <b>kumulatif {{ $posisi ? "s.d. {$posisi}" : 's.d. periode laporan' }}</b>. Untuk PSN multi-seksi/multi-lokasi/multi-wilayah, isi nilai agregat; rincian per seksi atau wilayah boleh dituliskan di bagian keterangan.</p>
        <p>3. Bagian B2 dan B3 memiliki dua versi isian. Isi hanya versi yang sesuai dengan jenis PSN pada butir A12 (Fisik atau Non-Fisik).</p>
        <p>4. Informasi lain terkait PSN yang belum tercantum dalam form ini dapat dilampirkan (termasuk dokumen, foto, dan video) dalam bentuk tautan atau file softcopy. Daftar lampiran dituliskan pada halaman <b>Lampiran</b> di bagian akhir form.</p>
        @if ($batas_kembali)<p>5. Form dikembalikan paling lambat <b>{{ $batas_kembali }}</b>.</p>@endif
        <p class="auto">Teks berwarna biru telah diisi dari data SIRENDA PSN{{ $cutoff ? ' (cut-off '.$posisi.')' : '' }}. Mohon diperiksa, dikoreksi bila tidak sesuai, dan dilengkapi.</p>
    </div>

    {{-- BAGIAN A --}}
    <div class="bagian">BAGIAN A. GAMBARAN UMUM</div>
    <table class="t">
        <tr><th class="no">No.</th><th class="komponen">Komponen</th><th>Data</th></tr>
        <tr><td class="no">A1</td><td class="komponen">Nama PSN</td><td class="auto">{{ $a['nama'] }}@if ($a['kode']) <span class="kecil">({{ $a['kode'] }})</span>@endif</td></tr>
        <tr><td class="no">A2</td><td class="komponen">Klaster PSN</td><td class="auto">{{ $a['klaster'] }}</td></tr>
        <tr><td class="no">A3</td><td class="komponen">Lokasi (nasional atau provinsi/kabupaten/kota)</td><td class="auto">{{ implode('; ', $a['lokasi']) }}</td></tr>
        <tr><td class="no">A4</td><td class="komponen">Penanggung Jawab, Pengelola, dan Pelaksana</td>
            <td class="auto">@foreach ($a['kelembagaan'] as $peran => $nama)<div><b>{{ $peran }}:</b> {{ implode('; ', $nama) }}</div>@endforeach</td></tr>
        <tr><td class="no">A5</td><td class="komponen">Dasar penetapan PSN / regulasi pendukung proyek atau program</td><td class="auto">{!! nl2br(e($a['dasar_penetapan'] ?? '')) !!}</td></tr>
        <tr><td class="no">A6</td><td class="komponen">Tahun pertama kali ditetapkan sebagai PSN</td><td class="auto">{{ $a['tahun_terbit'] }}</td></tr>
        <tr><td class="no">A7</td><td class="komponen">Target tahun proyek / program selesai</td><td class="auto">{{ $a['tahun_selesai'] }}</td></tr>
        <tr><td class="no">A8</td><td class="komponen">Sumber pendanaan (APBN / APBD / BUMN / Badan Usaha-Swasta / Danantara / Lainnya)</td><td class="auto">{{ implode(', ', $a['sumber_dana']) }}</td></tr>
        <tr><td class="no">A9</td><td class="komponen">Total kebutuhan pendanaan / nilai investasi / anggaran program</td>
            <td>Rp. <span class="auto">{{ $a['nilai_investasi_rp'] !== null ? $n($a['nilai_investasi_rp'], 0) : '' }}</span>@if ($a['investasi_anomali'])<div class="hint">Nilai di SIRENDA ditandai perlu verifikasi; mohon dikoreksi.</div>@endif</td></tr>
        <tr><td class="no">A10</td><td class="komponen">Deskripsi ringkas proyek / program (maksud / tujuan umum)</td><td class="auto">{!! nl2br(e($a['deskripsi'] ?? '')) !!}</td></tr>
        <tr><td class="no">A11</td><td class="komponen">Output akhir <span class="hint">(misal fisik: 100 km, 220 MW, 1.000 ha; non-fisik: jumlah penerima manfaat, unit layanan, lembaga terbentuk)</span></td><td class="auto">{!! nl2br(e($a['output'] ?? '')) !!}</td></tr>
        <tr><td class="no">A12</td><td class="komponen">Jenis PSN</td>
            <td><div>{{ $kotak($jenis === 'fisik') }} <b>Fisik</b> (output berupa aset terbangun) → isi B2.a dan B3.a</div>
                <div>{{ $kotak($jenis === 'nonfisik') }} <b>Non-Fisik</b> (output berupa layanan, program, kelembagaan, regulasi, atau data) → isi B2.b dan B3.b</div></td></tr>
    </table>

    {{-- BAGIAN B --}}
    <div class="bagian pecah">BAGIAN B. TAHAPAN DAN PROGRES {{ strtoupper($sdTw) }}</div>
    <table class="t">
        <tr><th class="no">No.</th><th class="komponen">Komponen</th><th>Posisi {{ $posisi }}</th></tr>
        <tr><td class="no">B1</td><td class="komponen">Tahapan proyek / program<div class="hint">Pilih salah satu</div></td>
            <td>@foreach (FormPengendalianService::TAHAPAN as $kode => [$label, $uraian])
                    <div @class(['auto' => $tahapan === $kode])>{{ $kotak($tahapan === $kode) }} {{ $label }} <i class="kecil">({{ $uraian }})</i></div>
                @endforeach</td></tr>
    </table>

    <h3>B2. Capaian kinerja kumulatif {{ $posisi ? "s.d. {$posisi}" : '' }}</h3>
    <p class="hint">Diisi hanya untuk PSN pada tahapan Konstruksi, Beroperasi sebagian, atau Kumulatif selesai. Isi tabel yang sesuai jenis PSN (A12): B2.a untuk PSN fisik, B2.b untuk PSN non-fisik.</p>
    @if ($fisik)
        <div class="u">B2.a PSN Fisik</div>
        <table class="t">
            <tr><th style="width:18%">Aspek</th><th style="width:13%">Satuan</th><th style="width:11%">Target akhir proyek</th><th style="width:12%">Target kumulatif {{ $sdTw }}</th><th style="width:12%">Realisasi kumulatif {{ $sdTw }}</th><th>Keterangan / sumber data</th></tr>
            <tr><td><b>Progres fisik</b></td><td>%</td><td class="kanan">100</td><td class="kanan auto">{{ $n($b2['progres_rencana']) }}</td><td class="kanan auto">{{ $n($b2['progres_realisasi']) }}</td>
                <td><span class="hint">Mengacu kurva-S / rencana kerja yang berlaku, detailkan milestone apa yang sudah dan/atau yang belum tercapai.</span>
                    @if ($b2['progres_realisasi'] !== null)<div class="auto kecil">SIRENDA: progres tertimbang KP/RO tahun {{ $cutoff->tanggal_cutoff->year }}; sesuaikan dengan kurva-S proyek.</div>@endif</td></tr>
            <tr><td><b>Output utama</b> (sesuai output akhir A11)</td><td>.........<div class="hint">(misal: km, unit, MW, ha, dll.)</div></td><td></td><td></td><td></td>
                <td><span class="hint">Detailkan output apa yang sudah dan/atau yang belum tercapai.</span>
                    @if ($b2['ro'] && $b2['ro'][1])<div class="auto kecil">SIRENDA: {{ $b2['ro'][0] }} dari {{ $b2['ro'][1] }} KP/RO tercapai.</div>@endif</td></tr>
            @include('proyek.partials.form-pembiayaan')
            <tr><td><b>Perizinan</b></td><td>jumlah izin</td><td></td><td></td><td></td><td><span class="hint">Sebutkan izin kritis yang belum terbit.</span></td></tr>
            <tr><td><b>Pengadaan lahan</b></td><td>ha / bidang <span class="hint">(pilih yang sesuai)</span></td><td></td><td></td><td></td><td><span class="hint">Sebutkan bidang di jalur kritis yang belum bebas.</span></td></tr>
        </table>
    @endif
    @if ($nonfisik)
        <div class="u">B2.b PSN Non-Fisik</div>
        <table class="t">
            <tr><th style="width:18%">Aspek</th><th style="width:15%">Satuan</th><th style="width:11%">Target akhir program</th><th style="width:11%">Target kumulatif {{ $sdTw }}</th><th style="width:11%">Realisasi kumulatif {{ $sdTw }}</th><th>Keterangan / sumber data</th></tr>
            <tr><td><b>Output utama</b> (sesuai output akhir A11)</td><td>.........<div class="hint">(misal: unit layanan, paket, lembaga atau orang, KK, wilayah)</div></td><td></td><td></td><td></td>
                <td><span class="hint">Mengacu rencana kerja program; detailkan output yang sudah dan/atau belum tercapai.</span>
                    @if ($b2['ro'] && $b2['ro'][1])<div class="auto kecil">SIRENDA: {{ $b2['ro'][0] }} dari {{ $b2['ro'][1] }} KP/RO tercapai.</div>@endif</td></tr>
            @include('proyek.partials.form-pembiayaan')
            <tr><td><b>Regulasi dan petunjuk teknis</b></td><td>jumlah dokumen</td><td class="kanan auto">{{ $b2['regulasi_total'] ?: '' }}</td><td></td><td class="kanan auto">{{ $b2['regulasi_total'] ? $b2['regulasi_ditetapkan'] : '' }}</td>
                <td><span class="hint">Sebutkan regulasi / juknis yang belum terbit.</span>
                    @if ($b2['regulasi_belum'])<div class="auto kecil">SIRENDA, belum ditetapkan: {{ implode('; ', $b2['regulasi_belum']) }}</div>@endif</td></tr>
            <tr><td><b>Sarana / sistem pendukung</b> (bila ada)</td><td>unit <span class="hint">(mis. dapur, gerai, aplikasi)</span></td><td></td><td></td><td></td><td><span class="hint">Sebutkan sarana / sistem yang belum siap.</span></td></tr>
        </table>
    @endif

    <h3>B3. Milestone pra-konstruksi / persiapan program</h3>
    <p class="hint">Diisi hanya untuk PSN pada tahapan Penyiapan atau Transaksi. Urutan bersifat umum; isi N/A bila milestone tidak berlaku untuk skema proyek / program. Isi B3.a untuk PSN fisik atau B3.b untuk PSN non-fisik.</p>
    @foreach (array_filter(['B3.a PSN Fisik – milestone pra-konstruksi' => $fisik ? $milestoneFisik : null, 'B3.b PSN Non-Fisik – milestone persiapan program' => $nonfisik ? $milestoneNonFisik : null]) as $judul => $daftar)
        <div class="u">{{ $judul }}</div>
        <table class="t">
            <tr><th style="width:38%">Milestone</th><th style="width:26%">Realisasi {{ $sdTw }}</th><th style="width:14%">Tanggal selesai / target</th><th>Keterangan</th></tr>
            @foreach ($daftar as $m)<tr><td>{{ $m }}</td><td class="kecil">{!! $status3 !!}</td><td></td><td></td></tr>@endforeach
        </table>
    @endforeach

    <h3>B4. Manfaat proyek / program</h3>
    <p class="hint">Diisi untuk PSN pada tahapan Beroperasi sebagian atau Kumulatif selesai.</p>
    <table class="t"><tr><td>
        <b>Manfaat yang sudah terwujud {{ $posisi ? "s.d. {$posisi}" : '' }}</b>
        <div class="hint">Contoh: volume layanan / pengguna, jumlah penerima manfaat, perubahan indikator outcome (pendapatan, gizi, akses layanan), tenaga kerja terserap.</div>
        @for ($i = 0; $i < 3; $i++)<div class="titik" style="margin-top:8px">{{ $titik }}</div>@endfor
    </td></tr></table>

    {{-- BAGIAN C --}}
    <div class="bagian">BAGIAN C. ISU, PERMASALAHAN, DAN KEBUTUHAN DUKUNGAN</div>
    <h3>C1. Tipologi isu (boleh lebih dari satu)</h3>
    <table class="t">
        @foreach (array_chunk($tipologi, 2) as $i => $pasang)
            <tr>@foreach ($pasang as $j => $t)<td style="width:50%">☐ {{ $i * 2 + $j + 1 }}. {{ $t }}</td>@endforeach @if (count($pasang) === 1)<td></td>@endif</tr>
        @endforeach
    </table>

    <h3>C2. Uraian isu (uraikan dari tipologi isu yang dipilih, urutkan dari yang paling menghambat)</h3>
    <table class="t">
        <tr><th class="no">No.</th><th style="width:30%">Uraian isu / permasalahan</th><th style="width:19%">Tipologi (no. C1) &amp; dampak</th><th style="width:22%">Upaya yang sudah dilakukan (opsional)</th><th>Dukungan yang dibutuhkan (apa dan dari siapa)</th></tr>
        @foreach ($isu as $k => $s)
            <tr><td class="no">{{ $k + 1 }}</td><td class="auto">{!! nl2br(e($s['uraian'])) !!}</td>
                <td class="kecil">No.: ......<div>Menghambat jalur kritis / capaian sasaran?</div>
                    <div @class(['auto' => $s['jalur_kritis']])>{{ $kotak($s['jalur_kritis'] === true) }} Ya &nbsp; ☐ Tidak</div></td>
                <td class="auto">{!! nl2br(e($s['upaya'] ?? '')) !!}</td><td class="auto">{!! nl2br(e($s['dukungan'] ?? '')) !!}</td></tr>
        @endforeach
        @for ($k = count($isu); $k < count($isu) + $isuKosong; $k++)
            <tr class="baris-kosong"><td class="no">{{ $k + 1 }}</td><td></td><td class="kecil">No.: ......<div>Menghambat jalur kritis / capaian sasaran?</div><div>☐ Ya &nbsp; ☐ Tidak</div></td><td></td><td></td></tr>
        @endfor
    </table>
    @if ($isu)<p class="hint">Isu di atas adalah isu yang masih terbuka di SIRENDA (yang terkait KP/RO jalur kritis didahulukan). Tipologi isu mohon diisi.</p>@endif

    {{-- BAGIAN D --}}
    <div class="bagian">BAGIAN D. KEBERLANJUTAN STATUS PSN</div>
    <table class="t">
        <tr><th class="no">No.</th><th class="komponen">Komponen</th><th>Isian</th></tr>
        <tr><td class="no">D1</td><td class="komponen">Kebutuhan status PSN pada Pemutakhiran RKP {{ ($cutoff?->tanggal_cutoff->year ?? now()->year) + 1 }}</td>
            <td><div><b>☐ Masih membutuhkan status PSN</b> → lanjut ke butir D2 dan D3</div>
                <div><b>☐ Tidak lagi membutuhkan status PSN</b>, karena:</div>
                <div style="margin-left:14px">☐ Proyek / program telah selesai atau mencapai sasaran<br>☐ Proyek / program tidak dilanjutkan / dibatalkan<br>☐ Dapat dilanjutkan tanpa fasilitas PSN (termasuk sebagai program reguler / prioritas K/L)<br>☐ Lainnya: ..............................................</div></td></tr>
        <tr><td class="no">D2</td><td class="komponen">Fasilitas kemudahan yang masih dibutuhkan (PP 42/2021)<div class="hint">Boleh lebih dari satu</div></td>
            <td><table>@foreach (array_chunk($fasilitas, 2) as $pasang)<tr>@foreach ($pasang as $f)<td style="width:50%; padding:1px 0">☐ {{ $f }}</td>@endforeach</tr>@endforeach</table>
                <div style="margin-top:6px"><b>Justifikasi singkat (mengapa proyek / program tidak dapat berjalan tanpa fasilitas tersebut):</b></div>
                <div class="titik" style="margin-top:6px">{{ $titik }}</div><div class="titik" style="margin-top:6px">{{ $titik }}</div></td></tr>
        <tr><td class="no">D3</td><td class="komponen">Target selanjutnya<div class="hint">Menjadi baseline pemantauan tahun depan</div></td>
            <td>Tahapan yang ditargetkan akhir {{ ($cutoff?->tanggal_cutoff->year ?? now()->year) + 1 }}:
                <div>@foreach (FormPengendalianService::TAHAPAN as [$label]) ☐ {{ $label }} &nbsp; @endforeach</div>
                <div style="margin-top:4px">Target progres fisik / output / cakupan sasaran akhir {{ ($cutoff?->tanggal_cutoff->year ?? now()->year) + 1 }}: ..............................</div>
                <div style="margin-top:4px">Target tahun selesai proyek / program (proyeksi berdasarkan capaian aktual saat ini): .........</div></td></tr>
    </table>

    {{-- PENGESAHAN --}}
    <div class="bagian">PENGESAHAN</div>
    <table class="ttd">
        <tr><td style="background:#e5e7eb"><b>Diisi oleh</b></td></tr>
        <tr><td><b>PIC Pengelola / Pelaksana PSN atau Pejabat unit Penanggung Jawab PSN (min. Eselon II)</b>
            @foreach (['Nama', 'Jabatan', 'Unit / Instansi', 'Email / HP', 'Tanggal', 'Tanda tangan'] as $label)
                <table style="margin-top:7px"><tr><td style="width:110px; border:0; padding:0">{{ $label }}</td><td style="border:0; padding:0" class="titik">: {{ $titik }}</td></tr></table>
            @endforeach
        </td></tr>
    </table>

    {{-- LAMPIRAN --}}
    <div class="bagian pecah">LAMPIRAN. INFORMASI DAN DOKUMEN PENDUKUNG</div>
    <p>Dalam hal terdapat informasi lain terkait PSN yang belum tercantum dalam form ini, informasi tersebut dapat dilampirkan, <b>termasuk dokumen, foto, dan video</b>, dalam bentuk <b>tautan (link)</b> atau <b>file softcopy</b> yang disertakan bersama form. Tuliskan daftar lampiran pada tabel berikut.</p>
    <p class="hint">Contoh: laporan kemajuan, kurva-S, dokumen perizinan, foto lapangan terkini, video progres, notulen rapat koordinasi, surat terkait status PSN.</p>
    <table class="t">
        <tr><th class="no">No.</th><th style="width:20%">Jenis lampiran</th><th style="width:32%">Judul / uraian singkat</th><th style="width:12%">Bentuk</th><th>Tautan (link) / nama file</th></tr>
        @for ($i = 1; $i <= config('psn_dashboard.form_pengendalian.baris_lampiran'); $i++)
            <tr><td class="no">{{ $i }}</td><td class="kecil">☐ Dokumen ☐ Foto<br>☐ Video ☐ Lainnya</td><td></td><td class="kecil">☐ Tautan<br>☐ File</td><td></td></tr>
        @endfor
    </table>
    <table class="t" style="margin-top:8px"><tr><td>
        <b>Informasi tambahan (uraian bebas)</b>
        <div class="hint">Uraikan informasi lain terkait PSN yang belum tertampung dalam form, termasuk penjelasan atas lampiran di atas.</div>
        @for ($i = 0; $i < 3; $i++)<div class="titik" style="margin-top:8px">{{ $titik }}</div>@endfor
    </td></tr></table>
</div>
</body>
</html>
