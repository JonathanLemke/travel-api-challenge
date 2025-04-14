<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;


class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Tentar parsear o token da requisição e autenticar o usuário
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                // Uma verificação extra, ja que o authenticate() já lança uma exceção
                return response()->json(['Status' => 'User not found associated with token'], 404);
            }
        } catch (Exception $e) {
            // Tratando os diferente tipos de exceções do JWT
            if ($e instanceof TokenInvalidException) {
                return response()->json(['Status' => 'Token is invalid'], 401);
            } elseif ($e instanceof TokenExpiredException) {
                return response()->json(['Status' => 'Token has expired'], 401);
            } else {
                // Outras exceções
                return response()->json(['Status' => 'Authorization Token not found or other error'], 401);
            }
        }
        return $next($request);
    }
}
