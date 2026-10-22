@use('App\Models\UsulanPsn')
<div class="grid grid-cols-12 gap-5">
    <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-pleno">
        <h3 id="h-pleno" class="judul-panel">Pembahasan Pleno</h3>
        <p class="label mb-4 font-normal text-slate-500">Keputusan dan catatan sidang pleno atas hasil penilaian usulan ini.</p>
        @forelse ($usulan->plenoUsulan as $a)
            <article class="mb-3 rounded-lg border border-slate-200 p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('perencanaan.pleno.show', $a->pleno_id) }}" class="font-semibold text-aksen-700 hover:underline">{{ $a->pleno->nama }}</a>
                    <span class="label">{{ $a->pleno->tanggal->translatedFormat('j F Y') }}</span>
                    <span class="badge {{ $a->pleno->isSelesai() ? 'bg-green-50 text-green-800 ring-green-600/40' : 'bg-sky-50 text-sky-900 ring-sky-600/40' }}">{{ $a->pleno->isSelesai() ? 'Sidang selesai' : 'Terjadwal' }}</span>
                    <span class="ml-auto">
                        @if ($a->keputusan)
                            <span @class(['badge', 'bg-green-50 text-green-800 ring-green-600/40' => str_starts_with($a->keputusan, 'DISETUJUI'), 'bg-amber-50 text-amber-900 ring-amber-600/40' => $a->keputusan === 'DITUNDA', 'bg-red-50 text-red-800 ring-red-600/40' => $a->keputusan === 'DITOLAK'])>{{ $a->labelKeputusan() }}</span>
                        @else<span class="badge bg-slate-100 text-slate-700 ring-slate-400/40">Belum diputus</span>@endif
                    </span>
                </div>
                <p class="label mt-1">Dasar: penilaian {{ $a->penilaian?->forum ?? '#'.$a->penilaian_id }} · nilai akhir {{ $a->penilaian?->nilai_akhir !== null ? number_format($a->penilaian->nilai_akhir, 2, ',', '.') : '–' }}</p>
                @if ($a->catatan)<div class="mt-2"><p class="label">Catatan pleno</p><p class="whitespace-pre-line text-isi">{{ $a->catatan }}</p></div>@endif
                @if ($a->tindak_lanjut)<div class="mt-2"><p class="label">Tindak lanjut</p><p class="whitespace-pre-line text-isi">{{ $a->tindak_lanjut }}</p></div>@endif
            </article>
        @empty
            <p class="text-slate-500">Usulan ini belum masuk agenda pleno.</p>
        @endforelse
    </section>
    <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-agenda">
        <h3 id="h-agenda" class="judul-panel mb-2">Masukkan ke Agenda Pleno</h3>
        @if (! $penilaianFinal)
            <p class="rounded-lg bg-amber-50 p-3 text-[12px] text-amber-900">Penilaian harus ditetapkan FINAL sebelum usulan dapat dibahas di pleno.</p>
        @elseif (Gate::allows('kelola', UsulanPsn::class))
            @if ($plenoTerbuka->isEmpty())
                <p class="text-[12px] text-slate-600">Belum ada sidang pleno terjadwal. <a href="{{ route('perencanaan.pleno.index') }}" class="text-aksen-700 hover:underline">Jadwalkan sidang pleno</a>.</p>
            @else
                <form method="POST" x-data="{ pleno: '{{ $plenoTerbuka->first()->id }}' }" :action="'{{ url('/perencanaan/pleno') }}/' + pleno + '/agenda'" class="space-y-3">
                    @csrf <input type="hidden" name="usulan_id[]" value="{{ $usulan->id }}">
                    <label class="block"><span class="label">Sidang pleno</span>
                        <select x-model="pleno" class="mt-1 w-full rounded-lg border-slate-300 text-isi">
                            @foreach ($plenoTerbuka as $p)<option value="{{ $p->id }}">{{ $p->nama }} · {{ $p->tanggal->format('d/m/Y') }}</option>@endforeach
                        </select></label>
                    <button class="tombol-primer w-full justify-center">Tambahkan ke agenda</button>
                </form>
            @endif
        @else
            <p class="text-[12px] text-slate-600">Penyusunan agenda pleno dilakukan oleh Tim Koordinasi/PMO.</p>
        @endif
    </section>
</div>
