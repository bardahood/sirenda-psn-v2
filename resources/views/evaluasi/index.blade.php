@php
    $s = $alur['status'];
    $tautan = fn (array $ubah) => route('evaluasi.index', array_filter(array_merge($filter, $ubah), fn ($v) => $v !== null && $v !== ''));
    $n = fn ($v) => number_format((int) $v, 0, ',', '.');
    $proses = $s['PROSES_PENGECEKAN'] + $s['BUTUH_DATA'] + $s['VERIFIKASI_PJ'] + $s['SIAP_DIANALISIS'];
    $belumDiputus = max(0, $alur['total'] - $s['DIPERTAHANKAN'] - $alur['dikeluarkan_total']);
    $tahunBerjalan = $tahun - 1;
    $labelSumber = config('psn_dashboard.label_sumber_psn');
@endphp
<x-app-layout judul="Evaluasi Status PSN · RKP {{ $tahun }}" subjudul="Penentuan PSN dilanjutkan atau dikeluarkan berdasarkan Form Pengendalian dan data profil proyek">
    <x-slot:infoJudul>Data profil per {{ $cutoff?->tanggal_cutoff?->translatedFormat('j F Y') ?? '–' }} · {{ $n($alur['total']) }} PSN dievaluasi</x-slot:infoJudul>
    <x-slot:aksi>
        <a href="{{ route('evaluasi.ekspor', $filter) }}" class="tombol-garis py-1.5">Unduh Excel</a>
    </x-slot:aksi>

    <div class="mx-auto max-w-[1600px] space-y-5">
        @include('evaluasi._tab')
        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif

        {{-- Alur keberlanjutan PSN: daftar RKP berjalan -> form -> pengecekan -> indikasi -> proyeksi daftar RKP berikutnya --}}
        <section aria-labelledby="h-alur" class="kartu overflow-hidden">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="h-alur" class="judul-panel">Alur Keberlanjutan PSN menuju RKP {{ $tahun }}</h2>
                <p class="label">Klik angka atau label untuk menyaring daftar di bawah.</p>
            </div>
            <ol class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5 xl:items-start">
                {{-- 1. Basis --}}
                <li class="flex flex-col rounded-kartu border border-slate-200 bg-slate-50/60 p-4 xl:mt-28">
                    <span class="self-start rounded-md bg-emerald-700 px-2.5 py-1 text-[12px] font-bold text-white">Basis evaluasi</span>
                    <span class="mt-2 text-[13px] font-semibold text-slate-700">PSN tahun berjalan (RKP &amp; Permenko)</span>
                    <a href="{{ $tautan(['status' => null, 'page' => null]) }}" class="angka mt-1 text-[38px] font-extrabold leading-none text-slate-900 hover:underline">{{ $n($alur['total']) }}</a>
                    <ul class="mt-3 space-y-1.5 text-[12.5px]">
                        @foreach ($alur['sumber'] as $kode => $jml)
                            <li class="flex items-center gap-2">
                                <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md bg-slate-700 px-2 py-0.5 font-bold text-white">{{ $n($jml) }}</span>
                                <a href="{{ $tautan(['sumber' => $kode, 'page' => null]) }}" class="text-slate-700 hover:underline">{{ $labelSumber[$kode] ?? $kode }}</a>
                            </li>
                        @endforeach
                    </ul>
                </li>

                {{-- 2. Form Pengendalian --}}
                <li class="flex flex-col rounded-kartu border border-slate-200 bg-white p-4 xl:mt-20">
                    <span class="self-start rounded-md bg-amber-600 px-2.5 py-1 text-[12px] font-bold text-white">Form Pengendalian</span>
                    <span class="mt-2 text-[13px] font-semibold text-slate-700">Form diterima</span>
                    <span class="angka mt-1 text-[38px] font-extrabold leading-none text-slate-900">{{ $n($alur['diterima']) }}<span class="text-[16px] font-semibold text-slate-500"> / {{ $n($alur['total']) }}</span></span>
                    <ul class="mt-3 space-y-1.5 text-[12.5px]">
                        <li class="flex items-center gap-2">
                            <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md bg-slate-400 px-2 py-0.5 font-bold text-white">{{ $n($s['BELUM_DITERIMA']) }}</span>
                            <a href="{{ $tautan(['status' => 'BELUM_DITERIMA', 'page' => null]) }}" class="text-slate-700 hover:underline">Belum kembali</a>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md bg-emerald-600 px-2 py-0.5 font-bold text-white">{{ $n($alur['lengkap']) }}</span>
                            <a href="{{ $tautan(['kelengkapan' => 'LENGKAP', 'page' => null]) }}" class="text-slate-700 hover:underline">Isian wajib lengkap (cek otomatis)</a>
                        </li>
                    </ul>
                    {{-- Progres form per sumber PSN --}}
                    <table class="mt-3 w-full border-t border-slate-200 text-[12px]">
                        <thead class="text-[11px] text-slate-500"><tr><th class="pt-2 text-left font-semibold">Sumber</th><th class="pt-2 text-right font-semibold">Diterima</th><th class="pt-2 text-right font-semibold">Belum kembali</th></tr></thead>
                        <tbody>
                            @foreach ($alur['form_sumber'] as $kode => $f)
                                <tr>
                                    <td class="py-0.5 text-slate-700">PSN {{ $labelSumber[$kode] ?? $kode }}</td>
                                    <td class="angka py-0.5 text-right"><a href="{{ $tautan(['sumber' => $kode, 'status' => null, 'page' => null]) }}" class="font-bold text-emerald-700 hover:underline" title="{{ $n($f['diterima']) }} dari {{ $n($f['diterima'] + $f['belum']) }} PSN {{ $labelSumber[$kode] ?? $kode }}">{{ $n($f['diterima']) }}</a></td>
                                    <td class="angka py-0.5 text-right"><a href="{{ $tautan(['sumber' => $kode, 'status' => 'BELUM_DITERIMA', 'page' => null]) }}" class="font-bold text-slate-600 hover:underline">{{ $n($f['belum']) }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </li>

                {{-- 3. Pengecekan --}}
                <li class="flex flex-col rounded-kartu border border-slate-200 bg-white p-4 xl:mt-12">
                    <span class="self-start rounded-md bg-sky-700 px-2.5 py-1 text-[12px] font-bold text-white">Pengecekan &amp; analisis</span>
                    <span class="mt-2 text-[13px] font-semibold text-slate-700">Dalam proses</span>
                    <span class="angka mt-1 text-[38px] font-extrabold leading-none text-slate-900">{{ $n($proses) }}</span>
                    <ul class="mt-3 space-y-1.5 text-[12.5px]">
                        @foreach (['PROSES_PENGECEKAN' => 'bg-sky-600', 'BUTUH_DATA' => 'bg-amber-600', 'VERIFIKASI_PJ' => 'bg-orange-600', 'SIAP_DIANALISIS' => 'bg-indigo-600'] as $kode => $warna)
                            <li class="flex items-center gap-2">
                                <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md {{ $warna }} px-2 py-0.5 font-bold text-white">{{ $n($s[$kode]) }}</span>
                                <a href="{{ $tautan(['status' => $kode, 'page' => null]) }}" class="text-slate-700 hover:underline">{{ $cfg['status'][$kode] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </li>

                {{-- 4. Indikasi status --}}
                <li class="flex flex-col rounded-kartu border border-slate-200 bg-white p-4 xl:mt-4">
                    <span class="self-start rounded-md bg-[#0d2b55] px-2.5 py-1 text-[12px] font-bold text-white">Indikasi status</span>
                    <span class="mt-2 text-[13px] font-semibold text-slate-700">Sudah diputuskan</span>
                    <span class="angka mt-1 text-[38px] font-extrabold leading-none text-slate-900">{{ $n($s['DIPERTAHANKAN'] + $s['DIKELUARKAN']) }}</span>
                    <ul class="mt-3 space-y-2 text-[12.5px]">
                        @foreach (['DIPERTAHANKAN' => ['+', 'bg-green-600', 'dipertahankan_nama'], 'DIKELUARKAN' => ['−', 'bg-red-700', 'dikeluarkan_nama']] as $kode => [$tanda, $warna, $kunciNama])
                            <li>
                                <span class="flex items-center gap-2">
                                    <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md {{ $warna }} px-2 py-0.5 font-bold text-white">{{ $tanda }}{{ $n($s[$kode]) }}</span>
                                    <a href="{{ $tautan(['status' => $kode, 'page' => null]) }}" class="font-semibold text-slate-800 hover:underline">{{ $cfg['status'][$kode] }}</a>
                                </span>
                                @if ($alur[$kunciNama])
                                    <ol class="ml-[3.25rem] mt-1 list-decimal space-y-0.5 pl-4 text-[11.5px] text-slate-600">
                                        @foreach ($alur[$kunciNama] as $p)<li class="truncate"><a href="{{ route('evaluasi.show', $p['id']) }}" class="hover:underline" title="{{ $p['nama'] }}">{{ \Illuminate\Support\Str::limit($p['nama'], 42) }}</a></li>@endforeach
                                    </ol>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </li>

                {{-- 5. Proyeksi RKP berikutnya --}}
                <li class="flex flex-col rounded-kartu border-2 border-[#0d2b55]/30 bg-white p-4">
                    <span class="self-start rounded-md bg-teal-700 px-2.5 py-1 text-[12px] font-bold text-white">RKP {{ $tahunBerjalan }} → RKP {{ $tahun }}</span>
                    <span class="mt-2 text-[13px] font-semibold text-slate-700">Proyeksi PSN dalam daftar RKP {{ $tahun }}</span>
                    <span class="angka mt-1 text-[44px] font-extrabold leading-none text-[#0d2b55]">{{ $n($alur['proyeksi']) }}</span>
                    <span class="mt-1 text-[11px] text-slate-500">= {{ $n($alur['total']) }} PSN tahun berjalan + {{ $n($alur['baru']) }} usulan baru − {{ $n($alur['dikeluarkan_total']) }} dikeluarkan</span>
                    <ul class="mt-3 space-y-2 text-[12.5px]">
                        <li class="flex items-center gap-2">
                            <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md bg-slate-700 px-2 py-0.5 font-bold text-white">{{ $n($alur['total']) }}</span>
                            <a href="{{ $tautan(['status' => null, 'page' => null]) }}" class="font-semibold text-slate-800 hover:underline">PSN tahun berjalan</a>
                        </li>
                        <li>
                            <span class="flex items-center gap-2">
                                <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md bg-green-600 px-2 py-0.5 font-bold text-white">+{{ $n($alur['baru']) }}</span>
                                <a href="{{ route('perencanaan.index', ['tahun' => $tahun]) }}" class="font-semibold text-slate-800 hover:underline">Usulan PSN baru yang ditetapkan</a>
                            </span>
                            @if ($alur['baru_nama'])
                                <ol class="ml-[3.25rem] mt-1 list-decimal space-y-0.5 pl-4 text-[11.5px] text-slate-600">
                                    @foreach ($alur['baru_nama'] as $u)<li class="truncate"><a href="{{ route('perencanaan.show', $u['id']) }}" class="hover:underline">{{ \Illuminate\Support\Str::limit($u['nama'], 42) }}</a></li>@endforeach
                                </ol>
                            @endif
                        </li>
                        <li class="flex items-center gap-2" title="Hanya keputusan PMO (Indikasi status = Dikeluarkan)">
                            <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md bg-red-700 px-2 py-0.5 font-bold text-white">−{{ $n($alur['dikeluarkan_total']) }}</span>
                            <a href="{{ $tautan(['status' => 'DIKELUARKAN', 'page' => null]) }}" class="text-slate-700 hover:underline">PSN tahun berjalan dikeluarkan (keputusan PMO)</a>
                        </li>
                        <li class="flex items-center gap-2">
                            <span class="angka inline-flex min-w-[2.75rem] justify-center rounded-md bg-slate-400 px-2 py-0.5 font-bold text-white">{{ $n($belumDiputus) }}</span>
                            <span class="text-slate-700">belum diputuskan (dihitung tetap)</span>
                        </li>
                    </ul>
                </li>
            </ol>
        </section>

        <div class="grid grid-cols-12 gap-5">
            <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-harian" x-data="grafikStatusHarian(@js($harian), @js(array_diff_key($cfg['status'], ['BELUM_DITERIMA' => 1])))">
                <h2 id="h-harian" class="judul-panel">Pergerakan Status Akhir per Hari</h2>
                <p class="label">{{ count($harian) }} hari terakhir; status suatu hari = perubahan terakhir PSN s.d. hari itu (PSN dihitung sejak form diterima).</p>
                <div x-ref="kanvas" class="mt-3 h-[300px] w-full" role="img" aria-label="Grafik batang bertumpuk jumlah PSN per status akhir per hari"></div>
            </section>
            <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-rek">
                <h2 id="h-rek" class="judul-panel">Rekomendasi Sistem</h2>
                <p class="label">Alat bantu analis berdasarkan kelengkapan, D1, deviasi progres, serapan, jadwal, dan isu jalur kritis. Keputusan tetap pada Indikasi Status.</p>
                <ul class="mt-4 space-y-3">
                    @php($maks = max(1, max($alur['rekomendasi'])))
                    @foreach ($alur['rekomendasi'] as $kode => $jml)
                        <li>
                            <a href="{{ $tautan(['rekomendasi' => $kode, 'page' => null]) }}" class="flex items-center justify-between gap-2 hover:underline">
                                <span class="badge-REK_{{ $kode }}"><span class="titik-REK_{{ $kode }}"></span>{{ $cfg['rekomendasi'][$kode] }}</span>
                                <span class="angka font-bold text-slate-900">{{ $n($jml) }}</span>
                            </a>
                            <span class="mt-1 block h-1.5 rounded-full bg-slate-100"><span class="block h-1.5 rounded-full bg-aksen" style="width: {{ round(100 * $jml / $maks) }}%"></span></span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <section class="kartu" aria-labelledby="h-daftar">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <h2 id="h-daftar" class="judul-panel mr-auto">Daftar Evaluasi <span class="label font-normal">{{ $n($daftar->total()) }} PSN</span></h2>
                <label class="text-isi"><span class="label block">Cari</span>
                    <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100" class="mt-1 w-52 rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" placeholder="Nama atau kode PSN…">
                </label>
                @foreach ([
                    ['status', 'Status akhir', $cfg['status']],
                    ['rekomendasi', 'Rekomendasi sistem', $cfg['rekomendasi']],
                    ['kelengkapan', 'Kelengkapan', ['LENGKAP' => 'Lengkap', 'KURANG' => 'Belum lengkap']],
                    ['sumber', 'Sumber', $labelSumber],
                    ['klaster', 'Klaster', $klaster->mapWithKeys(fn ($k) => [$k => $k])->all()],
                ] as [$nama, $label, $pilihan])
                    <label class="text-isi"><span class="label block">{{ $label }}</span>
                        <select name="{{ $nama }}" class="mt-1 max-w-[13rem] rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                            <option value="">Semua</option>
                            @foreach ($pilihan as $nilai => $teks)<option value="{{ $nilai }}" @selected((string) ($filter[$nama] ?? '') === (string) $nilai)>{{ $teks }}</option>@endforeach
                        </select>
                    </label>
                @endforeach
                <button class="tombol-garis py-1.5">Terapkan</button>
                <a href="{{ route('evaluasi.index') }}" class="tombol-garis py-1.5">Reset</a>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-500">
                        <tr>
                            <th class="py-2 pr-3">No</th><th class="py-2 pr-3">PSN</th><th class="py-2 pr-3">Tahapan</th>
                            <th class="py-2 pr-3 text-right">Deviasi fisik</th><th class="py-2 pr-3 text-right">Isu (jalur kritis)</th>
                            <th class="py-2 pr-3">Kelengkapan</th><th class="py-2 pr-3">D1 Kebutuhan</th><th class="py-2 pr-3">Rekomendasi sistem</th><th class="py-2">Status akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($daftar as $i => $r)
                            @php($ind = $r['indikator'])
                            <tr class="align-top hover:bg-slate-50">
                                <td class="angka py-2 pr-3 text-slate-500">{{ $daftar->firstItem() + $i }}</td>
                                <td class="max-w-[24rem] py-2 pr-3">
                                    <a href="{{ route('evaluasi.show', $r['psn_id']) }}" class="font-semibold text-slate-900 hover:underline">{{ $r['nama'] }}</a>
                                    <span class="block text-[11px] text-slate-500">{{ $r['kode'] }} · {{ $labelSumber[$r['sumber']] ?? '–' }} · {{ $r['klaster'] ?? '–' }}</span>
                                </td>
                                <td class="whitespace-nowrap py-2 pr-3">{{ $cfg['tahapan'][$r['data']['tahapan']] ?? '–' }}</td>
                                <td @class(['angka whitespace-nowrap py-2 pr-3 text-right', 'font-semibold text-red-700' => ($ind['deviasi_fisik'] ?? 0) <= -$cfg['ambang']['deviasi_fisik_poin']])>
                                    {{ $ind['deviasi_fisik'] === null ? '–' : number_format($ind['deviasi_fisik'], 1, ',', '.') }}</td>
                                <td class="angka whitespace-nowrap py-2 pr-3 text-right">{{ $ind['isu_terbuka'] }} @if ($ind['isu_jalur_kritis'])<span class="text-red-700">({{ $ind['isu_jalur_kritis'] }})</span>@endif</td>
                                <td class="py-2 pr-3">
                                    @if ($r['lengkap'])<span class="badge bg-green-50 text-green-800 ring-green-600/40">Lengkap</span>
                                    @else<span class="badge bg-amber-50 text-amber-900 ring-amber-600/40" title="{{ implode('; ', $r['kurang']) }}">{{ count($r['kurang']) }} isian kurang</span>@endif
                                </td>
                                @php($d1Efektif = \App\Services\EvaluasiStatusService::d1($r['evaluasi'], $r['data']))
                                <td class="py-2 pr-3 text-[12px]" title="{{ $r['evaluasi']?->d1_kebutuhan ? 'Isian evaluasi' : ($d1Efektif ? 'Dari isian Kebutuhan Status PSN di SIRENDA: '.$r['data']['kebutuhan_status'] : '') }}">{{ $d1Efektif ? ($d1Efektif === 'MASIH' ? 'Masih butuh' : 'Tidak butuh') : '–' }}@if ($d1Efektif && ! $r['evaluasi']?->d1_kebutuhan)<span class="block text-[10.5px] text-sky-700">dari SIRENDA</span>@endif</td>
                                <td class="py-2 pr-3"><span class="badge-REK_{{ $r['rekomendasi'] }}" title="{{ implode('; ', $r['sinyal']) }}"><span class="titik-REK_{{ $r['rekomendasi'] }}"></span>{{ $cfg['rekomendasi'][$r['rekomendasi']] }}</span></td>
                                <td class="py-2"><span class="badge-EV_{{ $r['status_akhir'] }}"><span class="titik-EV_{{ $r['status_akhir'] }}"></span>{{ $cfg['status'][$r['status_akhir']] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-6 text-center text-slate-500">Tidak ada PSN yang sesuai filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $daftar->links() }}</div>
        </section>
    </div>
</x-app-layout>
