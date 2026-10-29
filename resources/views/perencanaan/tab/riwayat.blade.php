<div class="grid grid-cols-12 gap-5">
    @if ($dariPsi)
        <section class="kartu col-span-12 xl:col-span-7" aria-labelledby="h-log">
            <h3 id="h-log" class="judul-panel mb-3">Riwayat Integrasi PSI</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-[12px]">
                    <thead class="border-b border-slate-200 text-[11px] text-slate-500"><tr><th class="py-1.5 pr-2">Waktu</th><th class="py-1.5 pr-2">Arah</th><th class="py-1.5 pr-2">Aksi</th><th class="py-1.5 pr-2">HTTP</th><th class="py-1.5">Keterangan</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($logIntegrasi as $l)
                            <tr class="align-top"><td class="whitespace-nowrap py-1.5 pr-2">{{ $l->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="py-1.5 pr-2">{{ $l->arah === 'MASUK' ? 'PSI → SIRENDA' : 'SIRENDA → PSI' }}</td>
                                <td class="py-1.5 pr-2">{{ ['USULAN' => 'Data usulan', 'AMBIL_USULAN' => 'Ambil usulan', 'TARIK' => 'Tarik usulan', 'AMBIL_HASIL' => 'Ambil hasil', 'KIRIM_HASIL' => 'Kirim hasil'][$l->aksi] ?? $l->aksi }}</td>
                                <td class="py-1.5 pr-2"><span @class(['badge', 'bg-green-50 text-green-800 ring-green-600/40' => $l->berhasil, 'bg-red-50 text-red-800 ring-red-600/40' => ! $l->berhasil])>{{ $l->status_http ?? '–' }} {{ $l->berhasil ? 'Berhasil' : 'Gagal' }}</span></td>
                                <td class="py-1.5">{{ $l->pesan }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-slate-500">Belum ada pertukaran data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
    <section @class(['kartu col-span-12', 'xl:col-span-5' => $dariPsi]) aria-labelledby="h-jejak">
        <h3 id="h-jejak" class="judul-panel mb-3">Jejak Audit</h3>
        <ul class="divide-y divide-slate-100 text-[12px]">
            @forelse ($jejak as $j)
                <li class="py-1.5"><b>{{ $j->user_label ?? 'Sistem' }}</b> · {{ $j->aksi }} {{ str_replace('_', ' ', $j->tabel) }}
                    <span class="block text-[11px] text-slate-500">{{ \Illuminate\Support\Carbon::parse($j->created_at)->format('d/m/Y H:i') }}{{ $j->sumber && $j->sumber !== 'APLIKASI' ? ' · '.$j->sumber : '' }}</span></li>
            @empty
                <li class="py-4 text-center text-slate-500">Belum ada jejak.</li>
            @endforelse
        </ul>
    </section>
</div>
