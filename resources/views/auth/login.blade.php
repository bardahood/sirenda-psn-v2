@extends('layouts.tamu', ['judul' => 'Masuk'])

@section('isi')
    <h1 class="judul-panel mb-4">Masuk ke aplikasi</h1>
    <form method="POST" action="{{ url('/login') }}" class="space-y-4">
        @csrf
        <div>
            <label for="login" class="label">Nama pengguna atau email</label>
            <input id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username"
                   class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen">
        </div>
        <div>
            <label for="password" class="label">Kata sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen">
        </div>
        @error('login') <p class="text-isi text-red-700" role="alert">{{ $message }}</p> @enderror
        <button class="tombol-primer w-full justify-center">Masuk</button>
    </form>
@endsection
