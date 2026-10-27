<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    // Pemakaian: ->middleware(EnsureRole::class . ':admin,supervisor')
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || ! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Anda tidak punya akses ke fitur ini.'], 403);
        }

        return $next($request);
    }
}
