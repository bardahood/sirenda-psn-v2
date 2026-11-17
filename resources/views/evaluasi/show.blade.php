@php
    $d = $h['data'];
    $ind = $h['indikator'];
    $u = auth()->user();
    $bisaInput = $e && $u->can('evaluasi.input');
    $bisaVerif = $e && $u->can('evaluasi.verifikasi');
    $bisaKelola = $e && $u->can('evaluasi.kelola');
    $angka = fn ($v, $dig = 1) => $v === null ? '–' : number_format((float) $v, $dig, ',', '.');
    $miliar = fn ($v) => $v === null ? '–' : 'Rp '.number_format($v / 1e9, 2, ',', '.').' M';
    $nilai = fn (string $k, $bawaan = null) => old($k, $bawaan);
    $kelompokKurang = collect($h['kurang']);
    $cekAturan = [
        'A6 Tahun penetapan PSN', 'A7 Target tahun selesai', 'B1 Tahapan', 'B2 Progres fisik/output', 'B2 Pembiayaan', 'B3 Milestone', 'B4 Manfaat',
        'C1 Tipologi isu', 'C2 Isu dan dukungan yang dibutuhkan', 'D1 Kebutuhan status PSN', 'D2 Fasilitas', 'D3 Target selanjutnya',
    ];
    $input = 'mt-1 w-full rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen disabled:bg-slate-50 disabled:text-slate-500';
    // Kertas kerja (rincian B dan C).
    $lampIsi = old('lampiran', $e?->lampiran->map(fn ($l) => $l->only(['jenis', 'judul', 'bentuk', 'tautan']))->all() ?? []);
                        $cap = $h['capaian'];
                        $isianCap = $e?->capaian->keyBy('komponen') ?? collect();
                        $isuIsi = old('isu', $e && $e->isu->isNotEmpty() ? $e->isu->map(fn ($i) => ['uraian' => $i->uraian, 'tipologi' => $i->tipologi, 'jalur_kritis' => $i->jalur_kritis === null ? '' : (string) (int) $i->jalur_kritis, 'upaya' => $i->upaya, 'dukungan' => $i->dukungan])->all()
                            : collect($h['isu'])->map(fn ($i) => ['uraian' => $i['uraian'], 'tipologi' => $i['tipologi'], 'jalur_kritis' => $i['jalur_kritis'] === null ? '' : (string) (int) $i['jalur_kritis'], 'upaya' => $i['upaya'], 'dukungan' => $i['dukungan']])->all());
                        $msIsi = $e?->milestone->keyBy('kode') ?? collect();
                        $fmt = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $a6Profil = $h['data']['tahun_terbit'] ?? null; // A6 dari profil SIRENDA (tahun_terbit)
    $d1Profil = $h['data']['d1_profil'] ?? null;      // D1 dari isian Kebutuhan Status PSN di SIRENDA
    $fasilitasProfil = $h['data']['fasilitas_profil'] ?? [];
@endphp
<x-app-layout :judul="$psn->nama" subjudul="Evaluasi status PSN untuk RKP {{ $tahun }} · {{ $psn->kode_psn }}">
    <x-slot:aksi>
        <a href="{{ route('evaluasi.index') }}" class="tombol-garis py-1.5">← Daftar evaluasi</a>
        <a href="{{ route('proyek.show', $psn) }}" class="tombol-garis py-1.5">Profil PSN</a>
        <a href="{{ route('proyek.form-pengendalian', $psn) }}" class="tombol-garis py-1.5" target="_blank" rel="noopener">Form Pengendalian</a>
    </x-slot:aksi>

    <div class="mx-auto max-w-[1600px] space-y-5">
        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $g)<li>{{ $g }}</li>@endforeach</ul></div>@endif

        {{-- Ringkasan keputusan --}}
        <section class="grid grid-cols-1 gap-4 lg:grid-cols-3" aria-label="Ringkasan">
            <div class="kartu">
                <h2 class="label">Status akhir</h2>
                <p class="mt-2"><span class="badge-EV_{{ $h['status_akhir'] }} !text-[14px]"><span class="titik-EV_{{ $h['status_akhir'] }}"></span>{{ $cfg['status'][$h['status_akhir']] }}</span></p>
                <p class="mt-2 text-[12px] text-slate-500">Indikasi Status bila sudah diisi; bila belum, Tindak Lanjut; bila belum, Proses Pengecekan.</p>
            </div>
            <div class="kartu">
                <h2 class="label">Rekomendasi sistem (alat bantu analis)</h2>
                <p class="mt-2"><span class="badge-REK_{{ $h['rekomendasi'] }} !text-[14px]"><span class="titik-REK_{{ $h['rekomendasi'] }}"></span>{{ $cfg['rekomendasi'][$h['rekomendasi']] }}</span></p>
                @if ($h['sinyal'])
                    <ul class="mt-2 list-disc space-y-0.5 pl-5 text-[12.5px] text-slate-700">@foreach ($h['sinyal'] as $s)<li>{{ $s }}</li>@endforeach</ul>
                @else
                    <p class="mt-2 text-[12.5px] text-slate-500">Tidak ada sinyal risiko pada indikator.</p>
                @endif
            </div>
            <div class="kartu">
                <h2 class="label">Cek kelengkapan otomatis (aturan wajib isi)</h2>
                <p class="mt-2">
                    @if ($h['lengkap'])<span class="badge bg-green-50 text-green-800 ring-green-600/40 !text-[14px]">Lengkap</span>
                    @else<span class="badge bg-amber-50 text-amber-900 ring-amber-600/40 !text-[14px]">Belum lengkap: {{ $kelompokKurang->count() }} isian</span>@endif
                </p>
                <ul class="mt-2 grid grid-cols-2 gap-x-3 gap-y-0.5 text-[12px]">
                    @foreach ($cekAturan as $a)
                        @php($k = $kelompokKurang->contains($a))
                        <li @class(['flex items-center gap-1', 'font-semibold text-amber-800' => $k, 'text-slate-600' => ! $k])><span aria-hidden="true">{{ $k ? '✗' : '✓' }}</span>{{ $a }}<span class="sr-only">{{ $k ? ' belum terisi' : ' terpenuhi atau tidak wajib' }}</span></li>
                    @endforeach
                </ul>
            </div>
        </section>

        <div class="grid grid-cols-12 gap-5">
            {{-- Data profil (A-C) --}}
            <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-profil">
                <h2 id="h-profil" class="judul-panel">Data Profil PSN (A–C, otomatis)</h2>
                <p class="label">Dari profil SIRENDA, cut-off {{ $cutoff?->tanggal_cutoff?->translatedFormat('j F Y') }}. Perbaiki di SIRENDA PEKS3 bila keliru.</p>
                <dl class="mt-3 divide-y divide-slate-100 text-isi">
                    @foreach ([
                        ['B1 Tahapan', $cfg['tahapan'][$d['tahapan']] ?? '–'],
                        ['A7 Target tahun selesai', $d['tahun_selesai'] ?? '–'],
                        ['B2 Progres fisik rencana / realisasi', $angka($d['progres_rencana']).'% / '.$angka($d['progres_realisasi']).'%'],
                        ['Deviasi progres fisik', $ind['deviasi_fisik'] === null ? '–' : $angka($ind['deviasi_fisik']).' poin'],
                        ['B2 Pembiayaan pagu / realisasi', $miliar($d['pagu_rp']).' / '.$miliar($d['realisasi_anggaran_rp'])],
                        ['Serapan anggaran', $ind['serapan'] === null ? '–' : $angka($ind['serapan']).'%'],
                        ['KP/RO tercapai', $d['ro'][1] ? $d['ro'][0].' dari '.$d['ro'][1] : '–'],
                        ['B3 Kegiatan (KP/RO) tercatat', $d['kegiatan']],
                        ['B4 Indikator & penerima manfaat', $d['manfaat']],
                        ['C Isu terbuka di profil / dipakai di kertas kerja / menghambat jalur kritis', $d['isu_terbuka'].' / '.$ind['isu_terbuka'].' / '.$ind['isu_jalur_kritis']],
                        ['Regulasi belum ditetapkan', $d['regulasi_belum']],
                        ['Usia sebagai PSN', $ind['umur_psn'] === null ? '– (isi A6)' : $ind['umur_psn'].' tahun'],
                    ] as [$lbl, $v])
                        <div class="flex justify-between gap-3 py-1.5"><dt class="text-slate-600">{{ $lbl }}</dt><dd class="angka text-right font-semibold text-slate-900">{{ $v }}</dd></div>
                    @endforeach
                </dl>
            </section>

            {{-- Isian evaluasi --}}
            <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-isian">
                <h2 id="h-isian" class="judul-panel">Isian Form Pengendalian &amp; Analisis</h2>
                @if (! $e)
                    <div class="mt-3 rounded-kartu border border-dashed border-slate-300 p-5 text-center">
                        <p class="text-slate-700">Form Pengendalian PSN ini belum ditandai diterima.</p>
                        @can('evaluasi.input')
                            <form method="POST" action="{{ route('evaluasi.terima', $psn) }}" class="mt-3 inline-flex flex-wrap items-end justify-center gap-3">@csrf
                                <label class="text-left text-isi"><span class="label block">Tanggal form diterima</span>
                                    <input type="date" name="form_diterima_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="{{ $input }}"></label>
                                <button class="tombol-primer py-1.5">Tandai form diterima</button>
                            </form>
                        @endcan
                    </div>
                @else
                    <form method="POST" action="{{ route('evaluasi.update', $psn) }}" class="mt-3 space-y-5"
                          x-data="{ d1: @js($nilai('d1_kebutuhan', $e->d1_kebutuhan)), alasan: @js($nilai('d1_alasan', $e->d1_alasan)), jenis: @js($nilai('jenis_psn', $e->jenis_psn) ?: 'FISIK') }">
                        @csrf @method('PUT')
                        <fieldset class="grid grid-cols-1 gap-3 md:grid-cols-5" @disabled(! $bisaInput)>
                            <legend class="mb-1 text-[13px] font-bold text-slate-800">Identitas tambahan</legend>
                            <label class="text-isi"><span class="label block">Tanggal form diterima *</span>
                                <input type="date" name="form_diterima_at" value="{{ $nilai('form_diterima_at', $e->form_diterima_at?->toDateString()) }}" max="{{ now()->toDateString() }}" class="{{ $input }}"></label>
                            <label class="text-isi"><span class="label block">A6 Tahun penetapan PSN *</span>
                                <input type="number" name="a6_tahun_penetapan" min="{{ $cfg['tahun_min'] }}" max="{{ $cfg['tahun_maks'] }}" value="{{ $nilai('a6_tahun_penetapan', $e->a6_tahun_penetapan) }}"
                                       placeholder="{{ $a6Profil ?? '' }}" @class([$input, 'bg-sky-50 placeholder:text-sky-800' => $a6Profil])
                                       title="{{ $a6Profil ? 'Kosongkan untuk memakai tahun penetapan dari profil SIRENDA ('.$a6Profil.')' : 'Tahun penetapan belum tercatat di profil SIRENDA' }}">
                                @if ($a6Profil)<span class="mt-0.5 block text-[11px] text-sky-700">Dari profil: {{ $a6Profil }}. Isi hanya bila berbeda.</span>@endif</label>
                            <label class="text-isi"><span class="label block">A8 Sumber pendanaan</span>
                                <select name="a8_sumber_pendanaan" class="{{ $input }}"><option value="">Dari profil: {{ $cfg['sumber_pendanaan'][$a8Profil] ?? '–' }}</option>
                                    @foreach ($cfg['sumber_pendanaan'] as $k => $l)<option value="{{ $k }}" @selected($nilai('a8_sumber_pendanaan', $e->a8_sumber_pendanaan) === $k)>{{ $l }}</option>@endforeach</select></label>
                            <label class="text-isi"><span class="label block">A12 Jenis PSN</span>
                                <select name="jenis_psn" x-model="jenis" class="{{ $input }}"><option value="">–</option>
                                    @foreach ($cfg['jenis_psn'] as $k => $l)<option value="{{ $k }}" @selected($nilai('jenis_psn', $e->jenis_psn) === $k)>{{ $l }}</option>@endforeach</select></label>
                            <label class="flex items-center gap-2 self-end pb-2 text-isi"><input type="checkbox" name="tanpa_isu" value="1" @checked($nilai('tanpa_isu', $e->tanpa_isu)) class="rounded border-slate-300 text-aksen">C1 "Tidak ada isu"</label>
                        </fieldset>

                        <fieldset class="space-y-4 rounded-kartu border border-slate-200 p-4" @disabled(! $bisaInput)>
                            <legend class="px-1 text-[13px] font-bold text-slate-800">B dan C. Capaian, milestone, manfaat, dan isu (kertas kerja)</legend>
                            <input type="hidden" name="bagian_rincian" value="1">
                            <p class="text-[12px] text-slate-500">Kolom berlatar biru muda berisi data profil SIRENDA. Isi hanya bila Form Pengendalian memuat angka yang berbeda; kosongkan untuk tetap memakai data profil.</p>

                            <div>
                                <span class="label block" x-text="jenis === 'NON_FISIK' ? 'B2.b Capaian PSN Non Fisik s.d. akhir triwulan' : 'B2.a Capaian PSN Fisik s.d. akhir triwulan'"></span>
                                <div class="mt-1 overflow-x-auto">
                                    <table class="w-full text-isi">
                                        <thead class="text-label text-slate-500"><tr><th class="py-1 pr-2 text-left">Komponen</th><th class="py-1 pr-2 text-left">Satuan</th><th class="py-1 pr-2 text-left">Target akhir</th><th class="py-1 pr-2 text-left">Target kumulatif</th><th class="py-1 pr-2 text-left">Realisasi kumulatif</th><th class="py-1 text-left">Keterangan / sumber data</th></tr></thead>
                                        <tbody>
                                            @foreach ($cfg['capaian'] as $komp => [$labelKomp, $satuanTetap, $sumberProfil])
                                                @php($jenisKomp = collect($cfg['capaian_jenis'])->filter(fn ($l) => in_array($komp, $l, true))->keys()->all())
                                                <tr x-show="@js($jenisKomp).includes(jenis || 'FISIK')" class="align-top">
                                                    <td class="py-1 pr-2 font-semibold text-slate-700">{{ $labelKomp }} @if ($sumberProfil)<span class="block text-[11px] font-normal text-sky-700">ada di profil</span>@endif</td>
                                                    @foreach (['satuan', 'target_akhir', 'target_kumulatif', 'realisasi_kumulatif'] as $kol)
                                                        @php($profil = in_array($kol, $cap[$komp]['dari_profil'], true) ? $cap[$komp][$kol] : null)
                                                        <td class="py-1 pr-2">
                                                            @if ($kol === 'satuan' && $satuanTetap)
                                                                <span class="block pt-2 text-slate-600">{{ $satuanTetap }}</span>
                                                            @else
                                                                <input type="{{ $kol === 'satuan' ? 'text' : 'number' }}" step="any" min="0" name="capaian[{{ $komp }}][{{ $kol }}]"
                                                                       value="{{ old("capaian.$komp.$kol", $kol === 'satuan' ? ($isianCap[$komp]->satuan ?? '') : $fmt($isianCap[$komp]->{$kol} ?? null)) }}"
                                                                       placeholder="{{ $profil !== null ? ($kol === 'satuan' ? $profil : $fmt($profil)) : '' }}"
                                                                       @class([$input, 'w-28' => $kol !== 'satuan', 'w-24' => $kol === 'satuan', 'bg-sky-50 placeholder:text-sky-800' => $profil !== null])>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                    <td class="py-1"><input type="text" name="capaian[{{ $komp }}][keterangan]" maxlength="2000" value="{{ old("capaian.$komp.keterangan", $isianCap[$komp]->keterangan ?? '') }}" class="{{ $input }} min-w-[14rem]"></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div id="b3" class="scroll-mt-24">
                                <span class="label block" x-text="jenis === 'NON_FISIK' ? 'B3.b Milestone persiapan program (wajib bila tahap Penyiapan/Transaksi; minimal 1)' : 'B3.a Milestone pra konstruksi (wajib bila tahap Penyiapan/Transaksi; minimal 1)'"></span>
                                @php($baris = 0)
                                @foreach ($cfg['milestone'] as $jenisMs => $daftarMs)
                                    <fieldset x-show="(jenis || 'FISIK') === @js($jenisMs)" :disabled="(jenis || 'FISIK') !== @js($jenisMs)" class="mt-1 overflow-x-auto">
                                        <table class="w-full text-isi">
                                            <thead class="text-label text-slate-500"><tr><th class="py-1 pr-2 text-left">Milestone</th><th class="py-1 pr-2 text-left">Realisasi</th><th class="py-1 pr-2 text-left">Tanggal selesai / target</th><th class="py-1 text-left">Keterangan</th></tr></thead>
                                            <tbody>
                                                @foreach ($daftarMs as $kodeMs => $labelMs)
                                                    <tr class="align-top">
                                                        <td class="py-1 pr-2 text-slate-700">{{ $loop->iteration }}. {{ $labelMs }}
                                                            <input type="hidden" name="milestone[{{ $baris }}][kode]" value="{{ $kodeMs }}">
                                                            @if (str_starts_with($kodeMs, 'LAIN'))<input type="text" name="milestone[{{ $baris }}][nama_lain]" maxlength="255" placeholder="Nama milestone" value="{{ old("milestone.$baris.nama_lain", $msIsi[$kodeMs]->nama_lain ?? '') }}" class="{{ $input }}">@endif
                                                        </td>
                                                        <td class="py-1 pr-2"><select name="milestone[{{ $baris }}][status]" class="{{ $input }} w-28"><option value="">–</option>
                                                            @foreach ($cfg['status_milestone'] as $k => $l)<option value="{{ $k }}" @selected(old("milestone.$baris.status", $msIsi[$kodeMs]->status ?? '') === $k)>{{ $l }}</option>@endforeach</select></td>
                                                        <td class="py-1 pr-2"><input type="date" name="milestone[{{ $baris }}][tanggal]" value="{{ old("milestone.$baris.tanggal", $msIsi->get($kodeMs)?->tanggal?->toDateString()) }}" class="{{ $input }} w-40"></td>
                                                        <td class="py-1"><input type="text" name="milestone[{{ $baris }}][keterangan]" maxlength="1000" value="{{ old("milestone.$baris.keterangan", $msIsi[$kodeMs]->keterangan ?? '') }}" class="{{ $input }} min-w-[14rem]"></td>
                                                    </tr>
                                                    @php($baris++)
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </fieldset>
                                @endforeach
                            </div>

                            <label class="block text-isi"><span class="label block">B4 Manfaat yang sudah terwujud s.d. akhir triwulan (wajib bila Beroperasi sebagian/Kumulatif selesai)</span>
                                <textarea name="b4_manfaat" rows="2" maxlength="4000" class="{{ $input }}">{{ $nilai('b4_manfaat', $e->b4_manfaat) }}</textarea></label>

                            <div>
                                <span class="label block">C1 Tipologi isu (boleh lebih dari satu)</span>
                                <div class="mt-1 grid grid-cols-1 gap-1 text-isi md:grid-cols-3">
                                    @foreach (array_diff_key($cfg['tipologi_isu'], ['11' => 1]) as $k => $l)
                                        <label class="flex items-center gap-2"><input type="checkbox" name="c1_tipologi[]" value="{{ $k }}" @checked(in_array((string) $k, array_map('strval', $nilai('c1_tipologi', $e->c1_tipologi ?? []) ?? []), true)) class="rounded border-slate-300 text-aksen">{{ $k }}. {{ $l }}</label>
                                    @endforeach
                                    <label class="text-isi md:col-span-3"><span class="label">11. Lainnya (sebutkan; tulis "Tidak ada isu" bila tidak ada)</span>
                                        <input type="text" name="c1_lainnya" maxlength="255" value="{{ $nilai('c1_lainnya', $e->c1_lainnya) }}" class="{{ $input }}"></label>
                                </div>
                            </div>

                            <div id="c2" class="scroll-mt-24">
                                <span class="label block">C2 Uraian isu (maks. {{ $cfg['maks_isu'] }}). @if ($e->isu->isEmpty() && $h['isu'])<span class="text-sky-700">Terisi dari isu terbuka di profil; periksa dan lengkapi tipologinya.</span>@endif</span>
                                <div class="mt-1 space-y-2">
                                    @for ($i = 0; $i < $cfg['maks_isu']; $i++)
                                        @php($is = $isuIsi[$i] ?? [])
                                        <div class="grid grid-cols-1 gap-2 rounded-lg border border-slate-200 p-2 md:grid-cols-12">
                                            <span class="angka pt-2 text-[12px] font-bold text-slate-500 md:col-span-1">#{{ $i + 1 }}</span>
                                            <label class="md:col-span-5"><span class="sr-only">Uraian isu {{ $i + 1 }}</span><textarea name="isu[{{ $i }}][uraian]" rows="2" maxlength="2000" placeholder="Uraian isu / permasalahan" class="{{ $input }}">{{ $is['uraian'] ?? '' }}</textarea></label>
                                            <label class="md:col-span-3"><span class="sr-only">Tipologi</span><select name="isu[{{ $i }}][tipologi]" class="{{ $input }}"><option value="">Tipologi (no. C1)</option>
                                                @foreach ($cfg['tipologi_isu'] as $k => $l)<option value="{{ $k }}" @selected((string) ($is['tipologi'] ?? '') === (string) $k)>{{ $k }}. {{ $l }}</option>@endforeach</select></label>
                                            <label class="md:col-span-3"><span class="sr-only">Menghambat jalur kritis</span><select name="isu[{{ $i }}][jalur_kritis]" class="{{ $input }}"><option value="">Menghambat jalur kritis?</option>
                                                @foreach ($cfg['ya_tidak'] as $k => $l)<option value="{{ $k }}" @selected(($is['jalur_kritis'] ?? '') === (string) $k)>{{ $l }}</option>@endforeach</select></label>
                                            <label class="md:col-span-5 md:col-start-2"><span class="sr-only">Upaya</span><input type="text" name="isu[{{ $i }}][upaya]" maxlength="2000" placeholder="Upaya yang sudah dilakukan (opsional)" value="{{ $is['upaya'] ?? '' }}" class="{{ $input }}"></label>
                                            <label class="md:col-span-6"><span class="sr-only">Dukungan</span><input type="text" name="isu[{{ $i }}][dukungan]" maxlength="2000" placeholder="Dukungan yang dibutuhkan (apa dan dari siapa)" value="{{ $is['dukungan'] ?? '' }}" class="{{ $input }}"></label>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="space-y-3 rounded-kartu border border-slate-200 p-4" @disabled(! $bisaInput)>
                            <legend class="px-1 text-[13px] font-bold text-slate-800">D. Keberlanjutan status PSN (isian PJ)</legend>
                            <input type="hidden" name="bagian_input" value="1">
                            <div class="flex flex-wrap gap-4 text-isi">
                                <span class="label w-full">D1 Kebutuhan status PSN pada RKP {{ $tahun }} *</span>
                                @foreach ($cfg['d1'] as $k => $l)
                                    <label class="flex items-center gap-2"><input type="radio" name="d1_kebutuhan" value="{{ $k }}" x-model="d1" class="border-slate-300 text-aksen">{{ $l }}</label>
                                @endforeach
                                <label class="flex items-center gap-2 text-slate-500"><input type="radio" name="d1_kebutuhan" value="" x-model="d1" class="border-slate-300">{{ $d1Profil ? 'Ikuti SIRENDA ('.$cfg['d1'][$d1Profil].')' : 'Belum diisi' }}</label>
                                @if ($h['data']['kebutuhan_status'] ?? null)
                                    <p class="w-full rounded-lg bg-sky-50 px-3 py-2 text-[12px] text-sky-900">Isian Kebutuhan Status PSN di SIRENDA: <i>"{{ \Illuminate\Support\Str::limit($h['data']['kebutuhan_status'], 400) }}"</i>
                                        {{ $d1Profil ? '· dibaca sebagai "'.$cfg['d1'][$d1Profil].'". Pilih di atas hanya bila berbeda.' : '· pernyataannya tidak tegas, mohon tentukan D1.' }}</p>
                                @endif
                            </div>
                            <div x-show="(d1 || @js($d1Profil)) === 'TIDAK'" x-cloak class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <label class="text-isi"><span class="label block">Alasan *</span>
                                    <select name="d1_alasan" x-model="alasan" class="{{ $input }}"><option value="">–</option>
                                        @foreach ($cfg['d1_alasan'] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                                <label class="text-isi" x-show="alasan === 'LAINNYA'"><span class="label block">Alasan lainnya *</span>
                                    <input type="text" name="d1_alasan_lain" value="{{ $nilai('d1_alasan_lain', $e->d1_alasan_lain) }}" maxlength="2000" class="{{ $input }}"></label>
                            </div>
                            <div x-show="(d1 || @js($d1Profil)) === 'MASIH'" x-cloak class="space-y-3">
                                <div>
                                    <span class="label block">D2 Fasilitas kemudahan yang masih dibutuhkan (PP 42/2021) *</span>
                                    @if ($fasilitasProfil)<span class="block text-[11px] text-slate-500">Keterangan biru = fasilitas yang pernah diterima menurut SIRENDA, sebagai rujukan. Pilih yang <b>masih</b> dibutuhkan.</span>@endif
                                    <div class="mt-1 grid grid-cols-1 gap-1 text-isi md:grid-cols-3">
                                        @foreach ($cfg['d2_fasilitas'] as $k => $l)
                                            <label class="flex items-start gap-2"><input type="checkbox" name="d2_fasilitas[]" value="{{ $k }}" @checked(in_array($k, $nilai('d2_fasilitas', $e->d2_fasilitas ?? []) ?? [], true)) class="mt-0.5 rounded border-slate-300 text-aksen"><span>{{ $loop->iteration }}. {{ $l }}@if (! empty($fasilitasProfil[$k]))<span class="block text-[11px] text-sky-700">diterima menurut SIRENDA: {{ implode(', ', $fasilitasProfil[$k]) ?: 'tahun tidak tercatat' }}</span>@endif</span></label>
                                        @endforeach
                                    </div>
                                </div>
                                <label class="block text-isi"><span class="label block">Justifikasi singkat</span>
                                    <textarea name="d2_justifikasi" rows="2" maxlength="4000" class="{{ $input }}">{{ $nilai('d2_justifikasi', $e->d2_justifikasi) }}</textarea></label>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <label class="text-isi"><span class="label block">D3 Tahapan target akhir {{ $tahun }} *</span>
                                        <select name="d3_tahapan" class="{{ $input }}"><option value="">–</option>
                                            @foreach ($cfg['tahapan'] as $k => $l)<option value="{{ $k }}" @selected($nilai('d3_tahapan', $e->d3_tahapan) === $k)>{{ $l }}</option>@endforeach</select></label>
                                    <label class="text-isi md:col-span-1"><span class="label block">D3 Target progres/output akhir {{ $tahun }} *</span>
                                        <input type="text" name="d3_target" maxlength="2000" value="{{ $nilai('d3_target', $e->d3_target) }}" class="{{ $input }}"></label>
                                    <label class="text-isi"><span class="label block">D3 Proyeksi tahun selesai *</span>
                                        <input type="number" name="d3_tahun_selesai" min="{{ $cfg['tahun_min'] }}" max="{{ $cfg['tahun_maks'] }}" value="{{ $nilai('d3_tahun_selesai', $e->d3_tahun_selesai) }}" class="{{ $input }}"></label>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                                <span class="label md:col-span-5">PIC / pejabat penanggung jawab (min. Eselon II)</span>
                                @foreach (['nama' => 'Nama', 'jabatan' => 'Jabatan', 'unit' => 'Unit / instansi', 'kontak' => 'Email / HP'] as $k => $l)
                                    <label class="text-isi"><span class="sr-only">{{ $l }}</span><input type="text" name="pic[{{ $k }}]" placeholder="{{ $l }}" maxlength="200" value="{{ $nilai("pic.$k", $e->pic[$k] ?? null) }}" class="{{ $input }}"></label>
                                @endforeach
                                <label class="text-isi"><span class="sr-only">Tanggal</span><input type="date" name="pic[tanggal]" value="{{ $nilai('pic.tanggal', $e->pic['tanggal'] ?? null) }}" class="{{ $input }}"></label>
                            </div>
                        </fieldset>

                        <fieldset id="lampiran" class="scroll-mt-24 space-y-3 rounded-kartu border border-slate-200 p-4" @disabled(! $bisaInput)>
                            <legend class="px-1 text-[13px] font-bold text-slate-800">Lampiran dan informasi tambahan</legend>
                                                        <div class="overflow-x-auto">
                                <table class="w-full text-isi">
                                    <thead class="text-label text-slate-500"><tr><th class="py-1 pr-2 text-left">#</th><th class="py-1 pr-2 text-left">Jenis</th><th class="py-1 pr-2 text-left">Judul / uraian singkat</th><th class="py-1 pr-2 text-left">Bentuk</th><th class="py-1 text-left">Tautan / nama file</th></tr></thead>
                                    <tbody>
                                        @for ($i = 0; $i < $cfg['maks_lampiran']; $i++)
                                            @php($l = $lampIsi[$i] ?? [])
                                            <tr x-data="{ buka: {{ $i < max(2, count($lampIsi) + 1) ? 'true' : 'false' }} }" x-show="buka">
                                                <td class="angka py-1 pr-2 text-slate-500">{{ $i + 1 }}</td>
                                                <td class="py-1 pr-2"><select name="lampiran[{{ $i }}][jenis]" class="{{ $input }} w-32"><option value="">–</option>@foreach ($cfg['lampiran_jenis'] as $k => $v)<option value="{{ $k }}" @selected(($l['jenis'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></td>
                                                <td class="py-1 pr-2"><input type="text" name="lampiran[{{ $i }}][judul]" maxlength="255" value="{{ $l['judul'] ?? '' }}" class="{{ $input }} min-w-[14rem]"></td>
                                                <td class="py-1 pr-2"><select name="lampiran[{{ $i }}][bentuk]" class="{{ $input }} w-28"><option value="">–</option>@foreach ($cfg['lampiran_bentuk'] as $k => $v)<option value="{{ $k }}" @selected(($l['bentuk'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></td>
                                                <td class="py-1"><input type="text" name="lampiran[{{ $i }}][tautan]" maxlength="1000" value="{{ $l['tautan'] ?? '' }}" class="{{ $input }} min-w-[16rem]"></td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                            <label class="block text-isi"><span class="label block">Informasi tambahan</span>
                                <textarea name="info_tambahan" rows="2" maxlength="4000" class="{{ $input }}">{{ $nilai('info_tambahan', $e->info_tambahan) }}</textarea></label>
                        </fieldset>

                        <fieldset class="grid grid-cols-1 gap-3 rounded-kartu border border-slate-200 p-4 md:grid-cols-2" @disabled(! $bisaVerif)>
                            <legend class="px-1 text-[13px] font-bold text-slate-800">Pengecekan dan analisis</legend>
                            <label class="text-isi"><span class="label block">Hasil pengecekan (terhadap kolom wajib)</span>
                                <select name="hasil_pengecekan" class="{{ $input }}"><option value="">–</option>
                                    @foreach ($cfg['hasil_pengecekan'] as $k => $l)<option value="{{ $k }}" @selected($nilai('hasil_pengecekan', $e->hasil_pengecekan) === $k)>{{ $l }}</option>@endforeach</select>
                                @if (str_starts_with((string) $e->hasil_pengecekan, 'LENGKAP') && ! $h['lengkap'])
                                    <span class="mt-1 block text-[12px] font-semibold text-red-700">Dipilih "Lengkap" padahal cek otomatis masih menemukan isian kosong.</span>
                                @endif
                            </label>
                            <label class="text-isi"><span class="label block">Tindak lanjut (kosongkan = otomatis dari hasil pengecekan)</span>
                                <select name="tindak_lanjut" class="{{ $input }}"><option value="">Otomatis</option>
                                    @foreach ($cfg['tindak_lanjut'] as $k => $l)<option value="{{ $k }}" @selected($nilai('tindak_lanjut', $e->tindak_lanjut) === $k)>{{ $l }}</option>@endforeach</select></label>
                            <label class="text-isi md:col-span-2"><span class="label block">Catatan hasil analisis</span>
                                <textarea name="catatan_analisis" rows="3" maxlength="5000" class="{{ $input }}">{{ $nilai('catatan_analisis', $e->catatan_analisis) }}</textarea></label>
                        </fieldset>

                        <fieldset class="rounded-kartu border-2 border-[#0d2b55]/20 p-4" @disabled(! $bisaKelola)>
                            <legend class="px-1 text-[13px] font-bold text-slate-800">Indikasi status PSN (keputusan)</legend>
                            <div class="flex flex-wrap gap-4 text-isi">
                                @foreach ($cfg['indikasi'] as $k => $l)
                                    <label class="flex items-center gap-2"><input type="radio" name="indikasi_status" value="{{ $k }}" @checked($nilai('indikasi_status', $e->indikasi_status) === $k) class="border-slate-300 text-aksen">
                                        <span class="badge-EV_{{ $k }}"><span class="titik-EV_{{ $k }}"></span>{{ $l }}</span></label>
                                @endforeach
                                <label class="flex items-center gap-2 text-slate-500"><input type="radio" name="indikasi_status" value="" @checked(! $nilai('indikasi_status', $e->indikasi_status)) class="border-slate-300">Belum diputuskan</label>
                            </div>
                            <p class="mt-1 text-[12px] text-slate-500">Diisi setelah Catatan Hasil Analisis ada. Hanya Tim Koordinasi/PMO dan Super Admin.</p>
                        </fieldset>

                        @if ($bisaInput || $bisaVerif || $bisaKelola)
                            <div class="flex flex-wrap items-end justify-end gap-3">
                                <label class="text-isi"><span class="label block">Keterangan perubahan status (opsional)</span>
                                    <input type="text" name="keterangan_riwayat" maxlength="255" class="{{ $input }} w-80"></label>
                                <button class="tombol-primer py-1.5">Simpan evaluasi</button>
                            </div>
                        @endif
                    </form>
                @endif
            </section>
        </div>

        @if ($e)
            <section class="kartu" aria-labelledby="h-riwayat">
                <h2 id="h-riwayat" class="judul-panel">Riwayat Status Akhir</h2>
                <ol class="mt-3 space-y-2">
                    @foreach ($riwayat as $r)
                        <li class="flex flex-wrap items-center gap-3 text-isi">
                            <span class="angka w-24 text-slate-500">{{ $r->tanggal->format('d/m/Y') }}</span>
                            <span class="badge-EV_{{ $r->status_akhir }}"><span class="titik-EV_{{ $r->status_akhir }}"></span>{{ $cfg['status'][$r->status_akhir] ?? $r->status_akhir }}</span>
                            <span class="text-slate-700">{{ $r->keterangan }}</span>
                            <span class="text-[12px] text-slate-500">{{ $r->user?->name }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
</x-app-layout>
