@use('App\Models\UsulanPsn')
<x-app-layout judul="Sidang Pleno" subjudul="Pembahasan hasil penilaian usulan PSN dan pencatatan keputusan">
    <x-slot:aksi><a href="{{ route('perencanaan.index') }}" class="tombol-garis py-1.5">← Daftar usulan</a></x-slot:aksi>
    <div class="mx-auto grid max-w-[1600px] grid-cols-12 gap-5">
        @if (session('status'))<div class="col-span-12 rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif
        <section @class(['kartu col-span-12', 'xl:col-span-8' => Gate::allows('kelola', UsulanPsn::class)]) aria-labelledby="h-daftar">
            <h2 id="h-daftar" class="judul-panel mb-3">Daftar Sidang Pleno</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600"><tr><th class="py-2 pr-3">Sidang</th><th class="py-2 pr-3">Tanggal</th><th class="py-2 pr-3">Tahun RKP</th><th class="py-2 pr-3 text-right">Usulan dibahas</th><th class="py-2">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($pleno as $p)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2 pr-3"><a href="{{ route('perencanaan.pleno.show', $p) }}" class="font-medium text-aksen-700 hover:underline">{{ $p->nama }}</a>@if ($p->tempat)<span class="block text-[11px] text-slate-500">{{ $p->tempat }}</span>@endif</td>
                                <td class="py-2 pr-3">{{ $p->tanggal->translatedFormat('j M Y') }}</td>
                                <td class="py-2 pr-3">{{ $p->tahun_rkp ?? '–' }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ $p->diputus_count }} / {{ $p->agenda_count }} diputus</td>
                                <td class="py-2"><span class="badge {{ $p->isSelesai() ? 'bg-green-50 text-green-800 ring-green-600/40' : 'bg-sky-50 text-sky-900 ring-sky-600/40' }}">{{ $p->isSelesai() ? 'Selesai' : 'Terjadwal' }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Belum ada sidang pleno.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $pleno->links() }}</div>
        </section>
        @can('kelola', UsulanPsn::class)
            <section class="kartu col-span-12 xl:col-span-4" aria-labelledby="h-baru">
                <h2 id="h-baru" class="judul-panel mb-3">Jadwalkan Sidang Pleno</h2>
                @if ($errors->any())<div class="mb-3 rounded-lg bg-red-50 p-2 text-[12px] text-red-800" role="alert">{{ implode(' ', $errors->all()) }}</div>@endif
                <form method="POST" action="{{ route('perencanaan.pleno.store') }}" class="space-y-3">
                    @csrf
                    <label class="block"><span class="label">Nama sidang</span><input name="nama" required maxlength="255" value="{{ old('nama', 'Rapat Pleno Penilaian Usulan PSN RKP '.(now()->year + 1)) }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block"><span class="label">Tanggal</span><input type="date" name="tanggal" required value="{{ old('tanggal', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                        <label class="block"><span class="label">Tahun RKP</span><input type="number" name="tahun_rkp" min="2024" max="2100" value="{{ old('tahun_rkp', now()->year + 1) }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                    </div>
                    <label class="block"><span class="label">Tempat</span><input name="tempat" maxlength="255" value="{{ old('tempat') }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                    <label class="block"><span class="label">Pimpinan sidang</span><input name="pimpinan" maxlength="255" value="{{ old('pimpinan') }}" class="mt-1 w-full rounded-lg border-slate-300 text-isi"></label>
                    <button class="tombol-primer w-full justify-center">Jadwalkan</button>
                </form>
            </section>
        @endcan
    </div>
</x-app-layout>
