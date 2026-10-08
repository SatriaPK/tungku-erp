<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Memutus akses akun yang dinonaktifkan walaupun sesinya masih hidup.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            abort(Response::HTTP_FORBIDDEN, 'Akun Anda dinonaktifkan.');
        }

        return $next($request);
    }
}