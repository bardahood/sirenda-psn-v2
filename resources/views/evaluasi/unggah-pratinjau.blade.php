@php($rg = $data['ringkasan'])
<x-app-layout judul="Pratinjau Unggah Kertas Kerja" subjudul="{{ $data['nama_berkas'] ?? '' }} · sheet {{ $data['sheet'] }}">
    <x-slot:aksi>
        <a href="{{ route('evaluasi.unggah') }}" class="tombol-garis py-1.5">Unggah berkas lain</a>
    </x-slot:aksi>
    <div class="mx-auto max-w-[1400px] space-y-5">
        @include('evaluasi._tab')

        <section class="grid grid-cols-2 gap-3 md:grid-cols-6" aria-label="Ringkasan">
            @foreach ([['Baris berkode PSN', $rg['baris'], 'text-slate-900'], ['PSN dikenali', $rg['dikenali'], 'text-slate-900'], ['PSN berubah', $rg['psn_berubah'], 'text-aksen'],
                ['Sel siap disimpan', $rg['sel_valid'], 'text-green-700'], ['Sel bergalat', $rg['sel_galat'], 'text-red-700'], ['Baris tidak dikenali', $rg['tidak_dikenal'], 'text-amber-700']] as [$l, $n, $w])
                <div class="kartu !p-4"><span class="label">{{ $l }}</span><span class="angka mt-1 block text-[26px] font-bold {{ $w }}">{{ number_format($n, 0, ',', '.') }}</span></div>
            @endforeach
        </section>

        @if ($data['tidak_dikenal'])
            <section class="kartu !border-amber-300 !bg-amber-50/60" aria-labelledby="h-td">
                <h2 id="h-td" class="judul-panel">Baris tidak diproses</h2>
                <ul class="mt-2 space-y-0.5 text-isi">
                    @foreach ($data['tidak_dikenal'] as $t)<li>Baris {{ $t['baris_excel'] }} · <b>{{ $t['kode'] }}</b>: {{ $t['alasan'] }}</li>@endforeach
                </ul>
            </section>
        @endif

        <form method="POST" action="{{ route('evaluasi.unggah.terapkan') }}" class="space-y-4" x-data="{ semua: true }">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            @if ($data['psn'])
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-isi"><input type="checkbox" x-model="semua" @change="$root.querySelectorAll('input[name=\'psn[]\']:not(:disabled)').forEach(c => c.checked = semua)" class="rounded border-slate-300 text-aksen">Pilih semua PSN</label>
                    <span class="label">Hanya sel tanpa galat yang disimpan. Galat ditandai merah dan dilewati.</span>
                    <button class="tombol-primer ml-auto py-1.5">Terapkan perubahan terpilih</button>
                </div>
            @else
                <div class="kartu text-center text-slate-600">Tidak ada perubahan: isi berkas sama dengan kertas kerja saat ini.</div>
            @endif

            @foreach ($data['psn'] as $p)
                @php($valid = collect($p['perubahan'])->whereNull('galat')->count())
                <section class="kartu !p-4" aria-label="{{ $p['nama'] }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2"><input type="checkbox" name="psn[]" value="{{ $p['psn_id'] }}" @checked($valid) @disabled(! $valid) class="rounded border-slate-300 text-aksen">
                            <span class="font-semibold text-slate-900">{{ $p['nama'] }}</span></label>
                        <span class="text-[12px] text-slate-500">{{ $p['kode'] }} · baris {{ $p['baris_excel'] }}</span>
                        <span class="badge-EV_{{ $p['status_akhir'] }}"><span class="titik-EV_{{ $p['status_akhir'] }}"></span>{{ $cfg['status'][$p['status_akhir']] }}</span>
                        <span class="ml-auto text-[12px] text-slate-600">{{ $valid }} siap · {{ count($p['perubahan']) - $valid }} galat</span>
                    </div>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="text-label text-slate-500"><tr><th class="w-64 py-1 pr-3 text-left">Kolom</th><th class="py-1 pr-3 text-left">Isi saat ini</th><th class="py-1 pr-3 text-left">Isi berkas</th><th class="w-72 py-1 text-left">Keterangan</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($p['perubahan'] as $x)
                                    <tr @class(['align-top', 'bg-red-50/60' => $x['galat']])>
                                        <td class="py-1 pr-3 font-semibold text-slate-700">{{ $x['label'] }}</td>
                                        <td class="max-w-[24rem] whitespace-pre-line py-1 pr-3 text-slate-500">{{ \Illuminate\Support\Str::limit($x['lama'], 1200) ?: '(kosong)' }}</td>
                                        <td class="max-w-[24rem] whitespace-pre-line py-1 pr-3 text-slate-900">{{ \Illuminate\Support\Str::limit($x['baru'], 1200) ?: '(dikosongkan)' }}</td>
                                        <td @class(['py-1', 'font-semibold text-red-700' => $x['galat'], 'text-green-700' => ! $x['galat']])>{{ $x['galat'] ?? 'Siap disimpan' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
            @if ($data['psn'])<div class="flex justify-end"><button class="tombol-primer py-1.5">Terapkan perubahan terpilih</button></div>@endif
        </form>
    </div>
</x-app-layout>
