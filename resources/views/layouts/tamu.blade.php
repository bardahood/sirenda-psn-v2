<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul ?? 'Masuk' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-primer px-4">
    <main class="w-full max-w-sm">
        <div class="mb-6 text-center text-white">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-aksen text-lg font-bold">PSN</span>
            <p class="mt-3 text-lg font-semibold">SIRENDA PSN</p>
            <p class="text-sm text-white/70">Monitoring & Evaluasi Proyek Strategis Nasional<br>Kementerian PPN/Bappenas</p>
        </div>
        <div class="rounded-kartu bg-white p-6 shadow-xl">
            @yield('isi')
        </div>
    </main>
</body>
</html>
