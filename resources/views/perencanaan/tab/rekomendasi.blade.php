@use('App\Enums\Rekomendasi')
@use('App\Models\UsulanPsn')
@use('App\Http\Controllers\RekomendasiController')
@php
    $psi = $usulan->psi;
    $rekResmi = $usulan->rekomendasiFinal;
    $draf = $usulan->rekomendasiResmi->firstWhere('status', 'DRAFT');
    $plenoPutus = $usulan->plenoUsulan->firstWhere(fn ($a) => $a->keputusan);
    $bolehKelola = Gate::allows('kelola', UsulanPsn::class);
    $sistem = $hasilFinal['rekomendasi'] ?? null;
    $usul = $draf?->rekomendasi ?? match ($plenoPutus?->keputusan) { 'DISETUJUI', 'DISETUJUI_CATATAN' => 'DIREKOMENDASIKAN', 'DITOLAK' => 'TIDAK_DIREKOMENDASIKAN', 'DITUNDA' => 'DIPERTIMBANGKAN', default => $sistem?->value };
@endphp
<div class="grid grid-cols-12 gap-5">
    {{-- Dasar rekomendasi --}}
    <section class="kartu col-span-12 lg:col-span-4" aria-labelledby="h-dasar">
        <h3 id="h-dasar" class="judul-panel mb-3">Dasar Rekomendasi</h3>
        <dl class="space-y-3 text-isi">
            <div><dt class="label">Penilaian FINAL</dt>
                <dd>@if ($penilaianFinal){{ $penilaianFinal->forum }} · {{ $penilaianFinal->tanggal?->format('d/m/Y') }}<br>
                    Nilai akhir <b class="angka">{{ $hasilFinal['nilai_akhir'] !== null ? number_format($hasilFinal['nilai_akhir'], 2, ',', '.') : '–' }}</b> ·
                    Gate {{ $hasilFinal['gate']['lulus'] === false ? 'GUGUR ('.implode(', ', $hasilFinal['gate']['gagal']).')' : ($hasilFinal['gate']['lulus'] ? 'lulus' : 'belum lengkap') }}
                    <br><span class="{{ $sistem->badge() }} mt-1">Hitungan sistem: {{ $sistem->label() }}</span>
                @else<span class="text-amber-800">Belum ada penilaian FINAL.</span>@endif</dd></div>
            <div><dt class="label">Keputusan pleno</dt>
                <dd>@if ($plenoPutus){{ $plenoPutus->labelKeputusan() }} — {{ $plenoPutus->pleno->nama }}
                    @if ($plenoPutus->catatan)<p class="mt-1 whitespace-pre-line text-[12px] text-slate-600">{{ \Illuminate\Support\Str::limit($plenoPutus->catatan, 300) }}</p>@endif
                @else<span class="{{ $wajibPleno ? 'text-amber-800' : 'text-slate-500' }}">Belum ada keputusan pleno{{ $wajibPleno ? ' (wajib sebelum rekomendasi).' : '.' }}</span>@endif</dd></div>
        </dl>
    </section>

    {{-- Penetapan rekomendasi resmi --}}
    <section class="kartu col-span-12 lg:col-span-8" aria-labelledby="h-rek">
        <h3 id="h-rek" class="judul-panel">Rekomendasi Resmi</h3>
        @if ($rekResmi)
            <div class="mt-2 rounded-lg border border-green-200 bg-green-50 p-3">
                <span class="{{ $rekResmi->enum()->badge() }}">{{ $rekResmi->enum()->label() }}</span>
                <span class="label ml-2">ditetapkan {{ $rekResmi->ditetapkan_at?->translatedFormat('j M Y H:i') }} oleh {{ $rekResmi->penetap?->name ?? '–' }}{{ $rekResmi->nomor_dokumen ? ' · No. '.$rekResmi->nomor_dokumen : '' }}</span>
                @if ($rekResmi->catatan)<p class="mt-2 whitespace-pre-line text-isi">{{ $rekResmi->catatan }}</p>@endif
            </div>
        @endif
        @if ($bolehKelola && $penilaianFinal && (! $wajibPleno || $plenoPutus))
            <form method="POST" action="{{ route('perencanaan.rekomendasi', $usulan) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <label class="block"><span class="label">Rekomendasi</span>
                    <select name="rekomendasi" required class="mt-1 w-full rounded-lg border-slate-300 text-isi">
                        @foreach (RekomendasiController::PILIHAN as $p)<option value="{{ $p->value }}" @selected(old('rekomendasi', $usul) === $p->value)>{{ $p->label() }}</option>@endforeach
                    </select></label>
                <label class="block"><span class="label">Tanggal</span><input type="date" name="tanggal" required value="{{ old('tanggal', $draf?->tanggal?->toDateString() ?? now()->toDateString()) }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                <label class="block md:col-span-2"><span class="label">Nomor dokumen / berita acara (opsional)</span><input name="nomor_dokumen" maxlength="150" value="{{ old('nomor_dokumen', $draf?->nomor_dokumen) }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                <label class="block md:col-span-2"><span class="label">Catatan rekomendasi (dikirim ke PSI)</span><textarea name="catatan" rows="4" maxlength="10000" class="mt-1 w-full rounded-lg border-slate-300 text-isi">{{ old('catatan', $draf?->catatan ?? $plenoPutus?->catatan) }}</textarea></label>
                <div class="flex flex-wrap justify-end gap-2 md:col-span-2">
                    <button name="tetapkan" value="0" class="tombol-garis">Simpan draf</button>
                    <button name="tetapkan" value="1" class="tombol-primer" x-data @click="confirm('Tetapkan rekomendasi resmi ini sebagai FINAL?') || $event.preventDefault()">{{ $rekResmi ? 'Tetapkan ulang (ganti FINAL)' : 'Tetapkan FINAL' }}</button>
                </div>
            </form>
        @elseif ($bolehKelola)
            <p class="mt-3 rounded-lg bg-amber-50 p-3 text-[12px] text-amber-900">Rekomendasi dapat ditetapkan setelah penilaian FINAL{{ $wajibPleno ? ' dan keputusan pleno dicatat' : '' }}.</p>
        @elseif (! $rekResmi)
            <p class="mt-3 text-slate-500">Rekomendasi resmi belum ditetapkan.</p>
        @endif

        @if ($usulan->rekomendasiResmi->count() > 1 || $draf)
            <details class="mt-4"><summary class="cursor-pointer text-label text-aksen-700">Riwayat rekomendasi ({{ $usulan->rekomendasiResmi->count() }})</summary>
                <ul class="mt-2 divide-y divide-slate-100 text-[12px]">
                    @foreach ($usulan->rekomendasiResmi as $x)
                        <li class="py-1.5"><b>{{ Rekomendasi::from($x->rekomendasi)->label() }}</b> · {{ $x->status }} · {{ $x->tanggal->format('d/m/Y') }}{{ $x->penetap ? ' · '.$x->penetap->name : '' }}</li>
                    @endforeach
                </ul></details>
        @endif
    </section>

    {{-- Kirim hasil ke PSI --}}
    @if ($dariPsi)
        <section class="kartu col-span-12" aria-labelledby="h-kirim">
            <div class="flex flex-wrap items-start gap-4">
                <div class="mr-auto">
                    <h3 id="h-kirim" class="judul-panel">Kirim Hasil ke Aplikasi PSI</h3>
                    <p class="label font-normal text-slate-500">Skor PMO (nilai akhir penilaian FINAL) dan catatan PMO (rekomendasi resmi, keputusan pleno, temuan per kriteria) dikirim ke SIF untuk usulan <b>{{ $psi->psi_id }}</b>. Catatan ini bersifat internal Bappenas dan tidak ditampilkan kepada pengusul.</p>
                </div>
                <div class="text-right text-isi">
                    <span @class(['badge', 'bg-emerald-100 text-emerald-900 ring-emerald-700/40' => $psi->kirim_status === 'TERKIRIM', 'bg-red-50 text-red-800 ring-red-600/40' => $psi->kirim_status === 'GAGAL', 'bg-slate-100 text-slate-700 ring-slate-400/40' => $psi->kirim_status === 'BELUM'])>
                        {{ ['TERKIRIM' => 'Terkirim', 'GAGAL' => 'Gagal terkirim', 'BELUM' => 'Belum dikirim'][$psi->kirim_status] ?? $psi->kirim_status }}</span>
                    @if ($psi->kirim_terakhir_at)<p class="label mt-1">Terakhir {{ $psi->kirim_terakhir_at->translatedFormat('j M Y H:i') }} · {{ $psi->kirim_percobaan }} percobaan</p>@endif
                </div>
            </div>
            @if (! $pushAktif)
                <p class="mt-3 rounded-lg bg-sky-50 p-3 text-[12px] text-sky-900">{{ app(\App\Services\SifClient::class)->kendala() }} Hubungi admin SIRENDA untuk melengkapi pengaturan API SIF.</p>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-3">
                @if ($bolehKelola)
                    <form method="POST" action="{{ route('perencanaan.kirim-psi', $usulan) }}">@csrf
                        <button class="tombol-primer" @disabled(! $rekResmi || ! $pushAktif) x-data @click="confirm('Kirim hasil ke aplikasi PSI sekarang?') || $event.preventDefault()">{{ $psi->kirim_status === 'BELUM' ? 'Kirim ke PSI' : 'Kirim ulang ke PSI' }}</button>
                    </form>
                    @if (! $rekResmi)<span class="text-[12px] text-amber-800">Tetapkan rekomendasi FINAL terlebih dahulu.</span>@endif
                @endif
                <details class="w-full"><summary class="cursor-pointer text-label text-aksen-700">Pratinjau data yang dikirim (JSON)</summary>
                    <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-slate-900 p-3 text-[11px] text-slate-100">{{ json_encode(app(\App\Services\PsiHasilService::class)->payloadSif($usulan), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details>
            </div>
        </section>
    @endif
</div>
