<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $user = auth()->user();

        // Jika user tidak punya salah satu dari role yang diizinkan
        if (!in_array($user->role_id, $roles)) {
            // Redirect berdasarkan role
            return redirect('/');
        }

        return $next($request);
    }
}
