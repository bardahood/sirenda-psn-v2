<x-app-layout judul="Pengaturan">
    <div class="mx-auto max-w-[1376px]">
        @include('pengaturan._tab')
        <div class="grid grid-cols-12 gap-6">
            <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-bangun">
                <h2 id="h-bangun" class="judul-panel mb-3">Bangun Snapshot</h2>
                <form method="POST" action="{{ route('pengaturan.cutoff.bangun') }}" class="space-y-3">
                    @csrf
                    <label class="block"><span class="label">Periode (YYYY-MM) *</span><input name="kode" value="{{ old('kode', $usulanKode) }}" required pattern="\d{4}-(0[1-9]|1[0-2])" class="mt-1 w-full rounded-lg border-slate-300"></label>
                    <label class="block"><span class="label">Tanggal cut-off (kosong = akhir bulan)</span><input type="date" name="tanggal" value="{{ old('tanggal') }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="terbit" value="1" class="rounded border-slate-300 text-aksen"> Terbitkan (dipakai dashboard & membatalkan cache)</label>
                    <label class="flex items-center gap-2"><input type="checkbox" name="paksa" value="1" class="rounded border-slate-300 text-aksen"> Bangun ulang snapshot yang sudah terbit</label>
                    <button class="tombol-primer w-full justify-center">Bangun snapshot</button>
                    <p class="label">Snapshot membekukan kondisi basis data saat tombol ditekan. Setara dengan <code>php artisan psn:snapshot</code>; setelah terbit, jalankan uji akurasi.</p>
                </form>
            </section>
            <section class="kartu col-span-12 xl:col-span-8" aria-labelledby="h-daftar-cutoff">
                <h2 id="h-daftar-cutoff" class="judul-panel mb-3">Periode Cut-off</h2>
                <table class="w-full text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600"><tr><th class="py-2 pr-3">Periode</th><th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3 text-right">PSN</th><th class="py-2 pr-3">Pengisian</th><th class="py-2 pr-3">Diterbitkan</th><th class="py-2"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($periode as $p)
                            <tr>
                                <td class="py-2 pr-3 font-medium">{{ $p->kode }}</td>
                                <td class="py-2 pr-3">{{ $p->tanggal_cutoff->translatedFormat('j M Y') }}</td>
                                <td class="py-2 pr-3"><span @class(['badge', 'bg-green-50 text-green-800 ring-green-600/40' => $p->isTerbit(), 'bg-slate-100 text-slate-700 ring-slate-400/40' => ! $p->isTerbit()])>{{ $p->isTerbit() ? 'Terbit' : 'Draf' }}</span></td>
                                <td class="angka py-2 pr-3 text-right">{{ $jumlah[$p->id] ?? 0 }}</td>
                                <td class="py-2 pr-3 text-label">{{ collect($pengisian[$p->id] ?? [])->map(fn ($x) => ucfirst(strtolower($x->status)).' '.$x->n)->implode(' · ') ?: '–' }}</td>
                                <td class="py-2 pr-3 text-label">{{ $p->diterbitkan_at?->translatedFormat('j M Y H:i') ?? '–' }}</td>
                                <td class="py-2">
                                    @unless ($p->isTerbit())
                                        <form method="POST" action="{{ route('pengaturan.cutoff.bangun') }}">@csrf<input type="hidden" name="kode" value="{{ $p->kode }}"><input type="hidden" name="tanggal" value="{{ $p->tanggal_cutoff->toDateString() }}"><input type="hidden" name="terbit" value="1"><button class="tombol-garis py-1 text-label">Bangun & terbitkan</button></form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="py-6 text-center text-slate-600">Belum ada cut-off.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>
