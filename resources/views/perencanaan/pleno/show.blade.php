@use('App\Models\UsulanPsn')
@use('App\Enums\Rekomendasi')
@php($bolehKelola = Gate::allows('kelola', UsulanPsn::class) && ! $pleno->isSelesai())
<x-app-layout judul="Sidang Pleno" :subjudul="$pleno->nama.' · '.$pleno->tanggal->translatedFormat('j F Y')">
    <x-slot:aksi><a href="{{ route('perencanaan.pleno.index') }}" class="tombol-garis py-1.5">← Daftar sidang</a></x-slot:aksi>
    <div class="mx-auto max-w-[1600px] space-y-5">
        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

        <section class="kartu flex flex-wrap items-center gap-x-8 gap-y-2">
            <div><span class="label">Tanggal</span> <b>{{ $pleno->tanggal->translatedFormat('j F Y') }}</b></div>
            <div><span class="label">Tempat</span> <b>{{ $pleno->tempat ?: '–' }}</b></div>
            <div><span class="label">Pimpinan sidang</span> <b>{{ $pleno->pimpinan ?: '–' }}</b></div>
            <div><span class="label">Tahun RKP</span> <b>{{ $pleno->tahun_rkp ?? '–' }}</b></div>
            <span class="badge ml-auto {{ $pleno->isSelesai() ? 'bg-green-50 text-green-800 ring-green-600/40' : 'bg-sky-50 text-sky-900 ring-sky-600/40' }}">{{ $pleno->isSelesai() ? 'Sidang selesai — keputusan terkunci' : 'Terjadwal' }}</span>
            @if ($bolehKelola && $pleno->agenda->isNotEmpty())
                <form method="POST" action="{{ route('perencanaan.pleno.selesai', $pleno) }}">@csrf<button class="tombol-garis py-1.5" x-data @click="confirm('Tandai sidang selesai? Keputusan tidak dapat diubah lagi.') || $event.preventDefault()">Tandai sidang selesai</button></form>
            @endif
        </section>

        <form method="POST" action="{{ route('perencanaan.pleno.keputusan', $pleno) }}" class="kartu" aria-labelledby="h-agenda">
            @csrf @method('PUT')
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <h2 id="h-agenda" class="judul-panel mr-auto">Agenda & Keputusan Pleno <span class="label font-normal">{{ $pleno->agenda->count() }} usulan</span></h2>
                @if ($bolehKelola && $pleno->agenda->isNotEmpty())<button class="tombol-primer py-1.5">Simpan keputusan</button>@endif
            </div>
            <div class="space-y-3">
                @forelse ($pleno->agenda as $i => $a)
                    @php($rekSistem = $a->penilaian?->rekomendasi ? Rekomendasi::tryFrom($a->penilaian->rekomendasi) : null)
                    <article class="rounded-lg border border-slate-200 p-4">
                        <div class="flex flex-wrap items-start gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primer text-[12px] font-bold text-white">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('perencanaan.show', [$a->usulan_id, 'tab' => 'penilaian']) }}" class="font-semibold text-aksen-700 hover:underline">{{ $a->usulan?->nama }}</a>
                                <p class="text-[12px] text-slate-600">{{ $a->usulan?->klaster?->nama ?? 'Tanpa klaster' }} · {{ $a->usulan?->pengusul_teks }}{{ $a->usulan?->psi ? ' · PSI '.$a->usulan->psi->psi_id : '' }}</p>
                                <p class="mt-1 text-[12px]">Nilai akhir <b class="angka">{{ $a->penilaian?->nilai_akhir !== null ? number_format($a->penilaian->nilai_akhir, 2, ',', '.') : '–' }}</b>
                                    · Gate {{ $a->penilaian?->gate_lulus === false ? 'gugur' : ($a->penilaian?->gate_lulus ? 'lulus' : '–') }}
                                    @if ($rekSistem)· <span class="{{ $rekSistem->badge() }}">{{ $rekSistem->label() }}</span>@endif</p>
                            </div>
                            @if ($bolehKelola && ! $a->keputusan)
                                <button form="hapus-{{ $a->id }}" class="text-[12px] text-red-700 hover:underline">Keluarkan dari agenda</button>
                            @endif
                        </div>
                        <div class="mt-3 grid gap-3 md:grid-cols-3">
                            <label class="block"><span class="label">Keputusan pleno</span>
                                <select name="agenda[{{ $a->id }}][keputusan]" @disabled(! $bolehKelola) class="mt-1 w-full rounded-lg border-slate-300 text-isi disabled:bg-slate-50">
                                    <option value="">— Belum diputus —</option>
                                    @foreach ($keputusan as $k => $l)<option value="{{ $k }}" @selected(old("agenda.{$a->id}.keputusan", $a->keputusan) === $k)>{{ $l }}</option>@endforeach
                                </select></label>
                            <label class="block md:col-span-2"><span class="label">Catatan pleno (wajib selain "Disetujui")</span>
                                <textarea name="agenda[{{ $a->id }}][catatan]" rows="2" maxlength="5000" @disabled(! $bolehKelola) class="mt-1 w-full rounded-lg border-slate-300 text-isi disabled:bg-slate-50">{{ old("agenda.{$a->id}.catatan", $a->catatan) }}</textarea></label>
                            <label class="block md:col-span-3"><span class="label">Tindak lanjut</span>
                                <textarea name="agenda[{{ $a->id }}][tindak_lanjut]" rows="2" maxlength="5000" @disabled(! $bolehKelola) class="mt-1 w-full rounded-lg border-slate-300 text-isi disabled:bg-slate-50">{{ old("agenda.{$a->id}.tindak_lanjut", $a->tindak_lanjut) }}</textarea></label>
                        </div>
                    </article>
                @empty
                    <p class="py-6 text-center text-slate-500">Agenda masih kosong. Tambahkan usulan yang penilaiannya sudah FINAL.</p>
                @endforelse
            </div>
            @if ($bolehKelola && $pleno->agenda->isNotEmpty())<div class="mt-3 text-right"><button class="tombol-primer">Simpan keputusan</button></div>@endif
        </form>
        @foreach ($pleno->agenda as $a)
            <form id="hapus-{{ $a->id }}" method="POST" action="{{ route('perencanaan.pleno.agenda.hapus', [$pleno, $a]) }}" class="hidden">@csrf @method('DELETE')</form>
        @endforeach

        @if ($bolehKelola)
            <section class="kartu" aria-labelledby="h-tambah">
                <h2 id="h-tambah" class="judul-panel mb-1">Tambahkan Usulan ke Agenda</h2>
                <p class="label mb-3 font-normal text-slate-500">Hanya usulan dengan penilaian FINAL{{ $pleno->tahun_rkp ? ' untuk RKP '.$pleno->tahun_rkp : '' }} yang belum ada di agenda.</p>
                @if ($kandidat->isEmpty())
                    <p class="text-slate-500">Tidak ada usulan yang memenuhi syarat.</p>
                @else
                    <form method="POST" action="{{ route('perencanaan.pleno.agenda', $pleno) }}">
                        @csrf
                        <ul class="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200">
                            @foreach ($kandidat as $u)
                                <li><label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-slate-50">
                                    <input type="checkbox" name="usulan_id[]" value="{{ $u->id }}" class="rounded border-slate-300 text-aksen">
                                    <span class="min-w-0 flex-1 text-isi">{{ $u->nama }}</span>
                                    <span class="angka text-[12px] text-slate-600">nilai {{ $u->penilaianTerakhir?->nilai_akhir !== null ? number_format($u->penilaianTerakhir->nilai_akhir, 2, ',', '.') : '–' }}</span>
                                </label></li>
                            @endforeach
                        </ul>
                        <button class="tombol-primer mt-3">Tambahkan ke agenda</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-app-layout>
