<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']],
            [], ['login' => 'nama pengguna atau email', 'password' => 'kata sandi']);

        // Batas 5 percobaan per menit per (login, IP).
        $kunci = Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            throw ValidationException::withMessages(['login' => 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.']);
        }

        $kolom = str_contains($data['login'], '@') ? 'email' : 'username';
        $user = User::where($kolom, Str::lower($data['login']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->is_active) {
            RateLimiter::hit($kunci);
            $this->log($user?->id, $data['login'], 'GAGAL', $request);
            throw ValidationException::withMessages(['login' => 'Nama pengguna atau kata sandi salah, atau akun tidak aktif.']);
        }

        RateLimiter::clear($kunci);
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'jumlah_login' => $user->jumlah_login + 1])->saveQuietly();
        $this->log($user->id, $user->username, 'LOGIN', $request);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        if ($user = $request->user()) {
            $this->log($user->id, $user->username, 'LOGOUT', $request);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword()
    {
        return view('auth.ganti-password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ], [], ['password_lama' => 'kata sandi lama', 'password' => 'kata sandi baru']);

        $request->user()->forceFill(['password' => Hash::make($request->password), 'wajib_ganti_password' => false])->save();

        return redirect()->route('dashboard')->with('status', 'Kata sandi berhasil diganti.');
    }

    protected function log(?int $userId, string $username, string $aktivitas, Request $request): void
    {
        DB::table('login_log')->insert(['user_id' => $userId, 'username' => Str::limit($username, 250), 'aktivitas' => $aktivitas, 'ip_address' => $request->ip(), 'created_at' => now()]);
    }
}
