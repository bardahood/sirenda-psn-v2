<?php

namespace App\Http\Middleware;

use App\Support\ProsesBerat as Batas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rute ekspor/unggah Excel: naikkan batas memori & waktu PHP (lihat App\Support\ProsesBerat). */
class ProsesBerat
{
    public function handle(Request $request, Closure $next): Response
    {
        Batas::siapkan();

        return $next($request);
    }
}
