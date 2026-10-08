<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak akun nonaktif (logout paksa) dan mengarahkan pengguna yang wajib
 * mengganti kata sandi (mis. akun hasil impor) ke halaman ganti kata sandi.
 */
class AkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            return $request->expectsJson()
                ? response()->json(['message' => 'Akun Anda dinonaktifkan.'], 403)
                : redirect()->route('login')->withErrors(['login' => 'Akun Anda dinonaktifkan. Hubungi administrator.']);
        }

        if ($user?->wajib_ganti_password && ! $request->routeIs('password.*', 'logout')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Anda wajib mengganti kata sandi.'], 403)
                : redirect()->route('password.edit');
        }

        return $next($request);
    }
}
