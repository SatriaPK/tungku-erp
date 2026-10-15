<?php

namespace App\Http\Middleware;

use App\Enum\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Pemakaian di route: ->middleware('role:manager') atau 'role:manager,operator'.
     * Nama peran yang salah ketik akan melempar error, bukan lolos diam-diam.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => Role::from($role), $roles);
        $user = $request->user();

        if ($user === null || ! in_array($user->role, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Anda tidak memiliki akses ke sumber daya ini.');
        }

        return $next($request);
    }
}
