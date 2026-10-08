<x-app-layout judul="Pengaturan">
    <div class="mx-auto max-w-[1376px]">
        @include('pengaturan._tab')
        <section class="kartu" aria-labelledby="h-pengguna">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <h2 id="h-pengguna" class="judul-panel mr-auto">Pengguna <span class="label font-normal">{{ $pengguna->total() }} akun</span></h2>
                <label><span class="label block">Cari</span><input type="search" name="q" value="{{ $filter['q'] ?? '' }}" placeholder="Nama, username, email" class="mt-1 w-56 rounded-lg border-slate-300 py-1.5 text-isi"></label>
                <label><span class="label block">Peran</span><select name="peran" class="mt-1 rounded-lg border-slate-300 py-1.5 text-isi"><option value="">Semua</option>@foreach ($peran as $p)<option @selected(($filter['peran'] ?? '') === $p)>{{ $p }}</option>@endforeach</select></label>
                <label><span class="label block">Status</span><select name="aktif" class="mt-1 rounded-lg border-slate-300 py-1.5 text-isi"><option value="">Semua</option><option value="1" @selected(($filter['aktif'] ?? '') === '1')>Aktif</option><option value="0" @selected(($filter['aktif'] ?? '') === '0')>Nonaktif</option></select></label>
                <button class="tombol-garis py-1.5">Terapkan</button>
                <a href="{{ route('pengaturan.pengguna.create') }}" class="tombol-primer py-1.5">+ Pengguna baru</a>
            </form>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600"><tr><th class="py-2 pr-3">Nama</th><th class="py-2 pr-3">Username</th><th class="py-2 pr-3">Peran</th><th class="py-2 pr-3">Unit kerja (cakupan)</th><th class="py-2 pr-3">Login terakhir</th><th class="py-2">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($pengguna as $u)
                            <tr class="hover:bg-slate-50">
                                <td class="py-2 pr-3"><a href="{{ route('pengaturan.pengguna.edit', $u) }}" class="font-medium text-aksen-700 hover:underline">{{ $u->name }}</a><div class="label">{{ $u->email }}</div></td>
                                <td class="py-2 pr-3">{{ $u->username }}</td>
                                <td class="py-2 pr-3">{{ $u->roles->pluck('name')->implode(', ') ?: '–' }}</td>
                                <td class="max-w-[16rem] truncate py-2 pr-3" title="{{ $u->unitKerja?->nama }}">{{ $u->unitKerja?->nama ?? '–' }}</td>
                                <td class="py-2 pr-3">{{ $u->last_login_at?->translatedFormat('j M Y') ?? 'belum pernah' }}</td>
                                <td class="py-2">
                                    <span @class(['badge', 'bg-green-50 text-green-800 ring-green-600/40' => $u->is_active, 'bg-slate-100 text-slate-700 ring-slate-400/40' => ! $u->is_active])>{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                    @if ($u->wajib_ganti_password)<span class="badge bg-amber-50 text-amber-900 ring-amber-600/40">Wajib ganti sandi</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $pengguna->links() }}</div>
        </section>
    </div>
</x-app-layout>
