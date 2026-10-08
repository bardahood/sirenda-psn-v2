@use('App\Services\PengisianService')
<x-app-layout judul="Pengisian & Verifikasi">
    <div class="mx-auto max-w-[1376px] space-y-6">
        @if (session('status'))<div class="rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif

        <section class="flex flex-wrap items-center gap-x-8 gap-y-2 rounded-kartu border border-primer-100 bg-primer-50 px-5 py-3" aria-label="Periode pengisian">
            <form method="GET" class="flex items-center gap-2">
                <label for="periode" class="label">Periode</label>
                <select id="periode" name="periode" x-data @change="$el.form.submit()" class="rounded-lg border-slate-300 py-1 text-isi">
                    @foreach ($daftarPeriode as $k)<option value="{{ $k }}" @selected($k === $periode->kode)>{{ $k }}</option>@endforeach
                </select>
                @isset($filter['status'])<input type="hidden" name="status" value="{{ $filter['status'] }}">@endisset
                @isset($filter['q'])<input type="hidden" name="q" value="{{ $filter['q'] }}">@endisset
                <input type="hidden" name="milik" value="{{ (int) $filter['milik'] }}">
                <noscript><button class="tombol-garis py-1">Pilih</button></noscript>
            </form>
            <div><span class="label">Tanggal cut-off</span> <span class="ml-1 font-semibold text-primer">{{ $periode->tanggal_cutoff->translatedFormat('j F Y') }}</span></div>
            <div><span class="label">Batas pengisian</span> <span @class(['ml-1 font-semibold', 'text-red-700' => $batas->isPast(), 'text-primer' => ! $batas->isPast()])>{{ $batas->translatedFormat('j F Y') }}{{ $batas->isPast() ? ' (terlewati)' : '' }}</span></div>
            @if ($periode->isTerbit())
                <span class="badge bg-slate-100 text-slate-700 ring-slate-500/40">Periode sudah diterbitkan — hanya baca</span>
            @endif
        </section>

        <section class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5" aria-label="Rekap status pengisian">
            @foreach (PengisianService::STATUS as $kode => $label)
                <a href="{{ route('pengisian.index', array_filter(['periode' => $periode->kode, 'status' => ($filter['status'] ?? null) === $kode ? null : $kode, 'milik' => (int) $filter['milik']], fn ($v) => $v !== null)) }}"
                   @class(['kartu block hover:ring-2 hover:ring-aksen/40', 'ring-2 ring-aksen' => ($filter['status'] ?? null) === $kode]) aria-pressed="{{ ($filter['status'] ?? null) === $kode ? 'true' : 'false' }}">
                    <span class="badge-{{ $kode }}"><span class="titik-{{ $kode }}"></span>{{ $label }}</span>
                    <div class="angka mt-2 text-kpi text-primer">{{ number_format($rekap[$kode], 0, ',', '.') }}</div>
                </a>
            @endforeach
        </section>

        <section class="kartu" aria-labelledby="h-daftar">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="periode" value="{{ $periode->kode }}">
                @isset($filter['status'])<input type="hidden" name="status" value="{{ $filter['status'] }}">@endisset
                <h2 id="h-daftar" class="judul-panel mr-auto">Daftar PSN <span class="label font-normal">{{ $halaman->total() }} PSN aktif · yang perlu tindakan di atas</span></h2>
                <label class="text-isi"><span class="label block">Cari</span>
                    <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100" class="mt-1 w-56 rounded-lg border-slate-300 py-1.5 text-isi" placeholder="Nama atau kode PSN…">
                </label>
                <label class="flex items-center gap-2 pb-2 text-isi">
                    <input type="hidden" name="milik" value="0">
                    <input type="checkbox" name="milik" value="1" @checked($filter['milik']) class="rounded border-slate-300 text-aksen"> Hanya PSN unit saya{{ auth()->user()->unitKerja ? ' ('.auth()->user()->unitKerja->nama.')' : '' }}
                </label>
                <button class="tombol-garis py-1.5">Terapkan</button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">PSN</th><th class="py-2 pr-3">Unit pengampu</th><th class="py-2 pr-3 text-right">KP/RO {{ $periode->tanggal_cutoff->year }}</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Diajukan</th><th class="py-2">Aksi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($halaman as $b)
                            <tr class="hover:bg-slate-50">
                                <td class="max-w-[24rem] py-2 pr-3">{{ $b['nama'] }} @if ($b['kode'])<span class="label">· {{ $b['kode'] }}</span>@endif</td>
                                <td class="max-w-[16rem] py-2 pr-3 text-label text-slate-700">{{ implode(', ', $b['unit']) ?: '–' }}</td>
                                <td class="angka py-2 pr-3 text-right">{{ $b['jumlah_ro'] }}</td>
                                <td class="py-2 pr-3"><span class="badge-{{ $b['status'] }}"><span class="titik-{{ $b['status'] }}"></span>{{ PengisianService::STATUS[$b['status']] }}</span></td>
                                <td class="py-2 pr-3 text-label">{{ $b['diajukan_at'] ? \Illuminate\Support\Carbon::parse($b['diajukan_at'])->translatedFormat('j M Y H:i') : '–' }}</td>
                                <td class="py-2">
                                    <a href="{{ route('pengisian.show', [$periode->kode, $b['id']]) }}" class="font-medium text-aksen-700 hover:underline">
                                        {{ $b['status'] === 'DIAJUKAN' ? 'Tinjau' : (in_array($b['status'], PengisianService::DAPAT_DIUBAH, true) && ! $periode->isTerbit() ? 'Isi' : 'Lihat') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-500">Tidak ada PSN yang sesuai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $halaman->links() }}</div>
        </section>
    </div>
</x-app-layout>
