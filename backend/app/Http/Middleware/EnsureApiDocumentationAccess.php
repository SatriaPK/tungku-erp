<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDocumentationAccess
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment(['local', 'testing'])) {
            $user = $request->user('sanctum');
            abort_unless($user !== null && $user->is_active && $user->isManager(), 403);
        }

        return $next($request);
    }
}
