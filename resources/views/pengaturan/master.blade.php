@use('App\Http\Controllers\Pengaturan\MasterDataController')
<x-app-layout judul="Pengaturan">
    <div class="mx-auto max-w-[1376px]">
        @include('pengaturan._tab')
        <p class="mb-4 text-isi text-slate-600">Master data ini adalah satu-satunya sumber opsi filter global (klaster, direktorat) dan cakupan akses pengguna. Setiap perubahan tercatat di jejak audit.</p>
        <div class="grid grid-cols-12 gap-6">
            <section class="kartu col-span-12 xl:col-span-5" aria-labelledby="h-klaster">
                <h2 id="h-klaster" class="judul-panel mb-3">Klaster PSN</h2>
                <ul class="divide-y divide-slate-100">
                    @foreach ($klaster as $k)
                        <li class="py-2">
                            <form method="POST" action="{{ route('pengaturan.master.klaster', $k->id) }}" class="grid grid-cols-[2.5rem_1fr] items-center gap-x-2 gap-y-1.5">
                                @csrf @method('PUT')
                                <span class="text-label text-slate-500">{{ $k->kode }}</span>
                                <label class="sr-only" for="kl-{{ $k->id }}">Nama klaster {{ $k->kode }}</label>
                                <input id="kl-{{ $k->id }}" name="nama" value="{{ $k->nama }}" class="min-w-0 rounded-lg border-slate-300 py-1 text-isi">
                                <div class="col-start-2 flex items-center gap-3">
                                    <label class="flex items-center gap-1 text-label">Urutan <input name="urutan" type="number" value="{{ $k->urutan }}" class="w-20 rounded-lg border-slate-300 py-1 text-isi"></label>
                                    <label class="flex items-center gap-1 text-label"><input type="checkbox" name="is_aktif" value="1" @checked($k->is_aktif) class="rounded border-slate-300 text-aksen"> Aktif</label>
                                    <span class="label ml-auto">{{ $k->jumlah_psn }} PSN</span>
                                    <button class="tombol-garis py-1 text-label">Simpan</button>
                                </div>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <h3 class="judul-panel mb-2 mt-6">Sub Klaster (kandidat "sektor", Q-06)</h3>
                <ul class="divide-y divide-slate-100">
                    @foreach ($subKlaster as $s)
                        <li class="py-2">
                            <form method="POST" action="{{ route('pengaturan.master.sub-klaster', $s->id) }}" class="flex flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <span class="w-8 text-label text-slate-500">{{ $s->kode }}</span>
                                <input name="nama" value="{{ $s->nama }}" class="min-w-0 flex-1 rounded-lg border-slate-300 py-1 text-isi" aria-label="Nama sub klaster {{ $s->kode }}">
                                <select name="klaster_id" class="max-w-[12rem] rounded-lg border-slate-300 py-1 text-label" aria-label="Klaster induk"><option value="">Tanpa induk</option>@foreach ($klasterOpsi as $id => $n)<option value="{{ $id }}" @selected($s->klaster_id === $id)>{{ $n }}</option>@endforeach</select>
                                <button class="tombol-garis py-1 text-label">Simpan</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="kartu col-span-12 xl:col-span-7" aria-labelledby="h-unit">
                <form method="GET" class="mb-3 flex flex-wrap items-end gap-3">
                    <h2 id="h-unit" class="judul-panel mr-auto">Direktorat & Unit Kerja</h2>
                    <label><span class="label block">Jenis</span><select name="jenis" class="mt-1 rounded-lg border-slate-300 py-1.5 text-isi"><option value="">Semua</option>
                        @foreach (MasterDataController::JENIS_UNIT as $k => $l)<option value="{{ $k }}" @selected(($filter['jenis'] ?? '') === $k)>{{ $l }} ({{ $rekapJenis[$k] ?? 0 }})</option>@endforeach</select></label>
                    <label><span class="label block">Cari</span><input type="search" name="q" value="{{ $filter['q'] ?? '' }}" class="mt-1 w-48 rounded-lg border-slate-300 py-1.5 text-isi"></label>
                    <button class="tombol-garis py-1.5">Terapkan</button>
                </form>
                <p class="label mb-2">Jenis unit diisi otomatis dari nama saat impor (heuristik). Mohon kurasi: hanya jenis "Direktorat Bappenas" yang menjadi opsi filter Direktorat dan "sektor" di Kualitas Data.</p>
                <ul class="divide-y divide-slate-100">
                    @foreach ($unit as $u)
                        <li class="py-2">
                            <form method="POST" action="{{ route('pengaturan.master.unit', $u->id) }}" class="flex flex-wrap items-center gap-2">
                                @csrf @method('PUT')
                                <span class="w-10 text-label text-slate-500">{{ $u->kode }}</span>
                                <input name="nama" value="{{ $u->nama }}" class="min-w-0 flex-1 rounded-lg border-slate-300 py-1 text-isi" aria-label="Nama unit {{ $u->kode }}">
                                <select name="jenis" class="rounded-lg border-slate-300 py-1 text-label" aria-label="Jenis unit">@foreach (MasterDataController::JENIS_UNIT as $k => $l)<option value="{{ $k }}" @selected($u->jenis === $k)>{{ $l }}</option>@endforeach</select>
                                <label class="flex items-center gap-1 text-label"><input type="checkbox" name="is_aktif" value="1" @checked($u->is_aktif) class="rounded border-slate-300 text-aksen"> Aktif</label>
                                <span class="label w-14 text-right">{{ $u->jumlah_psn }} PSN</span>
                                <button class="tombol-garis py-1 text-label">Simpan</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">{{ $unit->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
