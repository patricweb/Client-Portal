<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $allowed = $role === 'staff'
            ? $request->user()?->isStaff()
            : $request->user()?->role->value === $role;
        abort_unless($allowed, 403);

        return $next($request);
    }
}
