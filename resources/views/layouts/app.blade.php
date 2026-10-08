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
    <aside :class="ciut ? 'w-18' : 'w-60'"
           class="fixed inset-y-0 left-0 z-30 hidden flex-col bg-primer text-white transition-[width] duration-200 lg:flex"
           aria-label="Navigasi utama">
        <div class="flex h-18 items-center gap-3 border-b border-white/10 px-5">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-aksen text-sm font-bold">PSN</span>
            <span x-show="!ciut" class="text-sm font-semibold leading-tight">SIRENDA PSN<br><span class="font-normal text-white/70">Monev Bappenas</span></span>
        </div>
        @php
            $menu = [
                ['Ringkasan Eksekutif', '/dashboard', 'ringkasan.lihat', 'M3 13h8V3H3v10Zm0 8h8v-6H3v6Zm10 0h8V11h-8v10Zm0-18v6h8V3h-8Z'],
                ['Portofolio PSN', '/proyek', 'portofolio.lihat', 'M4 6h16M4 12h16M4 18h10'],
                ['Perencanaan', '/perencanaan', 'perencanaan.lihat', 'M9 11l3 3L22 4M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'],
                ['Risiko, Isu & Regulasi', '/risiko', 'risiko.lihat', 'M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z'],
                ['Peta Sebaran', '/peta', 'ringkasan.lihat', 'M9 20l-5.4-2.7A1 1 0 0 1 3 16.4V5.6a1 1 0 0 1 1.4-.9L9 7m0 13 6-3m-6 3V7m6 10 4.6 2.3a1 1 0 0 0 1.4-.9V7.6a1 1 0 0 0-.6-.9L15 4m0 13V4m0 0L9 7'],
                ['Kualitas Data', '/kualitas-data', 'kualitas.lihat', 'M9 17v-6m4 6V7m4 10v-3M5 21h14'],
            ];
        @endphp
        <nav class="flex-1 space-y-1 px-3 py-4">
            @foreach ($menu as [$label, $url, $izin, $ikon])
                @can($izin)
                    @php($aktif = request()->is(ltrim($url, '/').'*'))
                    <a :href="$store.filter.tautan('{{ $url }}')" href="{{ $url }}"
                       @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm', 'bg-white/10 font-semibold' => $aktif, 'text-white/80 hover:bg-white/5 hover:text-white' => ! $aktif])
                       @if ($aktif) aria-current="page" @endif :title="ciut ? '{{ $label }}' : ''">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $ikon }}"/></svg>
                        <span x-show="!ciut">{{ $label }}</span>
                    </a>
                @endcan
            @endforeach
        </nav>
        <div class="space-y-1 border-t border-white/10 p-3">
            <div class="flex items-center gap-3 px-3 py-2 text-sm">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-semibold">{{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($k) => mb_substr($k, 0, 1))->take(2)->implode('') }}</span>
                <span x-show="!ciut" class="min-w-0 truncate">{{ auth()->user()->name }}<br><span class="text-xs text-white/60">{{ auth()->user()->getRoleNames()->first() }}</span></span>
                <form x-show="!ciut" method="POST" action="{{ route('logout') }}" class="ml-auto">@csrf
                    <button class="rounded-md p-1 text-white/70 hover:bg-white/10 hover:text-white" title="Keluar" aria-label="Keluar">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    </button>
                </form>
            </div>
            <button type="button" @click="ciut = !ciut" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm text-white/70 hover:bg-white/5"
                    :aria-label="ciut ? 'Lebarkan menu' : 'Ciutkan menu'">
                <svg class="h-5 w-5 transition" :class="ciut && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                <span x-show="!ciut">Ciutkan</span>
            </button>
        </div>
    </aside>

    <div :class="ciut ? 'lg:pl-18' : 'lg:pl-60'" class="flex min-w-0 flex-1 flex-col transition-[padding] duration-200">
        {{-- Header sticky 72 px berisi filter global --}}
        <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div class="flex min-h-18 flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 md:px-8">
                <div class="mr-auto min-w-0">
                    <h1 class="truncate text-lg font-semibold text-primer">{{ $judul ?? 'Dashboard' }}</h1>
                </div>
                @isset($filter)
                    {{ $filter }}
                @endisset
            </div>
        </header>

        <main class="flex-1 px-4 py-6 md:px-8">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
