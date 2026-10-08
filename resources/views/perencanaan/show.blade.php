@use('App\Models\UsulanPsn')
@php
    $label = ['UTAMA' => 'Kriteria Utama', 'PENDUKUNG' => 'Pendukung', 'KESIAPAN' => 'Kesiapan', 'LOKASI' => 'Lokasi', 'TRISULA' => 'Trisula'];
    $bolehIsi = $penilaian && auth()->user()->can('nilai', $penilaian);
    $rek = $hasil['rekomendasi'] ?? null;
    $na = $hasil['nilai_akhir'] ?? null;
    $kriteriaPer = collect($hasil['kriteria'] ?? [])->groupBy('kelompok');
    $tampilNilai = fn ($k) => ($v = $hasil['nilai'][$k['id']] ?? null) === null ? '–' : ($k['tipe_nilai'] === 'YA_TIDAK' ? ($v ? 'Ya' : 'Tidak') : $v);
@endphp
<x-app-layout judul="Penilaian Usulan PSN">
    <div class="mx-auto max-w-[1376px]">
        <nav class="label mb-4" aria-label="Breadcrumb"><a href="{{ route('perencanaan.index') }}" class="text-aksen-700 hover:underline">Perencanaan</a> / <span aria-current="page">{{ $usulan->nama }}</span></nav>
        @if (session('status'))<div class="mb-4 rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="mb-4 rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        {{-- Identitas usulan --}}
        <section class="kartu mb-6">
            <div class="flex flex-wrap items-start gap-4">
                <div class="min-w-0 flex-1">
                    <p class="label">Usulan RKP {{ $usulan->tahun_rkp }} · {{ $usulan->klaster?->nama ?? 'Tanpa klaster' }}</p>
                    <h2 class="mt-1 text-xl font-semibold text-primer">{{ $usulan->nama }}</h2>
                    <dl class="mt-3 flex flex-wrap gap-x-8 gap-y-1">
                        <div><dt class="label inline">Pengusul</dt> <dd class="inline">{{ $usulan->pengusulInstansi?->nama ?? $usulan->pengusul_teks ?? '–' }} ({{ UsulanPsn::JENIS_PENGUSUL[$usulan->jenis_pengusul] ?? 'jenis belum diisi' }})</dd></div>
                        <div><dt class="label inline">Direktorat pengampu</dt> <dd class="inline">{{ $usulan->unitKerja?->nama ?? '–' }}</dd></div>
                        <div><dt class="label inline">Infrastruktur</dt> <dd class="inline">{{ $usulan->is_infrastruktur ? 'Ya' : 'Tidak' }}</dd></div>
                        <div><dt class="label inline">Lokasi</dt> <dd class="inline">{{ $usulan->lokasi->map(fn ($l) => $l->provinsi?->nama)->filter()->implode(', ') ?: '–' }}</dd></div>
                    </dl>
                </div>
                <div class="flex flex-col items-end gap-2">
                    @if ($usulan->penilaian->isNotEmpty())
                        <form method="GET" class="flex items-center gap-2"><label class="label" for="pilih-penilaian">Sesi penilaian</label>
                            <select id="pilih-penilaian" name="penilaian" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-1.5 text-isi">
                                @foreach ($usulan->penilaian as $p)<option value="{{ $p->id }}" @selected($penilaian?->id === $p->id)>{{ $p->forum ?? 'Penilaian' }} · {{ $p->tanggal?->format('d/m/Y') }} · {{ $p->status }}</option>@endforeach
                            </select></form>
                    @endif
                    @can('update', $usulan)
                        <details class="relative">
                            <summary class="tombol-garis cursor-pointer list-none py-1.5">+ Sesi penilaian baru</summary>
                            <form method="POST" action="{{ route('perencanaan.penilaian.store', $usulan) }}" class="absolute right-0 z-20 mt-1 w-80 space-y-3 rounded-kartu border border-slate-200 bg-white p-4 shadow-lg">
                                @csrf
                                <label class="block"><span class="label">Forum</span><input name="forum" required maxlength="100" value="Rapat Pleno II Pemutakhiran RKP {{ $usulan->tahun_rkp }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                                <label class="block"><span class="label">Tanggal</span><input type="date" name="tanggal" required value="{{ now()->toDateString() }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                                <button class="tombol-primer w-full justify-center">Buat</button>
                            </form>
                        </details>
                    @endcan
                </div>
            </div>
        </section>

        @if (! $penilaian)
            <section class="kartu text-center text-slate-600">Usulan ini belum memiliki sesi penilaian.</section>
        @else
            <div class="grid grid-cols-12 gap-6">
                {{-- GATE KU1-KU3 --}}
                <section class="col-span-12" aria-labelledby="h-gate">
                    @if ($hasil['gate']['lulus'] === false)
                        <div class="mb-3 flex items-center gap-3 rounded-kartu bg-red-700 px-5 py-4 text-white" role="alert">
                            <span class="text-2xl font-bold tracking-wide">DITOLAK</span>
                            <span>Kriteria Utama {{ implode(', ', $hasil['gate']['gagal']) }} bernilai "Tidak". Rekomendasi otomatis Ditolak berapa pun nilai komponen lainnya.</span>
                        </div>
                    @endif
                    <div class="kartu">
                        <h3 id="h-gate" class="judul-panel mb-3">Gate 3 Kriteria Utama</h3>
                        <div class="grid gap-4 md:grid-cols-3">
                            @foreach ($kriteriaPer['UTAMA'] ?? [] as $k)
                                @php($v = $hasil['nilai'][$k['id']] ?? null)
                                <div @class(['rounded-lg border p-3', 'border-red-300 bg-red-50' => $v === 0, 'border-green-300 bg-green-50' => $v === 1, 'border-slate-200' => $v === null])>
                                    <div class="flex items-center justify-between"><span class="font-semibold">{{ $k['kode'] }}</span>
                                        <span @class(['badge', 'bg-red-100 text-red-800 ring-red-600/40' => $v === 0, 'bg-green-100 text-green-800 ring-green-600/40' => $v === 1, 'bg-slate-100 text-slate-700 ring-slate-400/40' => $v === null])>{{ $v === null ? 'Belum dinilai' : ($v ? 'Ya' : 'Tidak') }}</span></div>
                                    <p class="mt-1 text-label text-slate-700">{{ \Illuminate\Support\Str::limit($k['uraian'], 160) }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Komponen skor --}}
                @foreach (\App\Services\ScoringService::KOMPONEN as $kel)
                    @php($k = $hasil['komponen'][$kel])
                    <section class="kartu col-span-12 sm:col-span-6 xl:col-span-3" aria-label="Komponen {{ $label[$kel] }}">
                        <div class="flex items-baseline justify-between"><span class="label uppercase tracking-wide">{{ $label[$kel] }}</span><span class="label">Bobot {{ $k['bobot'] * 100 }}%</span></div>
                        <div class="angka mt-2 text-kpi text-primer">{{ $k['skor'] !== null ? number_format($k['skor'], 2, ',', '.') : '–' }}</div>
                        <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-aksen" style="width: {{ min(100, $k['skor'] ?? 0) }}%"></div></div>
                        <p class="label mt-2">{{ $k['jumlah_sub'] }} sub-kriteria berlaku · Σ {{ $k['total_skor'] }} dari {{ 3 * $k['jumlah_sub'] }}
                            @if ($k['belum_dinilai'])<br><span class="text-amber-800">Belum dinilai: {{ implode(', ', $k['belum_dinilai']) }}</span>@endif</p>
                    </section>
                @endforeach

                {{-- Gauge nilai akhir + rekomendasi --}}
                <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-na">
                    <h3 id="h-na" class="judul-panel">Nilai Akhir & Rekomendasi</h3>
                    @php($sudut = min(100, max(0, $na ?? 0)) / 100)
                    <svg viewBox="0 0 200 120" class="mx-auto mt-2 w-64" role="img" aria-label="Nilai akhir {{ $na !== null ? number_format($na, 2, ',', '.') : 'belum dapat dihitung' }} dari 100">
                        <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke="#e2e8f0" stroke-width="16" stroke-linecap="round"/>
                        @if ($na !== null)
                            <path d="M20 100 A80 80 0 0 1 180 100" fill="none" stroke="{{ $hasil['gate']['lulus'] === false ? '#b91c1c' : '#1F6FD1' }}" stroke-width="16" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ $sudut * 100 }} 100"/>
                        @endif
                        <text x="100" y="92" text-anchor="middle" class="angka" style="font: 700 28px 'Plus Jakarta Sans Variable', sans-serif; fill: #0E2747">{{ $na !== null ? number_format($na, 2, ',', '.') : '–' }}</text>
                        <text x="20" y="116" text-anchor="middle" style="font: 500 10px sans-serif; fill: #64748b">0</text>
                        <text x="180" y="116" text-anchor="middle" style="font: 500 10px sans-serif; fill: #64748b">100</text>
                    </svg>
                    <div class="text-center"><span class="{{ $rek->badge() }} text-isi">{{ $rek->label() }}</span></div>
                    <p class="label mt-3 text-center">0,35·Pendukung + 0,35·Kesiapan + 0,15·Lokasi + 0,15·Trisula</p>
                    @foreach ($hasil['peringatan'] as $pr)<p class="mt-2 rounded-md bg-amber-50 p-2 text-label text-amber-900">{{ $pr }}</p>@endforeach
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <span class="badge bg-slate-100 text-slate-700 ring-slate-400/40">Status: {{ $penilaian->status }}</span>
                        @can('finalisasi', $penilaian)
                            <form method="POST" action="{{ route('perencanaan.penilaian.final', $penilaian) }}">@csrf<button class="tombol-primer py-1.5">Tetapkan FINAL</button></form>
                        @endcan
                        @if ($penilaian->isFinal())
                            @can('kelola', App\Models\UsulanPsn::class)
                                <form method="POST" action="{{ route('perencanaan.penilaian.buka', $penilaian) }}">@csrf<button class="tombol-garis py-1.5">Buka kembali</button></form>
                            @endcan
                        @endif
                    </div>
                </section>

                {{-- Rincian sub-kriteria (formulir skor) --}}
                <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-sub">
                    <h3 id="h-sub" class="judul-panel mb-3">Rincian Sub-kriteria <span class="label font-normal">(skor 0–3; Kriteria Utama Ya/Tidak)</span></h3>
                    <form method="POST" action="{{ route('perencanaan.penilaian.skor', $penilaian) }}">
                        @csrf @method('PUT')
                        <div class="max-h-[720px] overflow-y-auto pr-1">
                            @foreach ($kriteriaPer as $kel => $daftar)
                                <h4 class="sticky top-0 bg-white py-2 text-label font-semibold uppercase tracking-wide text-slate-600">{{ $label[$kel] ?? $kel }}</h4>
                                <ul class="divide-y divide-slate-100">
                                    @foreach ($daftar as $k)
                                        @php($berlakuK = $berlaku[$k['id']] ?? true)
                                        <li @class(['grid grid-cols-12 items-start gap-3 py-2', 'opacity-60' => ! $berlakuK])>
                                            <div class="col-span-12 md:col-span-7">
                                                <span class="font-semibold">{{ $k['kode'] }}</span> {{ $k['uraian'] }}
                                                @unless ($berlakuK)<span class="ml-1 badge bg-slate-100 text-slate-600 ring-slate-300">Tidak berlaku untuk usulan ini</span>@endunless
                                            </div>
                                            <div class="col-span-4 md:col-span-2">
                                                @if ($bolehIsi && $berlakuK)
                                                    <label class="sr-only" for="n-{{ $k['id'] }}">Nilai {{ $k['kode'] }}</label>
                                                    <select id="n-{{ $k['id'] }}" name="nilai[{{ $k['id'] }}]" class="w-full rounded-lg border-slate-300 py-1 text-isi">
                                                        <option value="">–</option>
                                                        @if ($k['tipe_nilai'] === 'YA_TIDAK')
                                                            <option value="1" @selected(($hasil['nilai'][$k['id']] ?? null) === 1)>Ya</option>
                                                            <option value="0" @selected(($hasil['nilai'][$k['id']] ?? null) === 0)>Tidak</option>
                                                        @else
                                                            @foreach ([0, 1, 2, 3] as $s)<option value="{{ $s }}" @selected(($hasil['nilai'][$k['id']] ?? null) === $s)>{{ $s }}</option>@endforeach
                                                        @endif
                                                    </select>
                                                @else
                                                    <span class="angka font-semibold">{{ $berlakuK ? $tampilNilai($k) : '–' }}</span>
                                                @endif
                                            </div>
                                            <div class="col-span-8 md:col-span-3">
                                                @if ($bolehIsi && $berlakuK)
                                                    <label class="sr-only" for="t-{{ $k['id'] }}">Temuan {{ $k['kode'] }}</label>
                                                    <textarea id="t-{{ $k['id'] }}" name="temuan[{{ $k['id'] }}]" rows="1" maxlength="2000" placeholder="Catatan/temuan" class="w-full rounded-lg border-slate-300 py-1 text-label">{{ $temuan[$k['id']] ?? '' }}</textarea>
                                                @else
                                                    <span class="text-label text-slate-600">{{ $temuan[$k['id']] ?? '' }}</span>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endforeach
                        </div>
                        @if ($bolehIsi)
                            <div class="mt-3 flex justify-end"><button class="tombol-primer">Simpan skor & hitung ulang</button></div>
                        @endif
                    </form>
                </section>

                {{-- Peta usulan vs PSN eksisting --}}
                <section class="kartu col-span-12 md:col-span-6" aria-labelledby="h-peta"
                         x-data="petaSebaran({ data: @js($petaUsulan['provinsi']), tileUrl: @js(config('psn_dashboard.peta.tile_url')), atribusi: @js(config('psn_dashboard.peta.tile_atribusi')), geojson: @js(file_exists(public_path(config('psn_dashboard.peta.geojson'))) ? asset(config('psn_dashboard.peta.geojson')) : null), kodeProp: @js(config('psn_dashboard.peta.kode_prop')) })">
                    <h3 id="h-peta" class="judul-panel mb-3">Lokasi Usulan vs PSN Eksisting</h3>
                    <div x-ref="peta" class="h-72 rounded-lg bg-slate-100" role="img" aria-label="Peta sebaran PSN eksisting dengan lokasi usulan disorot"></div>
                    <table class="mt-3 w-full text-left text-isi">
                        <thead class="text-label text-slate-600"><tr><th class="py-1">Provinsi usulan</th><th class="py-1 text-right">PSN eksisting</th><th class="py-1 text-right">Klaster sama</th></tr></thead>
                        <tbody>
                            @forelse ($petaUsulan['lokasi_usulan'] as $l)
                                <tr><td class="py-1">{{ $l['label'] }}</td><td class="angka py-1 text-right">{{ $l['psn'] }}</td><td class="angka py-1 text-right">{{ $l['sejenis'] }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="py-1 text-slate-500">Lokasi usulan belum diisi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <p class="label mt-2">Lingkaran oranye = lokasi usulan. PSN eksisting dari snapshot cut-off {{ $petaUsulan['cutoff'] ?? '–' }}.</p>
                </section>

                {{-- Perbandingan antar-usulan --}}
                <section class="kartu col-span-12 md:col-span-6" aria-labelledby="h-banding" x-data="grafikPerbandingan(@js($perbandingan))">
                    <h3 id="h-banding" class="judul-panel mb-3">Perbandingan Antar-usulan RKP {{ $usulan->tahun_rkp }}</h3>
                    @if ($perbandingan)
                        <div x-ref="kanvas" style="height: {{ max(200, count($perbandingan) * 30 + 40) }}px" role="img" aria-label="Nilai akhir usulan pada tahun RKP yang sama"></div>
                        <p class="label mt-2">Batang biru = usulan ini. Diurutkan berdasarkan nilai akhir penilaian terakhir.</p>
                    @else
                        <p class="text-slate-500">Belum ada usulan lain yang memiliki nilai akhir.</p>
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-app-layout>
