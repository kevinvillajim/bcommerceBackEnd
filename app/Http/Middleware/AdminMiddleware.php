<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        try {
            // Verificar token JWT y obtener usuario
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Verificar si el usuario es administrador
            if (! $user->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied. Admin privileges required.',
                ], 403);
            }

            // Verificar si el admin está activo
            if ($user->admin->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your admin account is not active.',
                ], 403);
            }

            // Si se requiere un rol específico
            if ($role && $user->admin->role !== $role && $user->admin->role !== 'super_admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => "Access denied. '{$role}' role required.",
                ], 403);
            }

            return $next($request);
        } catch (TokenExpiredException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token has expired',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token is invalid',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authorization token not found',
            ], 401);
        }
    }
}
