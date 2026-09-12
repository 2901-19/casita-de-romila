<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\LanzadorSesion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrarLanzador
{
    /**
     * Vincula la sesión del lanzador (token ?_lanzador) con el id de sesión
     * actual para que al cerrar la ventana se pueda cerrar esa sesión.
     * El token se guarda hasheado (SHA-256).
     *
     * Endurecimiento: el token se registra una sola vez por sesión y solo se
     * re-vincula cuando cambia el usuario autenticado (login/logout), en lugar
     * de re-escribir el registro en cada request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $storedToken = $session->get('_lanzador_token');
        $registeredUserId = $session->get('_lanzador_registered_user', '__none__');
        $currentUserId = $request->user()?->id ?? 'guest';

        if (! $storedToken) {
            $token = $request->query('_lanzador');
            if ($token) {
                $session->put('_lanzador_token', $token);
                $this->registrar($token, $request);
                $session->put('_lanzador_registered_user', $currentUserId);
            }
        } elseif ($registeredUserId !== $currentUserId) {
            $this->registrar($storedToken, $request);
            $session->put('_lanzador_registered_user', $currentUserId);
        }

        return $next($request);
    }

    private function registrar(string $token, Request $request): void
    {
        LanzadorSesion::updateOrCreate(
            ['token' => hash('sha256', $token)],
            ['session_id' => $request->session()->getId(), 'user_id' => $request->user()?->id]
        );
    }
}
