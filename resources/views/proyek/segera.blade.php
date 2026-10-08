<x-app-layout judul="Portofolio PSN">
    <x-slot:filter>
        <x-filter-global />
    </x-slot:filter>
    <nav class="label mb-4" aria-label="Breadcrumb">
        <a :href="$store.filter.tautan('/dashboard')" href="/dashboard" class="text-aksen-700 hover:underline">Ringkasan Eksekutif</a> / <span>Portofolio PSN</span>
    </nav>
    <div class="kartu" x-data x-init="$store.filter.muatOpsi()">
        <h2 class="judul-panel">Halaman Portofolio sedang dibangun (Fase 3)</h2>
        <p class="mt-2 text-slate-600">Filter dari dashboard sudah diterima dan akan dipakai tabel portofolio:</p>
        <code class="mt-2 block rounded bg-slate-100 p-3 text-label" x-text="$store.filter.qs() || '(tanpa filter)'"></code>
    </div>
</x-app-layout>
