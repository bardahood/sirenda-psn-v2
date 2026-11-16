<x-app-layout judul="Unggah Kertas Kerja · RKP {{ $tahun }}" subjudul="Isi kertas kerja secara luring di Excel, lalu unggah kembali; perubahan ditinjau dulu sebelum disimpan">
    <x-slot:aksi>
        <a href="{{ route('evaluasi.kertas-kerja') }}" class="tombol-garis py-1.5">← Kertas kerja</a>
    </x-slot:aksi>
    <div class="mx-auto max-w-[1100px] space-y-5">
        @include('evaluasi._tab')
        @if ($errors->any())<div class="rounded-kartu border border-red-200 bg-red-50 p-3 text-red-800" role="alert">{{ $errors->first() }}</div>@endif

        <section class="kartu" aria-labelledby="h-unggah">
            <h2 id="h-unggah" class="judul-panel">Unggah berkas kertas kerja (.xlsx)</h2>
            <form method="POST" action="{{ route('evaluasi.unggah.baca') }}" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3" x-data="{ proses: false }" @submit="proses = true">
                @csrf
                <label class="text-isi"><span class="label block">Berkas (maks. 10 MB)</span>
                    <input type="file" name="berkas" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required
                           class="mt-1 block w-[28rem] max-w-full rounded-lg border border-slate-300 text-isi file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5"></label>
                <button class="tombol-primer py-1.5" :disabled="proses" x-text="proses ? 'Membaca berkas…' : 'Baca dan tinjau perubahan'">Baca dan tinjau perubahan</button>
            </form>
        </section>

        <section class="kartu" aria-labelledby="h-cara">
            <h2 id="h-cara" class="judul-panel">Cara menggunakan</h2>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-isi text-slate-700">
                <li>Di tab <b>Kertas Kerja</b>, klik <b>Unduh kertas kerja (Excel)</b>. Template resmi <i>Input Form Pengendalian PSN</i> (sheet <i>Form PSN Format Ringkas</i>) juga dapat dipakai.</li>
                <li>Isi kolom isian di Excel. Gunakan dropdown dan sheet <b>Daftar Pilihan</b>. Untuk sel berisi beberapa butir (C1, D2, B3, C2, lampiran), tulis satu butir per baris dengan nomor, misalnya <code>1. Pengadaan Tanah dan Legalitas Lahan</code> (Alt+Enter untuk baris baru).</li>
                <li>Jangan mengubah <b>Kode PSN</b>: baris dicocokkan menurut kode ini. Kolom profil (A1 sampai A5, A7, A9 sampai A11, B1) dan kolom otomatis diabaikan; perbaiki datanya di SIRENDA PEKS3.</li>
                <li>Unggah berkasnya. Sistem hanya mengambil sel yang <b>berbeda</b> dari kertas kerja saat ini dan menampilkan pratinjau beserta galat per sel. Belum ada yang tersimpan sampai Anda menekan <b>Terapkan</b>.</li>
                <li>Sel yang dikosongkan di Excel akan dikosongkan juga di aplikasi (kecuali kolom B2 yang memakai data profil dan tindak lanjut otomatis).</li>
            </ol>
            <p class="mt-3 text-[12px] text-slate-500">Hak akses sama dengan isian langsung: isian PJ (Operator K/L ke atas), pengecekan dan catatan (Direktorat Sektor ke atas), indikasi status (PMO dan Super Admin). PSN di luar cakupan unit Anda tidak diproses.</p>
        </section>
    </div>
</x-app-layout>
