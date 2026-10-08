<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Pengaturan > Pengguna & Peran (Super Admin). */
class PenggunaController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'peran' => ['nullable', 'string'], 'aktif' => ['nullable', 'in:0,1']]);

        $pengguna = User::query()->with(['roles', 'unitKerja'])
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('username', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->when($f['peran'] ?? null, fn ($q, $v) => $q->role($v))
            ->when(isset($f['aktif']), fn ($q) => $q->where('is_active', (bool) $f['aktif']))
            ->orderByDesc('is_active')->orderBy('name')->paginate(25)->withQueryString();

        return view('pengaturan.pengguna.index', ['pengguna' => $pengguna, 'filter' => $f, 'peran' => Role::orderBy('name')->pluck('name')]);
    }

    public function create()
    {
        return view('pengaturan.pengguna.form', ['user' => new User(['is_active' => true]), 'opsi' => $this->opsi()]);
    }

    public function store(Request $r)
    {
        $data = $this->validasi($r);
        $sandi = Str::password(16);
        $user = User::create(collect($data)->except('peran')->all() + ['password' => Hash::make($sandi), 'wajib_ganti_password' => true]);
        $this->aturPeran($user, $data['peran']);

        return redirect()->route('pengaturan.pengguna.edit', $user)
            ->with('status', "Pengguna dibuat. Kata sandi sementara (sampaikan secara aman, wajib diganti saat login pertama): {$sandi}");
    }

    public function edit(User $pengguna)
    {
        return view('pengaturan.pengguna.form', ['user' => $pengguna->load('roles'), 'opsi' => $this->opsi()]);
    }

    public function update(Request $r, User $pengguna)
    {
        $data = $this->validasi($r, $pengguna);

        // Mencegah Super Admin mengunci dirinya sendiri.
        if ($pengguna->is(Auth::user()) && (! $data['is_active'] || $data['peran'] !== 'Super Admin')) {
            return back()->withErrors(['peran' => 'Anda tidak dapat menonaktifkan atau mencabut peran Super Admin akun Anda sendiri.']);
        }

        $pengguna->update(collect($data)->except('peran')->all());
        $this->aturPeran($pengguna, $data['peran']);

        return back()->with('status', 'Perubahan pengguna tersimpan.');
    }

    public function resetSandi(User $pengguna)
    {
        $sandi = Str::password(16);
        $pengguna->forceFill(['password' => Hash::make($sandi), 'wajib_ganti_password' => true])->save();
        $this->catat($pengguna, 'UPDATE', ['reset_kata_sandi' => true]);

        return back()->with('status', "Kata sandi direset. Kata sandi sementara (wajib diganti saat login): {$sandi}");
    }

    protected function aturPeran(User $u, string $peran): void
    {
        $lama = $u->getRoleNames()->all();
        if ($lama !== [$peran]) {
            $u->syncRoles([$peran]);
            $this->catat($u, 'UPDATE', ['peran' => $peran], ['peran' => implode(', ', $lama) ?: null]);
        }
    }

    protected function catat(User $u, string $aksi, array $baru, ?array $lama = null): void
    {
        AuditLog::create(['user_id' => Auth::id(), 'user_label' => Auth::user()?->username, 'tabel' => 'users', 'record_id' => $u->id,
            'aksi' => $aksi, 'nilai_lama' => $lama, 'nilai_baru' => $baru]);
    }

    protected function validasi(Request $r, ?User $u = null): array
    {
        $data = $r->validate([
            'username' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._@-]+$/', Rule::unique('users')->ignore($u?->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($u?->id)],
            'unit_kerja_id' => ['nullable', 'exists:ref_unit_kerja,id'],
            'peran' => ['required', Rule::exists('roles', 'name')],
            'is_active' => ['boolean'],
        ], ['username.regex' => 'Nama pengguna hanya boleh huruf kecil, angka, titik, garis bawah, @, dan tanda hubung.'],
            ['username' => 'nama pengguna', 'name' => 'nama', 'unit_kerja_id' => 'unit kerja']);
        $data['is_active'] = $r->boolean('is_active');

        // Peran terbatas (¹) wajib punya unit kerja agar cakupan aksesnya terdefinisi.
        if (in_array($data['peran'], config('psn_dashboard.rbac.peran_ubah_terbatas'), true) && empty($data['unit_kerja_id'])) {
            throw ValidationException::withMessages(['unit_kerja_id' => 'Peran ini wajib memiliki unit kerja (cakupan data).']);
        }

        return $data;
    }

    protected function opsi(): array
    {
        return [
            'peran' => Role::orderBy('name')->pluck('name'),
            'unit' => DB::table('ref_unit_kerja')->where('is_aktif', true)->orderBy('jenis')->orderBy('nama')->get(['id', 'nama', 'jenis'])->groupBy('jenis'),
        ];
    }
}
