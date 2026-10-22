@use('App\Models\UsulanPsn')
@php($psi = $usulan->psi)
<div class="grid grid-cols-12 gap-5">
    <section class="kartu col-span-12 xl:col-span-7" aria-labelledby="h-identitas">
        <h3 id="h-identitas" class="judul-panel">Identitas Pengusulan</h3>
        <p class="label mb-4 font-normal text-slate-500">Data umum proyek yang diusulkan.</p>
        <dl class="grid gap-x-8 gap-y-4 md:grid-cols-2">
            @foreach ([
                ['Instansi Pengusul', $psi?->instansi_pengusul ?? $usulan->pengusulInstansi?->nama ?? $usulan->pengusul_teks],
                ['Jenis Instansi', $psi?->jenis_instansi ?? (UsulanPsn::JENIS_PENGUSUL[$usulan->jenis_pengusul] ?? null)],
                ['Nama Usulan PSN', $usulan->nama],
                ['Sektor', $psi?->sektor],
                ['Klaster', $usulan->klaster?->nama ?? $psi?->klaster_teks],
                ['Jenis PSN', $psi?->jenis_psn ?? ($usulan->is_infrastruktur ? 'Infrastruktur' : null)],
                ['Tahun RKP', $usulan->tahun_rkp],
                ['Nilai Investasi', $usulan->nilai_investasi_rp !== null ? 'Rp '.number_format($usulan->nilai_investasi_rp, 0, ',', '.') : null],
            ] as [$l, $v])
                <div><dt class="label">{{ $l }}</dt><dd class="mt-0.5 text-isi text-slate-900">{{ $v ?: '–' }}</dd></div>
            @endforeach
            <div class="md:col-span-2"><dt class="label">Lokasi</dt>
                <dd class="mt-0.5 text-isi text-slate-900">{{ $usulan->lokasi->map(fn ($l) => trim(($l->kabupaten?->nama ? $l->kabupaten->nama.', ' : '').($l->provinsi?->nama ?? $l->provinsi_kode)))->implode('; ') ?: '–' }}</dd></div>
            @if ($psi?->kontak)
                <div class="md:col-span-2"><dt class="label">Narahubung</dt>
                    <dd class="mt-0.5 text-isi text-slate-900">{{ collect([$psi->kontak['nama'] ?? null, $psi->kontak['jabatan'] ?? null, $psi->kontak['email'] ?? null, $psi->kontak['telepon'] ?? null])->filter()->implode(' · ') }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="kartu col-span-12 xl:col-span-5" aria-labelledby="h-peta">
        <h3 id="h-peta" class="judul-panel">Penetapan di SIRENDA</h3>
        <p class="label mb-3 font-normal text-slate-500">Direktorat pengampu menentukan siapa yang dapat menilai (Direktorat Sektor). Klaster & jenis pengusul memengaruhi kriteria penilaian.</p>
        @if ($psi?->peringatan_impor)
            <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-[12px] text-amber-900" role="status">
                <p class="font-semibold">Nilai dari PSI yang belum terpetakan:</p>
                <ul class="ml-4 list-disc">@foreach ($psi->peringatan_impor as $p)<li>{{ $p }}</li>@endforeach</ul>
            </div>
        @endif
        @can('kelola', UsulanPsn::class)
            <form method="POST" action="{{ route('perencanaan.pemetaan', $usulan) }}" class="space-y-3">
                @csrf @method('PUT')
                <label class="block"><span class="label">Direktorat pengampu</span>
                    <select name="unit_kerja_id" class="mt-1 w-full rounded-lg border-slate-300 text-isi"><option value="">— Belum ditetapkan —</option>
                        @foreach (\Illuminate\Support\Facades\DB::table('ref_unit_kerja')->where('jenis', 'DIREKTORAT')->where('is_aktif', true)->orderBy('nama')->pluck('nama', 'id') as $id => $n)<option value="{{ $id }}" @selected($usulan->unit_kerja_id === $id)>{{ $n }}</option>@endforeach
                    </select></label>
                <label class="block"><span class="label">Klaster SIRENDA</span>
                    <select name="klaster_id" class="mt-1 w-full rounded-lg border-slate-300 text-isi"><option value="">— Belum ditetapkan —</option>
                        @foreach (\Illuminate\Support\Facades\DB::table('ref_klaster')->orderBy('urutan')->pluck('nama', 'id') as $id => $n)<option value="{{ $id }}" @selected($usulan->klaster_id === $id)>{{ $n }}</option>@endforeach
                    </select></label>
                <label class="block"><span class="label">Jenis pengusul</span>
                    <select name="jenis_pengusul" class="mt-1 w-full rounded-lg border-slate-300 text-isi"><option value="">— Belum ditetapkan —</option>
                        @foreach (UsulanPsn::JENIS_PENGUSUL as $k => $n)<option value="{{ $k }}" @selected($usulan->jenis_pengusul === $k)>{{ $n }}</option>@endforeach
                    </select></label>
                <label class="flex items-center gap-2 text-isi"><input type="hidden" name="is_infrastruktur" value="0"><input type="checkbox" name="is_infrastruktur" value="1" @checked($usulan->is_infrastruktur) class="rounded border-slate-300 text-aksen"> Proyek infrastruktur (sub-kriteria KK3–KK4 berlaku)</label>
                <button class="tombol-primer">Simpan penetapan</button>
            </form>
        @else
            <dl class="space-y-2 text-isi">
                <div><dt class="label">Direktorat pengampu</dt><dd>{{ $usulan->unitKerja?->nama ?? 'Belum ditetapkan' }}</dd></div>
                <div><dt class="label">Klaster SIRENDA</dt><dd>{{ $usulan->klaster?->nama ?? 'Belum ditetapkan' }}</dd></div>
                <div><dt class="label">Jenis pengusul</dt><dd>{{ UsulanPsn::JENIS_PENGUSUL[$usulan->jenis_pengusul] ?? 'Belum ditetapkan' }}</dd></div>
            </dl>
        @endcan
    </section>
</div>
