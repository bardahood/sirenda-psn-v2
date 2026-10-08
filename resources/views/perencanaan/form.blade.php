@use('App\Models\UsulanPsn')
<x-app-layout judul="Usulan PSN Baru">
    <div class="mx-auto max-w-3xl">
        <nav class="label mb-4" aria-label="Breadcrumb"><a href="{{ route('perencanaan.index') }}" class="text-aksen-700 hover:underline">Perencanaan</a> / <span aria-current="page">Usulan baru</span></nav>
        <form method="POST" action="{{ route('perencanaan.store') }}" class="kartu space-y-4">
            @csrf
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800" role="alert"><ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <label class="block"><span class="label">Nama usulan *</span>
                <input name="nama" value="{{ old('nama') }}" required maxlength="500" class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen"></label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block"><span class="label">Tahun RKP *</span>
                    <input type="number" name="tahun_rkp" value="{{ old('tahun_rkp', $usulan->tahun_rkp) }}" required class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen"></label>
                <label class="block"><span class="label">Jenis pengusul * (menentukan sub-kriteria KP4–KP6)</span>
                    <select name="jenis_pengusul" required class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen">
                        <option value="">Pilih…</option>
                        @foreach (UsulanPsn::JENIS_PENGUSUL as $k => $l)<option value="{{ $k }}" @selected(old('jenis_pengusul') === $k)>{{ $l }}</option>@endforeach
                    </select></label>
                <label class="block"><span class="label">Klaster</span>
                    <select name="klaster_id" class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen"><option value="">–</option>
                        @foreach ($opsi['klaster'] as $id => $n)<option value="{{ $id }}" @selected((int) old('klaster_id') === $id)>{{ $n }}</option>@endforeach</select></label>
                @unless (auth()->user()->ubahTerbatas())
                    <label class="block"><span class="label">Direktorat pengampu</span>
                        <select name="unit_kerja_id" class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen"><option value="">–</option>
                            @foreach ($opsi['direktorat'] as $id => $n)<option value="{{ $id }}" @selected((int) old('unit_kerja_id') === $id)>{{ $n }}</option>@endforeach</select></label>
                @endunless
                <label class="block"><span class="label">K/L pengusul</span>
                    <select name="pengusul_instansi_id" class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen"><option value="">–</option>
                        @foreach ($opsi['instansi'] as $id => $n)<option value="{{ $id }}" @selected((int) old('pengusul_instansi_id') === $id)>{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="label">Pengusul lain (Pemda/BUMN/swasta)</span>
                    <input name="pengusul_teks" value="{{ old('pengusul_teks') }}" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen"></label>
                <label class="block"><span class="label">Nilai investasi (Rupiah penuh)</span>
                    <input type="number" step="1" min="0" name="nilai_investasi_rp" value="{{ old('nilai_investasi_rp') }}" class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen"></label>
                <label class="mt-6 flex items-center gap-2"><input type="checkbox" name="is_infrastruktur" value="1" @checked(old('is_infrastruktur')) class="rounded border-slate-300 text-aksen focus:ring-aksen">
                    <span>Usulan infrastruktur (sub-kriteria KK3–KK4 berlaku)</span></label>
            </div>
            <label class="block"><span class="label">Provinsi lokasi (boleh lebih dari satu; tahan Ctrl/Cmd)</span>
                <select name="provinsi[]" multiple size="6" class="mt-1 w-full rounded-lg border-slate-300 focus:border-aksen focus:ring-aksen">
                    @foreach ($opsi['provinsi'] as $kode => $n)<option value="{{ $kode }}" @selected(in_array($kode, old('provinsi', []), true))>{{ $n }}</option>@endforeach
                </select></label>
            <div class="flex justify-end gap-2"><a href="{{ route('perencanaan.index') }}" class="tombol-garis">Batal</a><button class="tombol-primer">Simpan usulan</button></div>
        </form>
    </div>
</x-app-layout>
