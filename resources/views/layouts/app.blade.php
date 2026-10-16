<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Dashboard' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{--
    Tata letak: topbar navigasi (menggantikan sidebar) + subbar judul/filter + konten.
    $layarPenuh = true (dashboard): pada layar ≥ 1280 × 720 px halaman mengisi tepat satu layar tanpa
    gulir vertikal (varian Tailwind "layar"); di layar lebih kecil kembali ke tata letak bergulir.
--}}
@php
    $u = auth()->user();
    $layarPenuh = ! empty($layarPenuh);
    $logo = collect(['img/logo.svg', 'img/logo.png'])->first(fn ($p) => file_exists(public_path($p)));
    // [label, label pendek (topbar), url, izin, ikon]
    $menu = [
        ['Dashboard', 'Dashboard', '/dashboard', 'ringkasan.lihat', 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z'],
        ['Portofolio PSN', 'Portofolio', '/proyek', 'portofolio.lihat', 'M4 6h16M4 12h16M4 18h10'],
        ['Perencanaan', 'Perencanaan', '/perencanaan', 'perencanaan.lihat', 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
        ['Pengisian & Verifikasi', 'Pengisian', '/pengisian', 'detail.input', 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z'],
        ['Risiko, Isu & Regulasi', 'Risiko & Isu', '/risiko', 'risiko.lihat', 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
        ['Peta Sebaran', 'Peta', '/peta', 'ringkasan.lihat', 'M9 20l-5.4-2.7A1 1 0 0 1 3 16.4V5.6a1 1 0 0 1 1.4-.9L9 7m0 13 6-3m-6 3V7m6 10 4.6 2.3a1 1 0 0 0 1.4-.9V7.6a1 1 0 0 0-.6-.9L15 4m0 13V4m0 0L9 7'],
        ['Kualitas Data', 'Kualitas Data', '/kualitas-data', 'kualitas.lihat', 'M9 17v-6m4 6V7m4 10v-3M5 21h14'],
        ['Laporan', 'Laporan', '/laporan', 'laporan.lihat', 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8M8 17h5'],
        ['Kamus Indikator', 'Kamus', '/kamus-indikator', null, 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15Z'],
        ['Pengaturan', 'Pengaturan', '/pengaturan', 'pengaturan.kelola', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2-1.2L14.5 3h-5l-.4 2.6a7.6 7.6 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7.6 7.6 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z'],
    ];
    $menu = array_values(array_filter($menu, fn ($m) => $m[3] === null || $u->can($m[3])));
    $aktif = fn ($url) => request()->is(ltrim($url, '/').'*');
@endphp
<body x-data="{ menuSeluler: false }" @class(['flex min-h-screen flex-col', 'layar:h-screen layar:overflow-hidden' => $layarPenuh])>
    {{-- Topbar navigasi --}}
    <header class="sticky top-0 z-30 bg-gradient-to-r from-[#0b2547] via-[#0d2b55] to-[#0b2547] text-white shadow-md">
        <div class="flex h-14 items-center gap-3 px-3 md:px-5">
            <button type="button" class="rounded-lg p-2 text-white/80 hover:bg-white/10 xl:hidden" @click="menuSeluler = !menuSeluler" :aria-expanded="menuSeluler" aria-controls="menu-seluler" aria-label="Buka menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <a href="/dashboard" class="flex shrink-0 items-center gap-2.5" aria-label="Beranda SIRENDA PSN">
                {{-- Logo instansi: letakkan berkas resmi di public/img/logo.svg (atau .png); jika tidak ada, tanda generik. --}}
                @if ($logo)
                    <img src="{{ asset($logo) }}" alt="" class="h-8 w-8 object-contain">
                @else
                    <svg class="h-8 w-8" viewBox="0 0 40 40" aria-hidden="true">
                        <path d="M8 30C8 17 17 8 30 8c-4 3-7 7-8 12 5-2 9-1 12 2-9-1-17 3-26 8Z" fill="#3b8cf0"/>
                        <path d="M10 33c7-5 15-7 24-5-6 1-11 4-15 9-3-2-6-3-9-4Z" fill="#f5a524"/>
                    </svg>
                @endif
                <span class="hidden leading-tight sm:block">
                    <span class="block text-[13px] font-bold">SIRENDA PSN</span>
                    <span class="block text-[10px] text-white/60">{{ config('app.instansi', 'Kementerian PPN/Bappenas') }}</span>
                </span>
            </a>

            <nav class="ml-2 hidden min-w-0 flex-1 items-center gap-0.5 xl:flex" aria-label="Navigasi utama">
                @foreach ($menu as [$label, $pendek, $url, $izin, $ikon])
                    <a :href="$store.filter.tautan('{{ $url }}')" href="{{ $url }}" title="{{ $label }}"
                       @class(['flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 py-1.5 text-[13px] transition', 'bg-aksen font-semibold text-white shadow' => $aktif($url), 'text-white/75 hover:bg-white/10 hover:text-white' => ! $aktif($url)])
                       @if ($aktif($url)) aria-current="page" @endif>
                        <svg class="hidden h-4 w-4 shrink-0 min-[1600px]:block" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $ikon }}"/></svg>
                        {{ $pendek }}
                    </a>
                @endforeach
            </nav>

            {{-- Menu pengguna --}}
            <div class="relative ml-auto shrink-0" x-data="{ buka: false }" @click.outside="buka = false" @keydown.escape="buka = false">
                <button type="button" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="menu" class="flex items-center gap-2 rounded-lg px-2 py-1 text-left hover:bg-white/10">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-aksen text-[11px] font-bold">{{ \Illuminate\Support\Str::of($u->name)->explode(' ')->map(fn ($k) => mb_substr($k, 0, 1))->take(2)->implode('') }}</span>
                    <span class="hidden min-w-0 leading-tight 2xl:block">
                        <span class="block max-w-[11rem] truncate text-[12px] font-semibold">{{ $u->name }}</span>
                        <span class="block max-w-[11rem] truncate text-[10px] text-white/60">{{ $u->getRoleNames()->first() ?? 'Tanpa peran' }}</span>
                    </span>
                    <svg class="h-4 w-4 text-white/60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                </button>
                <div x-show="buka" x-cloak x-transition.opacity role="menu" class="absolute right-0 z-50 mt-1 w-60 rounded-xl border border-slate-200 bg-white py-1 text-slate-700 shadow-lg">
                    <div class="border-b border-slate-100 px-4 py-2">
                        <p class="truncate text-[13px] font-semibold text-slate-900">{{ $u->name }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ $u->getRoleNames()->first() ?? 'Tanpa peran' }}{{ $u->unitKerja ? ' · '.$u->unitKerja->nama : '' }}</p>
                    </div>
                    <a href="{{ route('password.edit') }}" role="menuitem" class="block px-4 py-2 text-isi hover:bg-slate-50">Ganti kata sandi</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button role="menuitem" class="block w-full px-4 py-2 text-left text-isi text-red-700 hover:bg-red-50">Keluar</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Menu layar kecil --}}
        <nav id="menu-seluler" x-show="menuSeluler" x-cloak x-transition.opacity @click.outside="menuSeluler = false" class="border-t border-white/10 px-3 pb-3 xl:hidden" aria-label="Navigasi utama">
            <div class="grid grid-cols-2 gap-1 pt-2 sm:grid-cols-3 md:grid-cols-5">
                @foreach ($menu as [$label, $pendek, $url, $izin, $ikon])
                    <a :href="$store.filter.tautan('{{ $url }}')" href="{{ $url }}"
                       @class(['flex items-center gap-2 rounded-lg px-3 py-2 text-[13px]', 'bg-aksen font-semibold' => $aktif($url), 'text-white/80 hover:bg-white/10' => ! $aktif($url)])>
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $ikon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </nav>
    </header>

    {{-- Subbar: judul halaman, filter global, dan aksi halaman --}}
    <div class="z-20 border-b border-slate-200/80 bg-white/95 backdrop-blur {{ $layarPenuh ? '' : 'sticky top-14' }}">
        <div @class(['flex flex-wrap items-center gap-x-4 gap-y-2 px-3 md:px-5', 'py-2' => ! $layarPenuh, 'py-1.5' => $layarPenuh])>
            <div class="mr-auto min-w-0">
                <h1 class="truncate text-[17px] font-bold leading-6 text-primer">{{ $judul ?? 'Dashboard' }}</h1>
                @if (! empty($subjudul))<p @class(['truncate text-[12px] leading-4 text-slate-500', '[@media(max-height:1000px)]:hidden' => $layarPenuh])>{{ $subjudul }}</p>@endif
            </div>
            @isset($filter){{ $filter }}@endisset
            @isset($aksi){{ $aksi }}@endisset
        </div>
    </div>

    <main @class(['flex-1 bg-kanvas', 'px-3 py-2.5 md:px-5 layar:min-h-0' => $layarPenuh, 'px-4 py-6 md:px-8' => ! $layarPenuh])>
        {{ $slot }}
    </main>
</body>
</html>
