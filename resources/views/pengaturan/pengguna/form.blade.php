@use('App\Http\Controllers\Pengaturan\MasterDataController')
<x-app-layout judul="Pengaturan">
    <div class="mx-auto max-w-3xl">
        @include('pengaturan._tab')
        <form method="POST" action="{{ $user->exists ? route('pengaturan.pengguna.update', $user) : route('pengaturan.pengguna.store') }}" class="kartu space-y-4">
            @csrf @if ($user->exists) @method('PUT') @endif
            <h2 class="judul-panel">{{ $user->exists ? 'Ubah pengguna' : 'Pengguna baru' }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="label">Nama *</span><input name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border-slate-300"></label>
                <label class="block"><span class="label">Username *</span><input name="username" value="{{ old('username', $user->username) }}" required class="mt-1 w-full rounded-lg border-slate-300"></label>
                <label class="block sm:col-span-2"><span class="label">Email *</span><input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 w-full rounded-lg border-slate-300"></label>
                <label class="block"><span class="label">Peran *</span>
                    <select name="peran" required class="mt-1 w-full rounded-lg border-slate-300">
                        @foreach ($opsi['peran'] as $p)<option @selected(old('peran', $user->roles->first()?->name) === $p)>{{ $p }}</option>@endforeach
                    </select></label>
                <label class="block"><span class="label">Unit kerja (wajib untuk Direktorat Sektor & Operator K/L)</span>
                    <select name="unit_kerja_id" class="mt-1 w-full rounded-lg border-slate-300"><option value="">–</option>
                        @foreach ($opsi['unit'] as $jenis => $daftar)
                            <optgroup label="{{ MasterDataController::JENIS_UNIT[$jenis] ?? $jenis }}">
                                @foreach ($daftar as $u)<option value="{{ $u->id }}" @selected((int) old('unit_kerja_id', $user->unit_kerja_id) === $u->id)>{{ $u->nama }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select></label>
                <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-slate-300 text-aksen"> Akun aktif (akun nonaktif langsung dikeluarkan dari sesi)</label>
            </div>
            @if ($user->legacy_grup)<p class="label">Akun hasil impor sistem lama: grup "{{ $user->legacy_grup }}", akses "{{ $user->legacy_akses }}".</p>@endif
            <div class="flex flex-wrap justify-end gap-2">
                <a href="{{ route('pengaturan.pengguna.index') }}" class="tombol-garis">Kembali</a>
                <button class="tombol-primer">Simpan</button>
            </div>
        </form>
        @if ($user->exists)
            <form method="POST" action="{{ route('pengaturan.pengguna.reset', $user) }}" class="mt-4 flex items-center justify-between rounded-kartu border border-slate-200 bg-white p-4" x-data @submit="confirm('Reset kata sandi pengguna ini?') || $event.preventDefault()">
                @csrf
                <span class="text-isi text-slate-700">Reset kata sandi: membuat kata sandi sementara dan mewajibkan penggantian saat login.</span>
                <button class="tombol-garis">Reset kata sandi</button>
            </form>
        @endif
    </div>
</x-app-layout>
