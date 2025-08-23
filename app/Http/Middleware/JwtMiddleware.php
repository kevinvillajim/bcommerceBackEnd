<?php

namespace App\Http\Middleware;

use App\Domain\Interfaces\JwtServiceInterface;
use Closure;
use Illuminate\Http\Request;

class JwtMiddleware
{
    protected $jwtService;

    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function handle(Request $request, Closure $next)
    {
        try {
            // Extraer token del encabezado
            if (! $request->headers->has('Authorization')) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Token not provided',
                ], 401);
            }

            $authHeader = $request->header('Authorization');
            $token = str_replace('Bearer ', '', $authHeader);

            if (empty($token)) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Token not provided',
                ], 401);
            }

            // Validar token
            if (! $this->jwtService->validateToken($token)) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid token',
                ], 401);
            }

            // Obtener usuario
            $user = $this->jwtService->getUserFromToken($token);

            if (! $user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'User not found',
                ], 401);
            }

            // Verificar si el usuario está bloqueado
            if ($user->isBlocked()) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'Your account has been blocked.',
                ], 403);
            }

            // Adjuntar usuario a la solicitud
            $request->merge(['authenticated_user' => $user]);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Token error: '.$e->getMessage(),
            ], 401);
        }
    }
}
