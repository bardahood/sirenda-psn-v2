@use('App\Enums\Rekomendasi')
<x-app-layout judul="Perencanaan & Penilaian Usulan">
    <div class="mx-auto max-w-[1376px]">
        @if (session('status'))<div class="mb-4 rounded-kartu border border-green-200 bg-green-50 p-3 text-green-800" role="status">{{ session('status') }}</div>@endif

        <section class="kartu" aria-labelledby="h-usulan">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <h2 id="h-usulan" class="judul-panel mr-auto">Daftar Usulan PSN <span class="label font-normal">{{ $usulan->total() }} usulan · diurutkan berdasarkan nilai akhir</span></h2>
                <label class="text-isi"><span class="label block">Cari</span>
                    <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" maxlength="100" class="mt-1 w-56 rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen" placeholder="Nama usulan…">
                </label>
                @foreach ([['tahun', 'Tahun RKP', $opsi['tahun']->mapWithKeys(fn ($t) => [$t => $t])], ['instansi', 'K/L pengusul', $opsi['instansi']], ['klaster', 'Klaster', $opsi['klaster']], ['status', 'Status penilaian', collect(['BELUM_DINILAI' => 'Belum dinilai', 'DRAFT' => 'Draf', 'FINAL' => 'Final'])], ['rekomendasi', 'Rekomendasi', collect(Rekomendasi::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])]] as [$nama, $label, $pilihan])
                    <label class="text-isi"><span class="label block">{{ $label }}</span>
                        <select name="{{ $nama }}" class="mt-1 max-w-[14rem] rounded-lg border-slate-300 py-1.5 text-isi focus:border-aksen focus:ring-aksen">
                            <option value="">Semua</option>
                            @foreach ($pilihan as $nilai => $teks)<option value="{{ $nilai }}" @selected((string) ($filter[$nama] ?? '') === (string) $nilai)>{{ $teks }}</option>@endforeach
                        </select>
                    </label>
                @endforeach
                <button class="tombol-garis py-1.5">Terapkan</button>
                <a href="{{ route('perencanaan.index') }}" class="tombol-garis py-1.5">Reset</a>
                @can('create', App\Models\UsulanPsn::class)
                    <a href="{{ route('perencanaan.create') }}" class="tombol-primer py-1.5">+ Usulan baru</a>
                @endcan
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600">
                        <tr><th class="py-2 pr-3">Usulan</th><th class="py-2 pr-3">Tahun</th><th class="py-2 pr-3">Klaster</th><th class="py-2 pr-3">Pengusul</th><th class="py-2 pr-3">Lokasi</th><th class="py-2 pr-3">Gate</th><th class="py-2 pr-3 text-right">Nilai akhir</th><th class="py-2 pr-3">Rekomendasi</th><th class="py-2">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($usulan as $u)
                            @php($rek = $u->rekomendasi ? Rekomendasi::tryFrom($u->rekomendasi) : null)
                            <tr class="hover:bg-slate-50">
                                <td class="max-w-[22rem] py-2 pr-3"><a href="{{ route('perencanaan.show', $u) }}" class="font-medium text-aksen-700 hover:underline">{{ $u->nama }}</a></td>
                                <td class="py-2 pr-3">{{ $u->tahun_rkp }}</td>
                                <td class="max-w-[12rem] truncate py-2 pr-3" title="{{ $u->klaster?->nama }}">{{ $u->klaster?->nama ?? '–' }}</td>
                                <td class="max-w-[12rem] truncate py-2 pr-3">{{ $u->pengusulInstansi?->nama ?? $u->pengusul_teks ?? '–' }}</td>
                                <td class="max-w-[10rem] truncate py-2 pr-3">{{ $u->lokasi->map(fn ($l) => $l->provinsi?->nama)->filter()->implode(', ') ?: '–' }}</td>
                                <td class="py-2 pr-3">{{ $u->gate_lulus === null ? '–' : ($u->gate_lulus ? 'Lulus' : 'Gugur') }}</td>
                                <td class="angka py-2 pr-3 text-right font-semibold">{{ $u->nilai_akhir !== null ? number_format($u->nilai_akhir, 2, ',', '.') : '–' }}</td>
                                <td class="py-2 pr-3">@if ($rek)<span class="{{ $rek->badge() }}">{{ $rek->label() }}</span>@else <span class="label">Belum dinilai</span>@endif</td>
                                <td class="py-2">{{ $u->penilaian_status ? ucfirst(strtolower($u->penilaian_status)) : '–' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="py-8 text-center text-slate-600">Belum ada usulan yang sesuai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $usulan->links() }}</div>
        </section>
    </div>
</x-app-layout>
