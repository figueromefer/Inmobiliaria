<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autenticación por token simple.
 * Requiere el header:  X-Api-Token: <TU_TOKEN>
 *
 * El token esperado se lee desde config('services.clients_api.token'),
 * que a su vez toma el valor de la variable de entorno CLIENTS_API_TOKEN.
 */
class TokenAuth
{
    /**
     * Maneja la solicitud entrante verificando el token del header.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1) Leer el token que envía el cliente en el header X-Api-Token
        $incomingToken = (string) $request->header('X-Api-Token');

        // 2) Leer el token “bueno” desde configuración (.env -> config/services.php)
        $expectedToken = (string) config('services.clients_api.token');

        // 3) Validar: si falta cualquiera o no coinciden → 401
        if ($incomingToken === '' || $expectedToken === '' || !hash_equals($expectedToken, $incomingToken)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 4) Token válido → continuar
        return $next($request);
    }
}
