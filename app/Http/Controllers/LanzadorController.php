<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LanzadorSesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class LanzadorController extends Controller
{
    /**
     * Cierra la sesion asociada al token del lanzador (llamado al cerrar
     * la ventana de la app). No requiere autenticacion ni permiso, pero solo
     * admite peticiones desde la propia maquina (loopback) y esta limitada
     * por throttle (10/min).
     */
    public function cerrarSesion(Request $request): Response
    {
        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort(403, 'Acceso no permitido.');
        }

        $registro = LanzadorSesion::find(hash('sha256', (string) $request->input('token')));

        if ($registro) {
            Session::getHandler()->destroy($registro->session_id);
            $registro->delete();
        }

        return response()->noContent();
    }
}
