<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Middleware para validar que el usuario tenga rol 'payment' o 'super_admin'
 */
class PaymentRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Verificar token JWT y obtener usuario
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            // Verificar si el usuario tiene un admin asociado
            if (!$user->admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Admin account required.',
                ], 403);
            }

            // Verificar si el admin está activo
            if ($user->admin->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your admin account is not active.',
                ], 403);
            }

            // Verificar si tiene rol 'payment' o 'super_admin'
            $allowedRoles = ['payment', 'super_admin'];
            if (!in_array($user->admin->role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Payment privileges required.',
                    'required_roles' => $allowedRoles,
                    'current_role' => $user->admin->role,
                ], 403);
            }

            return $next($request);

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired',
            ], 401);

        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token is invalid',
            ], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token not found',
            ], 401);
        }
    }
}
