<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Dashboard' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ ciut: localStorage.getItem('sidebar-ciut') === '1', menuSeluler: false }"
      x-init="$watch('ciut', v => localStorage.setItem('sidebar-ciut', v ? '1' : '0'))">
<div class="flex min-h-screen">
    {{-- Sidebar 240 px, dapat diciutkan ke 72 px --}}
    <aside :class="ciut && '!w-18'"
           class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col bg-gradient-to-b from-[#0d2b55] to-[#081a33] text-white transition-[width] duration-200 lg:flex"
           aria-label="Navigasi utama">
        <div class="flex h-20 items-center gap-3 px-5">
            {{-- Logo instansi: letakkan berkas resmi di public/img/logo.svg (atau .png); jika tidak ada, tanda generik. --}}
            @php
                $logo = collect(['img/logo.svg', 'img/logo.png'])->first(fn ($p) => file_exists(public_path($p)));
            @endphp
            @if ($logo)
                <img src="{{ asset($logo) }}" alt="" class="h-10 w-10 shrink-0 object-contain">
            @else
                <svg class="h-10 w-10 shrink-0" viewBox="0 0 40 40" aria-hidden="true">
                    <path d="M8 30C8 17 17 8 30 8c-4 3-7 7-8 12 5-2 9-1 12 2-9-1-17 3-26 8Z" fill="#3b8cf0"/>
                    <path d="M10 33c7-5 15-7 24-5-6 1-11 4-15 9-3-2-6-3-9-4Z" fill="#f5a524"/>
                </svg>
            @endif
            <span x-show="!ciut" class="text-[13px] font-semibold leading-tight">{{ config('app.instansi', 'Kementerian PPN/Bappenas') }}<br><span class="text-[11px] font-normal text-white/60">SIRENDA PSN</span></span>
        </div>
        @php
            $menu = [
                ['Dashboard', '/dashboard', 'ringkasan.lihat', 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z'],
                ['Portofolio PSN', '/proyek', 'portofolio.lihat', 'M4 6h16M4 12h16M4 18h10'],
                ['Perencanaan', '/perencanaan', 'perencanaan.lihat', 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
                ['Pengisian & Verifikasi', '/pengisian', 'detail.input', 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z'],
                ['Risiko, Isu & Regulasi', '/risiko', 'risiko.lihat', 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
                ['Peta Sebaran', '/peta', 'ringkasan.lihat', 'M9 20l-5.4-2.7A1 1 0 0 1 3 16.4V5.6a1 1 0 0 1 1.4-.9L9 7m0 13 6-3m-6 3V7m6 10 4.6 2.3a1 1 0 0 0 1.4-.9V7.6a1 1 0 0 0-.6-.9L15 4m0 13V4m0 0L9 7'],
                ['Kualitas Data', '/kualitas-data', 'kualitas.lihat', 'M9 17v-6m4 6V7m4 10v-3M5 21h14'],
                ['Laporan', '/laporan', 'laporan.lihat', 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8M8 17h5'],
                ['Kamus Indikator', '/kamus-indikator', null, 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V2H6.5A2.5 2.5 0 0 0 4 4.5v15Z'],
                ['Pengaturan', '/pengaturan', 'pengaturan.kelola', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2-1.2L14.5 3h-5l-.4 2.6a7.6 7.6 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2 1.2l.4 2.6h5l.4-2.6a7.6 7.6 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2Z'],
            ];
        @endphp
        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            @foreach ($menu as [$label, $url, $izin, $ikon])
                @if ($izin === null || auth()->user()->can($izin))
                    @php($aktif = request()->is(ltrim($url, '/').'*'))
                    <a :href="$store.filter.tautan('{{ $url }}')" href="{{ $url }}"
                       @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px]', 'bg-aksen font-semibold text-white shadow-md shadow-black/20' => $aktif, 'text-white/80 hover:bg-white/10 hover:text-white' => ! $aktif])
                       @if ($aktif) aria-current="page" @endif :title="ciut ? '{{ $label }}' : ''">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $ikon }}"/></svg>
                        <span x-show="!ciut">{{ $label }}</span>
                    </a>
                @endif
            @endforeach
        </nav>
        {{-- Tagline + ilustrasi dekoratif (disembunyikan saat sidebar diciutkan atau layar pendek). --}}
        <div x-show="!ciut" class="relative mx-3 mb-2 hidden overflow-hidden rounded-xl bg-white/5 px-4 pb-4 pt-16 [@media(min-height:820px)]:block" aria-hidden="true">
            <svg class="absolute inset-x-0 top-0 h-16 w-full text-white/15" viewBox="0 0 200 64" preserveAspectRatio="xMidYMax meet" fill="currentColor">
                <path d="M0 64V44h10V30h8v14h6V20h10v24h6V36h8v28Zm60 0V40h8V24l6-6 6 6v16h8v24Zm42 0V10h3V0h2v10h3v54Zm16 0V34h10V22h12v12h8v30Zm38 0V28h12v8h8V18h10v46Z"/>
                <path d="M0 64c40-14 90-16 200-6v6Z" class="text-aksen" fill="currentColor" opacity=".5"/>
            </svg>
            <p class="text-[12px] font-semibold leading-snug text-white/90">Menuju Indonesia Maju Melalui Pembangunan Berkelanjutan</p>
        </div>
        <div class="border-t border-white/10 p-3">
            <button type="button" @click="ciut = !ciut" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/70 hover:bg-white/5"
                    :aria-label="ciut ? 'Lebarkan menu' : 'Ciutkan menu'">
                <svg class="h-5 w-5 transition" :class="ciut && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                <span x-show="!ciut">Ciutkan</span>
            </button>
        </div>
    </aside>

    {{-- Lebar default statis agar tidak berkedip sebelum Alpine aktif; mode ciut menimpa dengan !important. --}}
    <div :class="ciut && 'lg:!pl-18'" class="flex min-w-0 flex-1 flex-col transition-[padding] duration-200 lg:pl-60">
        {{-- Header sticky 72 px berisi filter global --}}
        <header class="sticky top-0 z-20 border-b border-slate-200/80 bg-white/95 backdrop-blur">
            <div class="flex min-h-[76px] flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 md:px-8">
                <div class="mr-auto min-w-0">
                    <h1 class="truncate text-xl font-bold text-primer">{{ $judul ?? 'Dashboard' }}</h1>
                    @if (! empty($subjudul))<p class="truncate text-[13px] text-slate-500">{{ $subjudul }}</p>@endif
                </div>
                {{-- Satu baris pada layar lebar; di bawah 1760 px filter turun ke baris kedua, menu pengguna tetap di kanan atas. --}}
                @isset($filter)
                    <div class="order-3 w-full min-[1760px]:order-2 min-[1760px]:w-auto">{{ $filter }}</div>
                @endisset
                {{-- Menu pengguna --}}
                @php($u = auth()->user())
                <div class="relative order-2 min-[1760px]:order-3" x-data="{ buka: false }" @click.outside="buka = false" @keydown.escape="buka = false">
                    <button type="button" @click="buka = !buka" :aria-expanded="buka" aria-haspopup="menu"
                            class="flex items-center gap-2.5 rounded-xl px-2 py-1.5 text-left hover:bg-slate-50">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primer text-xs font-bold text-white">{{ \Illuminate\Support\Str::of($u->name)->explode(' ')->map(fn ($k) => mb_substr($k, 0, 1))->take(2)->implode('') }}</span>
                        <span @class(['hidden min-w-0 leading-tight sm:block', 'min-[1760px]:hidden min-[1900px]:block' => isset($filter)])>
                            <span class="block max-w-[10rem] truncate text-[13px] font-semibold text-slate-900">{{ $u->name }}</span>
                            <span class="block max-w-[10rem] truncate text-[11px] text-slate-500">{{ $u->getRoleNames()->first() ?? 'Tanpa peran' }}{{ $u->unitKerja ? ' · '.$u->unitKerja->nama : '' }}</span>
                        </span>
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-show="buka" x-cloak x-transition.opacity role="menu" class="absolute right-0 z-40 mt-1 w-56 rounded-xl border border-slate-200 bg-white py-1 shadow-lg">
                        <a href="{{ route('password.edit') }}" role="menuitem" class="block px-4 py-2 text-isi text-slate-700 hover:bg-slate-50">Ganti kata sandi</a>
                        <a href="{{ route('kamus') }}" role="menuitem" class="block px-4 py-2 text-isi text-slate-700 hover:bg-slate-50">Kamus indikator</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button role="menuitem" class="block w-full px-4 py-2 text-left text-isi text-red-700 hover:bg-red-50">Keluar</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 bg-kanvas px-4 py-6 md:px-8">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
