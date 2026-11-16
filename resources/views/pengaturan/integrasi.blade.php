@php
    use App\Http\Controllers\Pengaturan\IntegrasiController as IC;
    $waktu = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->translatedFormat('j M Y H:i:s') : '–';
    $lalu = fn ($t) => $t ? \Illuminate\Support\Carbon::parse($t)->diffForHumans() : 'belum pernah';
    $warna = [
        'ok' => 'bg-green-50 text-green-800 ring-green-600/40', 'waspada' => 'bg-amber-50 text-amber-900 ring-amber-600/40',
        'galat' => 'bg-red-50 text-red-800 ring-red-600/40', 'netral' => 'bg-slate-100 text-slate-700 ring-slate-500/40',
    ];
    $titik = ['ok' => 'bg-green-600', 'waspada' => 'bg-amber-500', 'galat' => 'bg-red-600', 'netral' => 'bg-slate-400'];
    $hasilWarna = ['DITERIMA' => 'ok', 'BERHASIL' => 'ok', 'TETAP' => 'netral', 'VERSI_LAMA' => 'waspada', 'DITOLAK' => 'galat', 'GAGAL' => 'galat'];
    $detakBaru = $detak && $detak->gt(now()->subMinutes(5));
    $kesehatan = [
        ['Sinkron PEKS3', $aktif ? 'ok' : 'netral', $aktif ? 'Aktif' : 'Belum aktif', $aktif ? 'PEKS3_AKTIF=true' : 'Isi PEKS3_AKTIF=true di .env bila siap.'],
        ['Kunci bersama', $kunciTerpasang ? 'ok' : 'galat', $kunciTerpasang ? 'Terpasang' : 'Belum terpasang', $kunciTerpasang ? 'PEKS3_RAHASIA minimal 32 karakter (isi tidak ditampilkan).' : 'Isi PEKS3_RAHASIA di .env.'],
        ['Basis data cermin', $cerminSiap ? 'ok' : 'galat', $cerminSiap ? 'Siap' : 'Belum siap', $cerminSiap ? ($status['psn_di_cermin'].' PSN di cermin') : \Illuminate\Support\Str::limit($cerminPesan, 160)],
        ['Penjadwal (cron)', ! $aktif ? 'netral' : ($detakBaru ? 'ok' : 'galat'), ! $aktif ? 'Tidak dipantau' : ($detakBaru ? 'Berjalan' : 'Tidak berjalan'), 'Terakhir berjalan '.$lalu($detak).'.'],
        ['Kiriman dari PEKS3', $kirimTerakhir ? 'ok' : 'netral', $kirimTerakhir ? 'Pernah diterima' : 'Belum ada', 'Terakhir '.$lalu($kirimTerakhir).'.'],
        ['Galat penerapan', $status['galat_terakhir'] ? 'galat' : 'ok', $status['galat_terakhir'] ? 'Ada galat' : 'Tidak ada', $status['galat_terakhir'] ? \Illuminate\Support\Str::limit($status['galat_terakhir'], 160) : 'Penerapan terakhir tanpa galat.'],
    ];
@endphp
<x-app-layout judul="Pengaturan">
    <div class="mx-auto max-w-[1376px]">
        @include('pengaturan._tab')

        <section class="kartu mb-6" aria-labelledby="h-kesehatan">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 id="h-kesehatan" class="judul-panel">Sinkron SIRENDA PEKS3 ke Dashboard</h2>
                    <p class="label">Alamat API untuk tim PEKS3: <code class="select-all">{{ $urlApi }}</code></p>
                </div>
                <form method="POST" action="{{ route('pengaturan.integrasi.terapkan') }}">
                    @csrf
                    <button class="tombol-primer" @disabled(! $aktif || ! $cerminSiap) title="Terapkan isi basis data cermin ke dashboard pada putaran penjadwal berikutnya">Terapkan sekarang</button>
                </form>
            </div>
            <ul class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($kesehatan as [$judul, $k, $label, $ket])
                    <li class="rounded-kartu border border-slate-200 p-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-isi font-semibold text-slate-800">{{ $judul }}</span>
                            <span class="badge {{ $warna[$k] }}"><span class="mr-1 inline-block h-2 w-2 rounded-full {{ $titik[$k] }}"></span>{{ $label }}</span>
                        </div>
                        <p class="label mt-1 break-words">{{ $ket }}</p>
                    </li>
                @endforeach
            </ul>
        </section>

        <div class="mb-6 grid grid-cols-12 gap-6">
            <section class="kartu col-span-12 xl:col-span-7" aria-labelledby="h-antrean">
                <h2 id="h-antrean" class="judul-panel mb-3">Antrean dan Penerapan</h2>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-isi sm:grid-cols-2">
                    <div><dt class="label">Antrean</dt><dd>
                        @if ($status['tertunda'])<span class="badge {{ $warna['waspada'] }}">Menunggu diterapkan</span> sejak {{ $waktu($status['tertunda_sejak']) }}
                        @else<span class="badge {{ $warna['ok'] }}">Tidak ada antrean</span>@endif
                    </dd></div>
                    <div><dt class="label">Kiriman terakhir masuk antrean</dt><dd>{{ $waktu($status['perubahan_terakhir_at']) }}</dd></div>
                    <div><dt class="label">Penerapan berhasil terakhir</dt><dd>{{ $waktu($status['diterapkan_at']) }} @if ($status['durasi_detik'] !== null)<span class="label">({{ number_format($status['durasi_detik'], 1, ',', '.') }} detik)</span>@endif</dd></div>
                    <div><dt class="label">Penerapan terakhir</dt><dd>
                        @if ($terapkanTerakhir)<span class="badge {{ $warna[$hasilWarna[$terapkanTerakhir->hasil] ?? 'netral'] }}">{{ IC::HASIL[$terapkanTerakhir->hasil] ?? $terapkanTerakhir->hasil }}</span> {{ $waktu($terapkanTerakhir->created_at) }}@else – @endif
                    </dd></div>
                    <div><dt class="label">PSN di basis data cermin</dt><dd class="angka">{{ $status['psn_di_cermin'] ?? '–' }}</dd></div>
                    <div><dt class="label">PSN di dashboard (hasil impor)</dt><dd class="angka">{{ $status['psn_di_dashboard'] }}</dd></div>
                </dl>
                <p class="label mt-3">Kiriman PEKS3 diterapkan otomatis setelah {{ $jeda }} detik tanpa kiriman baru. Tombol "Terapkan sekarang" hanya mempercepat penerapan pada putaran penjadwal berikutnya; prosesnya tetap berjalan di latar belakang.</p>
            </section>
            <section class="kartu col-span-12 xl:col-span-5" aria-labelledby="h-rekap">
                <h2 id="h-rekap" class="judul-panel mb-3">Rekap 24 Jam Terakhir</h2>
                <ul class="space-y-2 text-isi">
                    @foreach (IC::HASIL as $kode => $label)
                        <li class="flex items-center justify-between"><span class="badge {{ $warna[$hasilWarna[$kode]] }}">{{ $label }}</span><span class="angka">{{ number_format($rekap[$kode] ?? 0, 0, ',', '.') }}</span></li>
                    @endforeach
                </ul>
                @if ($psi)
                    <h3 class="mt-5 text-isi font-semibold text-slate-800">Usulan dari Aplikasi Perencanaan (SIF/PSI)</h3>
                    <p class="label mt-1">Ambil otomatis tiap jam: {{ $psi['otomatis'] ? 'aktif' : 'tidak aktif' }} · Alamat SIF {{ $psi['url_terpasang'] ? 'terpasang' : 'belum diisi' }} · {{ $psi['usulan'] }} usulan · pembaruan terakhir {{ $lalu($psi['terakhir']) }}.</p>
                @endif
            </section>
        </div>

        <section class="kartu" aria-labelledby="h-log">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                <h2 id="h-log" class="judul-panel">Log Kiriman dan Penerapan</h2>
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <label class="text-isi"><span class="label block">Jenis</span><select name="jenis" class="rounded-lg border-slate-300 py-1.5"><option value="">Semua</option>@foreach (IC::JENIS as $k => $l)<option value="{{ $k }}" @selected($filter['jenis'] === $k)>{{ $l }}</option>@endforeach</select></label>
                    <label class="text-isi"><span class="label block">Hasil</span><select name="hasil" class="rounded-lg border-slate-300 py-1.5"><option value="">Semua</option>@foreach (IC::HASIL as $k => $l)<option value="{{ $k }}" @selected($filter['hasil'] === $k)>{{ $l }}</option>@endforeach</select></label>
                    <label class="text-isi"><span class="label block">ID PSN PEKS3</span><input type="number" name="psn" value="{{ $filter['psn'] }}" class="w-28 rounded-lg border-slate-300 py-1.5"></label>
                    <button class="tombol-garis">Saring</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-isi">
                    <thead class="border-b border-slate-200 text-label text-slate-600"><tr><th class="py-2 pr-3">Waktu</th><th class="py-2 pr-3">Jenis</th><th class="py-2 pr-3">ID PSN</th><th class="py-2 pr-3">Hasil</th><th class="py-2 pr-3">Keterangan</th><th class="py-2">IP</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($log as $l)
                            @php($ring = $l->ringkasan ? json_decode($l->ringkasan, true) : null)
                            <tr class="align-top">
                                <td class="whitespace-nowrap py-2 pr-3">{{ $waktu($l->created_at) }}</td>
                                <td class="py-2 pr-3">{{ IC::JENIS[$l->jenis] ?? $l->jenis }}</td>
                                <td class="angka py-2 pr-3">{{ $l->legacy_psn_id ?? '–' }}</td>
                                <td class="py-2 pr-3"><span class="badge {{ $warna[$hasilWarna[$l->hasil] ?? 'netral'] }}">{{ IC::HASIL[$l->hasil] ?? $l->hasil }}</span></td>
                                <td class="max-w-[40rem] py-2 pr-3 break-words">
                                    {{ $l->pesan }}
                                    @if ($l->jenis === 'TERAPKAN' && $ring)
                                        <span class="label">{{ isset($ring['psn']) ? $ring['psn'].' PSN' : '' }}{{ isset($ring['durasi_detik']) ? ', '.number_format($ring['durasi_detik'], 1, ',', '.').' detik' : '' }}{{ ! empty($ring['peringatan']) ? ', '.$ring['peringatan'].' peringatan impor' : '' }}</span>
                                    @elseif ($ring && is_array($ring['baris'] ?? null))
                                        <span class="label">{{ array_sum($ring['baris']) }} baris</span>
                                    @endif
                                </td>
                                <td class="py-2 text-label">{{ $l->ip_address ?? '–' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-600">Belum ada log.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $log->links() }}</div>
        </section>
    </div>
</x-app-layout>
