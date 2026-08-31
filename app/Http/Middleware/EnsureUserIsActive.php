<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Expulsa de la sesion a usuarios desactivados mientras navegan y
     * mantiene fresca la marca de ultimo acceso (max 1 escritura cada 5 min).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'username' => 'Tu usuario fue desactivado. Contacta al administrador.',
            ]);
        }

        if ($user && (! $user->last_login_at || $user->last_login_at->lt(now()->subMinutes(5)))) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
