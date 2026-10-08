@use('App\Services\PengisianService')
@php
    $cfg = config('psn_dashboard.pengisian');
    $kelasLevel = fn ($l) => match ($l) {
        'Rendah' => 'badge bg-green-50 text-green-800 ring-green-600/40',
        'Sedang' => 'badge bg-amber-50 text-amber-900 ring-amber-600/40',
        'Tinggi' => 'badge bg-orange-50 text-orange-900 ring-orange-600/40',
        'Sangat Tinggi' => 'badge bg-red-50 text-red-800 ring-red-600/40',
        default => 'badge bg-slate-100 text-slate-700 ring-slate-400/40',
    };
    $rp = fn ($v) => $v === null ? '–' : 'Rp '.number_format((float) $v, 0, ',', '.');
    $pct = fn ($v) => $v === null ? '–' : number_format((float) $v, 1, ',', '.').'%';
    $namaBulan = $periode->tanggal_cutoff->translatedFormat('F Y');
    $inp = 'w-full rounded-lg border-slate-300 py-1 text-isi disabled:bg-slate-50 disabled:text-slate-600';
@endphp
<x-app-layout judul="Pengisian & Verifikasi">
    <div class="mx-auto max-w-[1376px] space-y-6">
        <nav class="text-label" aria-label="Remah roti"><a href="{{ route('pengisian.index', ['periode' => $periode->kode]) }}" class="text-aksen-700 hover:underline">Pengisian & Verifikasi</a> / {{ $periode->kode }}</nav>

        <section class="kartu flex flex-wrap items-start gap-x-8 gap-y-3" aria-label="Ringkasan isian">
            <div class="mr-auto">
                <h2 class="text-panel font-bold text-primer">{{ $psn->nama }}</h2>
                <p class="label mt-1">Periode {{ $namaBulan }} · cut-off {{ $periode->tanggal_cutoff->translatedFormat('j F Y') }} · batas pengisian
                    <span @class(['font-semibold', 'text-red-700' => $batas->isPast()])>{{ $batas->translatedFormat('j F Y') }}</span></p>
                <a href="{{ route('proyek.show', $psn) }}?tab=progres" class="label text-aksen-700 hover:underline">Lihat detail proyek</a>
            </div>
            <div class="text-right">
                <span class="badge-{{ $status }}"><span class="titik-{{ $status }}"></span>{{ PengisianService::STATUS[$status] }}</span>
                @if ($pengisian?->diajukan_at)<p class="label mt-1">Diajukan {{ $pengisian->diajukan_at->translatedFormat('j M Y H:i') }}</p>@endif
                @if ($pengisian?->diverifikasi_at)<p class="label">Ditinjau {{ $pengisian->diverifikasi_at->translatedFormat('j M Y H:i') }}</p>@endif
            </div>
        </section>

        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())
            <div class="rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert">
                <p class="font-semibold">Isian belum dapat disimpan:</p>
                <ul class="ml-5 list-disc">@foreach (array_unique($errors->all()) as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($status === 'DIKEMBALIKAN' && $pengisian?->catatan_verifikator)
            <div class="rounded-kartu border border-red-200 bg-red-50 p-4 text-red-900" role="alert">
                <p class="font-semibold">Dikembalikan oleh verifikator</p>
                <p class="mt-1 whitespace-pre-line">{{ $pengisian->catatan_verifikator }}</p>
            </div>
        @endif
        @if ($periode->isTerbit())
            <div class="rounded-kartu border border-slate-200 bg-slate-50 p-3 text-slate-700" role="status">Periode ini sudah diterbitkan; isian terkunci dan hanya dapat dibaca.</div>
        @elseif (! $bolehIsi && in_array($status, ['DIAJUKAN', 'DIVERIFIKASI'], true))
            <div class="rounded-kartu border border-slate-200 bg-slate-50 p-3 text-slate-700" role="status">Isian berstatus {{ PengisianService::STATUS[$status] }}; perubahan hanya dapat dilakukan setelah dikembalikan verifikator.</div>
        @endif

        <form method="POST" action="{{ route('pengisian.simpan', [$periode->kode, $psn]) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            {{-- A. Progres & anggaran KP/RO --}}
            <fieldset class="kartu min-w-0" @disabled(! $bolehIsi)>
                <legend class="sr-only">Progres dan anggaran KP/RO</legend>
                <h3 class="judul-panel">A. Progres Fisik & Anggaran KP/RO — {{ $namaBulan }}</h3>
                <p class="label mb-3">Rencana dan realisasi progres fisik diisi kumulatif s.d. akhir bulan (%). Realisasi anggaran diisi untuk bulan ini saja (Rp); kumulatif dihitung otomatis.</p>
                @if ($ro)
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1100px] text-left text-isi">
                            <thead class="border-b border-slate-200 text-label text-slate-600">
                                <tr><th class="py-2 pr-3">KP/RO</th><th class="w-24 py-2 pr-3">Rencana (%)</th><th class="w-28 py-2 pr-3">Realisasi (%)</th><th class="w-44 py-2 pr-3">Realisasi anggaran bulan ini (Rp)</th><th class="py-2 pr-3">Permasalahan</th><th class="w-52 py-2">Bukti dukung</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 align-top">
                                @foreach ($ro as $k)
                                    @php($n = "ro[{$k['id']}]")
                                    <tr>
                                        <td class="max-w-[22rem] py-2 pr-3">
                                            <p>{{ $k['nama'] }} @if ($k['is_critical_path'])<span class="ml-1 rounded bg-aksen-50 px-1.5 text-label text-aksen-700">Critical path</span>@endif</p>
                                            <p class="label">Target {{ $k['target_volume'] !== null ? rtrim(rtrim(number_format((float) $k['target_volume'], 2, ',', '.'), '0'), ',').' '.$k['satuan'] : '–' }} · Pagu {{ $rp($k['pagu_rp']) }}</p>
                                        </td>
                                        <td class="py-2 pr-3"><input type="number" step="0.01" min="0" max="100" name="{{ $n }}[rencana_persen]" value="{{ old("ro.{$k['id']}.rencana_persen", $k['rencana_persen']) }}" class="{{ $inp }}" aria-label="Rencana kumulatif {{ $k['nama'] }}"></td>
                                        <td class="py-2 pr-3">
                                            <input type="number" step="0.01" min="0" max="100" name="{{ $n }}[realisasi_persen]" value="{{ old("ro.{$k['id']}.realisasi_persen", $k['realisasi_persen']) }}" class="{{ $inp }}" aria-label="Realisasi kumulatif {{ $k['nama'] }}">
                                            <p class="label mt-1">{{ $k['bulan_lalu'] ? 'Bulan '.$k['bulan_lalu'].': '.$pct($k['realisasi_lalu']) : 'Belum ada laporan sebelumnya' }}</p>
                                        </td>
                                        <td class="py-2 pr-3">
                                            <input type="number" step="1" min="0" name="{{ $n }}[realisasi_anggaran_rp]" value="{{ old("ro.{$k['id']}.realisasi_anggaran_rp", $k['realisasi_anggaran_rp'] !== null ? (int) $k['realisasi_anggaran_rp'] : null) }}" class="{{ $inp }}" aria-label="Realisasi anggaran bulan ini {{ $k['nama'] }}">
                                            <p class="label mt-1">s.d. bulan lalu {{ $rp($k['anggaran_sd_bulan_lalu']) }}</p>
                                        </td>
                                        <td class="py-2 pr-3"><textarea name="{{ $n }}[permasalahan]" rows="2" maxlength="2000" class="{{ $inp }}" aria-label="Permasalahan {{ $k['nama'] }}">{{ old("ro.{$k['id']}.permasalahan", $k['permasalahan']) }}</textarea></td>
                                        <td class="py-2">
                                            @if ($k['bukti'])<a href="{{ \Illuminate\Support\Facades\Storage::url($k['bukti']) }}" target="_blank" rel="noopener" class="label text-aksen-700 hover:underline">Lihat bukti tersimpan</a>@endif
                                            @if ($bolehIsi)<input type="file" name="bukti[{{ $k['id'] }}]" accept="{{ collect($cfg['bukti_ekstensi'])->map(fn ($e) => '.'.$e)->implode(',') }}" class="mt-1 block w-full text-label" aria-label="Unggah bukti {{ $k['nama'] }}">@endif
                                            @if ($k['dilaporkan_at'])<p class="label mt-1">Dilaporkan {{ $k['dilaporkan_at']->translatedFormat('j M Y H:i') }}</p>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="label mt-2">Bukti: {{ strtoupper(implode('/', $cfg['bukti_ekstensi'])) }}, maks. {{ $cfg['bukti_maks_kb'] / 1024 }} MB per berkas.</p>
                @else
                    <p class="text-slate-500">Belum ada KP/RO bertarget pada tahun {{ $tahun }}. Target KP/RO ditetapkan melalui data perencanaan.</p>
                @endif
            </fieldset>

            {{-- B. Pemantauan risiko --}}
            <fieldset class="kartu min-w-0" @disabled(! $bolehRisiko)>
                <legend class="sr-only">Pemantauan risiko</legend>
                <h3 class="judul-panel">B. Pemantauan Risiko (Risiko Aktual)</h3>
                <p class="label mb-3">Nilai kemungkinan dan dampak aktual 1–5 per akhir periode; level dihitung otomatis (skor = kemungkinan × dampak).</p>
                @if ($risiko)
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1000px] text-left text-isi">
                            <thead class="border-b border-slate-200 text-label text-slate-600">
                                <tr><th class="py-2 pr-3">Risiko</th><th class="py-2 pr-3">Harapan</th><th class="w-24 py-2 pr-3">Kemungkinan</th><th class="w-24 py-2 pr-3">Dampak</th><th class="w-44 py-2 pr-3">Status perlakuan</th><th class="py-2">Catatan</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 align-top">
                                @foreach ($risiko as $x)
                                    @php($n = "risiko[{$x['id']}]")
                                    <tr>
                                        <td class="max-w-[22rem] py-2 pr-3">
                                            <p>{{ $x['uraian'] }}</p>
                                            <p class="label">{{ $x['kategori'] ?? 'Tanpa kategori' }}{{ $x['lalu'] ? ' · terakhir '.$x['lalu']['tanggal'].': '.($x['lalu']['level'] ?? '–') : '' }}</p>
                                            @if ($x['level_aktual'])<span class="{{ $kelasLevel($x['level_aktual']) }} mt-1">Aktual: {{ $x['level_aktual'] }}</span>@endif
                                        </td>
                                        <td class="whitespace-nowrap py-2 pr-3"><span class="{{ $kelasLevel($x['level_harapan']) }}">{{ $x['level_harapan'] ?? 'Belum dinilai' }}{{ $x['kemungkinan_harapan'] ? ' ('.$x['kemungkinan_harapan'].'×'.$x['dampak_harapan'].')' : '' }}</span></td>
                                        @foreach (['kemungkinan_aktual' => 'Kemungkinan', 'dampak_aktual' => 'Dampak'] as $kol => $lbl)
                                            <td class="py-2 pr-3">
                                                <select name="{{ $n }}[{{ $kol }}]" class="{{ $inp }}" aria-label="{{ $lbl }} aktual: {{ \Illuminate\Support\Str::limit($x['uraian'], 60) }}">
                                                    <option value="">–</option>
                                                    @foreach (range(1, 5) as $v)<option value="{{ $v }}" @selected((string) old("risiko.{$x['id']}.{$kol}", $x[$kol]) === (string) $v)>{{ $v }}</option>@endforeach
                                                </select>
                                            </td>
                                        @endforeach
                                        <td class="py-2 pr-3">
                                            <select name="{{ $n }}[status_perlakuan]" class="{{ $inp }}" aria-label="Status perlakuan">
                                                <option value="">–</option>
                                                @foreach ($cfg['status_perlakuan'] as $v => $l)<option value="{{ $v }}" @selected(old("risiko.{$x['id']}.status_perlakuan", $x['status_perlakuan']) === $v)>{{ $l }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="py-2"><textarea name="{{ $n }}[catatan]" rows="2" maxlength="2000" class="{{ $inp }}" aria-label="Catatan pemantauan">{{ old("risiko.{$x['id']}.catatan", $x['catatan']) }}</textarea></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-slate-500">Belum ada risiko terdaftar untuk PSN ini.</p>
                @endif
                @if ($bolehRisiko)
                    <details class="mt-4 rounded-lg border border-slate-200 p-3" @if (old('risiko_baru.uraian')) open @endif>
                        <summary class="cursor-pointer font-semibold text-primer">+ Tambah risiko baru</summary>
                        <div class="mt-3 grid grid-cols-12 gap-3">
                            <label class="col-span-12 lg:col-span-6"><span class="label">Uraian risiko</span><textarea name="risiko_baru[uraian]" rows="2" maxlength="2000" class="{{ $inp }}">{{ old('risiko_baru.uraian') }}</textarea></label>
                            <label class="col-span-12 sm:col-span-6 lg:col-span-3"><span class="label">Kategori</span>
                                <select name="risiko_baru[kategori_id]" class="{{ $inp }}"><option value="">–</option>@foreach ($kategori_risiko as $id => $nm)<option value="{{ $id }}" @selected((string) old('risiko_baru.kategori_id') === (string) $id)>{{ $nm }}</option>@endforeach</select>
                            </label>
                            @foreach (['kemungkinan_harapan' => 'Kemungkinan (harapan)', 'dampak_harapan' => 'Dampak (harapan)'] as $kol => $lbl)
                                <label class="col-span-6 lg:col-span-3 xl:col-span-1"><span class="label">{{ $lbl }}</span>
                                    <select name="risiko_baru[{{ $kol }}]" class="{{ $inp }}"><option value="">–</option>@foreach (range(1, 5) as $v)<option value="{{ $v }}" @selected((string) old("risiko_baru.{$kol}") === (string) $v)>{{ $v }}</option>@endforeach</select>
                                </label>
                            @endforeach
                            <label class="col-span-12 lg:col-span-6"><span class="label">Rencana perlakuan</span><textarea name="risiko_baru[rencana_perlakuan]" rows="2" maxlength="2000" class="{{ $inp }}">{{ old('risiko_baru.rencana_perlakuan') }}</textarea></label>
                            <label class="col-span-12 lg:col-span-6"><span class="label">Penanggung jawab</span><input name="risiko_baru[penanggung_jawab]" value="{{ old('risiko_baru.penanggung_jawab') }}" maxlength="500" class="{{ $inp }}"></label>
                        </div>
                    </details>
                @endif
            </fieldset>

            {{-- C. Isu & tindak lanjut --}}
            <fieldset class="kartu min-w-0" @disabled(! $bolehRisiko)>
                <legend class="sr-only">Isu dan tindak lanjut</legend>
                <h3 class="judul-panel">C. Isu & Tindak Lanjut</h3>
                <p class="label mb-3">Isu terbuka serta isu yang selesai pada bulan ini. Tetapkan PIC dan tenggat agar dapat dipantau pada halaman Risiko, Isu & Regulasi.</p>
                @if ($isu)
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1000px] text-left text-isi">
                            <thead class="border-b border-slate-200 text-label text-slate-600">
                                <tr><th class="py-2 pr-3">Isu</th><th class="w-44 py-2 pr-3">PIC</th><th class="w-40 py-2 pr-3">Tenggat</th><th class="w-40 py-2 pr-3">Status</th><th class="py-2">Tindak lanjut</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 align-top">
                                @foreach ($isu as $i)
                                    @php($n = "isu[{$i['id']}]")
                                    <tr @class(['bg-red-50/60' => $i['lewat_tenggat']])>
                                        <td class="max-w-[24rem] py-2 pr-3"><p>{{ $i['uraian'] }}</p>@if ($i['kebutuhan_dukungan'])<p class="label">Dukungan: {{ $i['kebutuhan_dukungan'] }}</p>@endif
                                            @if ($i['lewat_tenggat'])<span class="badge mt-1 bg-red-50 text-red-800 ring-red-600/40">Lewat tenggat</span>@endif</td>
                                        <td class="py-2 pr-3"><input name="{{ $n }}[pic_nama]" value="{{ old("isu.{$i['id']}.pic_nama", $i['pic_nama']) }}" maxlength="255" class="{{ $inp }}" aria-label="PIC isu"></td>
                                        <td class="py-2 pr-3"><input type="date" name="{{ $n }}[tenggat]" value="{{ old("isu.{$i['id']}.tenggat", $i['tenggat']) }}" class="{{ $inp }}" aria-label="Tenggat isu"></td>
                                        <td class="py-2 pr-3">
                                            <select name="{{ $n }}[status]" class="{{ $inp }}" aria-label="Status isu">
                                                @foreach ($cfg['status_isu'] as $v => $l)<option value="{{ $v }}" @selected(old("isu.{$i['id']}.status", $i['status']) === $v)>{{ $l }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="py-2"><textarea name="{{ $n }}[tindak_lanjut]" rows="2" maxlength="2000" class="{{ $inp }}" aria-label="Tindak lanjut isu">{{ old("isu.{$i['id']}.tindak_lanjut", $i['tindak_lanjut']) }}</textarea></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-slate-500">Tidak ada isu terbuka.</p>
                @endif
                @if ($bolehRisiko)
                    <details class="mt-4 rounded-lg border border-slate-200 p-3" @if (old('isu_baru.uraian')) open @endif>
                        <summary class="cursor-pointer font-semibold text-primer">+ Tambah isu baru</summary>
                        <div class="mt-3 grid grid-cols-12 gap-3">
                            <label class="col-span-12 lg:col-span-6"><span class="label">Uraian isu</span><textarea name="isu_baru[uraian]" rows="2" maxlength="2000" class="{{ $inp }}">{{ old('isu_baru.uraian') }}</textarea></label>
                            <label class="col-span-12 lg:col-span-6"><span class="label">Kebutuhan dukungan</span><textarea name="isu_baru[kebutuhan_dukungan]" rows="2" maxlength="2000" class="{{ $inp }}">{{ old('isu_baru.kebutuhan_dukungan') }}</textarea></label>
                            <label class="col-span-12 sm:col-span-6"><span class="label">PIC</span><input name="isu_baru[pic_nama]" value="{{ old('isu_baru.pic_nama') }}" maxlength="255" class="{{ $inp }}"></label>
                            <label class="col-span-12 sm:col-span-6"><span class="label">Tenggat</span><input type="date" name="isu_baru[tenggat]" value="{{ old('isu_baru.tenggat') }}" class="{{ $inp }}"></label>
                        </div>
                    </details>
                @endif
            </fieldset>

            @if ($bolehIsi)
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <p class="label mr-auto">Simpan draf dapat dilakukan berulang. Setelah diajukan, isian terkunci sampai diverifikasi atau dikembalikan.</p>
                    <button type="submit" class="tombol-garis">Simpan draf</button>
                    <button type="submit" name="ajukan" value="1" class="tombol-primer" x-data @click="confirm('Simpan dan ajukan isian ini untuk verifikasi?') || $event.preventDefault()">Simpan & ajukan verifikasi</button>
                </div>
            @endif
        </form>

        @if ($bolehVerifikasi)
            <section class="kartu border-amber-300 ring-1 ring-amber-200" aria-labelledby="h-verif">
                <h3 id="h-verif" class="judul-panel mb-2">Verifikasi Isian</h3>
                <form method="POST" action="{{ route('pengisian.verifikasi', [$periode->kode, $psn]) }}" class="space-y-3">
                    @csrf
                    <label class="block"><span class="label">Catatan verifikator (wajib bila dikembalikan)</span>
                        <textarea name="catatan" rows="3" maxlength="2000" class="{{ $inp }}">{{ old('catatan') }}</textarea></label>
                    <div class="flex justify-end gap-3">
                        <button type="submit" name="keputusan" value="kembalikan" class="tombol-garis text-red-700">Kembalikan untuk perbaikan</button>
                        <button type="submit" name="keputusan" value="setuju" class="tombol-primer">Verifikasi</button>
                    </div>
                </form>
            </section>
        @endif

        <section class="kartu" aria-labelledby="h-riwayat">
            <h3 id="h-riwayat" class="judul-panel mb-2">Riwayat Status</h3>
            <ol class="divide-y divide-slate-100">
                @forelse ($riwayat as $h)
                    <li class="flex flex-wrap items-center gap-2 py-2">
                        <span class="badge-{{ $h->ke_status }}"><span class="titik-{{ $h->ke_status }}"></span>{{ PengisianService::STATUS[$h->ke_status] ?? $h->ke_status }}</span>
                        <span class="text-isi">{{ $h->pengguna ?? 'Sistem' }}</span>
                        <span class="label">{{ \Illuminate\Support\Carbon::parse($h->created_at)->translatedFormat('j M Y H:i') }}</span>
                        @if ($h->catatan)<p class="w-full whitespace-pre-line text-label text-slate-700">{{ $h->catatan }}</p>@endif
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Belum ada isian untuk periode ini.</li>
                @endforelse
            </ol>
        </section>
    </div>
</x-app-layout>
