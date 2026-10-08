@extends('layouts.tamu', ['judul' => 'Ganti Kata Sandi'])

@section('isi')
    <h1 class="judul-panel">Ganti kata sandi</h1>
    @if (auth()->user()->wajib_ganti_password)
        <p class="mt-1 text-isi text-slate-600">Anda wajib mengganti kata sandi sebelum melanjutkan.</p>
    @endif
    <form method="POST" action="{{ route('password.update') }}" class="mt-4 space-y-4">
        @csrf @method('PUT')
        @foreach ([['password_lama', 'Kata sandi lama', 'current-password'], ['password', 'Kata sandi baru (min. 12 karakter, huruf besar, huruf kecil, angka)', 'new-password'], ['password_confirmation', 'Ulangi kata sandi baru', 'new-password']] as [$nama, $label, $ac])
            <div>
                <label for="{{ $nama }}" class="label">{{ $label }}</label>
                <input id="{{ $nama }}" name="{{ $nama }}" type="password" required autocomplete="{{ $ac }}"
                       class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen">
                @error($nama) <p class="mt-1 text-isi text-red-700" role="alert">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <button class="tombol-primer w-full justify-center">Simpan</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3 text-center">@csrf
        <button class="text-label text-slate-600 hover:underline">Keluar</button>
    </form>
@endsection
