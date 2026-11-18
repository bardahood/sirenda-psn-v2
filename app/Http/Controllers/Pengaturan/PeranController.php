<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Database\Seeders\PeranSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pengaturan > Peran & Hak Akses (Super Admin): centang menu/modul yang boleh diakses tiap peran.
 * Izin tetap berformat {modul}.{aksi} dan kumulatif (lihat < input < verifikasi < kelola).
 * Super Admin selalu penuh (Gate::before) sehingga tidak dapat diubah.
 */
class PeranController extends Controller
{
    public const PERAN_TETAP = 'Super Admin';

    public function index(Request $r)
    {
        $peran = Role::query()->withCount('users')->with('permissions:id,name')->orderBy('name')->get();
        $pilih = $peran->firstWhere('id', (int) $r->query('peran')) ?? $peran->firstWhere('name', '!=', self::PERAN_TETAP) ?? $peran->first();

        return view('pengaturan.peran', [
            'peran' => $peran,
            'pilih' => $pilih,
            'tingkat' => $pilih ? $this->tingkatPeran($pilih) : [],
            'modul' => $this->modul(),
            'bawaan' => array_keys(PeranSeeder::MATRIKS),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'nama' => ['required', 'string', 'max:100', 'regex:/^[\pL\pN .\/&()]+$/u', Rule::unique('roles', 'name')],
            'salin_dari' => ['nullable', Rule::exists('roles', 'id')],
        ], ['nama.regex' => 'Nama peran hanya boleh huruf, angka, spasi, titik, garis miring, &, dan tanda kurung.'], ['nama' => 'nama peran']);

        $role = Role::create(['name' => trim($data['nama']), 'guard_name' => 'web']);
        $sumber = ! empty($data['salin_dari']) ? Role::findById((int) $data['salin_dari']) : null;
        $izin = match (true) {
            $sumber === null => [],
            // Super Admin mendapat akses lewat Gate::before, bukan izin tersimpan: salin sebagai akses penuh.
            $sumber->name === self::PERAN_TETAP => $this->izinDari(array_map(fn ($m) => array_key_last($m['aksi']), $this->modul())),
            default => $sumber->permissions->pluck('name')->all(),
        };
        $role->syncPermissions($izin);
        $this->catat($role, 'INSERT', ['peran' => $role->name, 'izin' => $izin]);

        return redirect()->route('pengaturan.peran.index', ['peran' => $role->id])->with('status', "Peran \"{$role->name}\" dibuat. Atur centang hak aksesnya lalu simpan.");
    }

    public function update(Request $r, int $peran)
    {
        $role = Role::findById($peran);
        abort_if($role->name === self::PERAN_TETAP, 403, 'Hak akses Super Admin selalu penuh dan tidak dapat diubah.');

        $modul = $this->modul();
        $aturan = [];
        foreach ($modul as $kode => $m) {
            $aturan["hak.{$kode}"] = ['nullable', Rule::in(array_keys($m['aksi']))];
        }
        $hak = $r->validate($aturan)['hak'] ?? [];

        $lama = $role->permissions->pluck('name')->sort()->values()->all();
        $baru = $this->izinDari($hak);
        // Pertahankan tingkat di atas yang ditampilkan (cadangan, belum dipakai aplikasi) bila tingkat tertinggi tetap dicentang.
        foreach ($modul as $kode => $m) {
            if (($hak[$kode] ?? null) === array_key_last($m['aksi'])) {
                foreach (array_slice(PeranSeeder::TINGKAT, array_search(array_key_last($m['aksi']), PeranSeeder::TINGKAT) + 1) as $aksi) {
                    if (in_array("{$kode}.{$aksi}", $lama, true)) {
                        $baru[] = "{$kode}.{$aksi}";
                    }
                }
            }
        }
        sort($baru);

        if ($baru !== $lama) {
            DB::transaction(function () use ($role, $baru, $lama) {
                foreach ($baru as $nama) {
                    Permission::findOrCreate($nama);
                }
                $role->syncPermissions($baru);
                $this->catat($role, 'UPDATE', ['izin' => $baru], ['izin' => $lama]);
            });
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return redirect()->route('pengaturan.peran.index', ['peran' => $role->id])
            ->with('status', $baru === $lama ? 'Tidak ada perubahan hak akses.' : "Hak akses peran \"{$role->name}\" tersimpan. Berlaku pada halaman berikutnya yang dibuka pengguna.");
    }

    public function destroy(int $peran)
    {
        $role = Role::withCount('users')->findOrFail($peran);
        if (in_array($role->name, array_keys(PeranSeeder::MATRIKS), true)) {
            return back()->withErrors(['peran' => 'Peran bawaan aplikasi tidak dapat dihapus.']);
        }
        if ($role->users_count > 0) {
            return back()->withErrors(['peran' => "Peran \"{$role->name}\" masih dipakai {$role->users_count} pengguna. Pindahkan pengguna ke peran lain lebih dulu."]);
        }
        $this->catat($role, 'DELETE', null, ['peran' => $role->name, 'izin' => $role->permissions->pluck('name')->all()]);
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('pengaturan.peran.index')->with('status', "Peran \"{$role->name}\" dihapus.");
    }

    /** Tingkat tertinggi yang dimiliki peran per modul (hanya tingkat yang ditampilkan), null bila tanpa akses. */
    protected function tingkatPeran(Role $role): array
    {
        $punya = $role->name === self::PERAN_TETAP ? null : $role->permissions->pluck('name')->flip();
        $hasil = [];
        foreach ($this->modul() as $kode => $m) {
            $hasil[$kode] = null;
            foreach (array_keys($m['aksi']) as $aksi) {
                if ($punya === null || isset($punya["{$kode}.{$aksi}"])) {
                    $hasil[$kode] = $aksi;
                }
            }
        }

        return $hasil;
    }

    /** Izin kumulatif dari tingkat tertinggi per modul. */
    protected function izinDari(array $hak): array
    {
        $izin = [];
        foreach ($hak as $kode => $tingkat) {
            if ($tingkat === null || $tingkat === '') {
                continue;
            }
            foreach (array_slice(PeranSeeder::TINGKAT, 0, array_search($tingkat, PeranSeeder::TINGKAT) + 1) as $aksi) {
                $izin[] = "{$kode}.{$aksi}";
            }
        }

        return $izin;
    }

    protected function modul(): array
    {
        return array_intersect_key(config('psn_dashboard.rbac.modul'), array_flip(PeranSeeder::MODUL));
    }

    protected function catat(Role $role, string $aksi, ?array $baru, ?array $lama = null): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username, 'tabel' => 'roles', 'record_id' => $role->id,
            'aksi' => $aksi, 'nilai_lama' => $lama, 'nilai_baru' => $baru]);
    }
}
