<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Middleware para validar que el usuario tenga rol 'payment' independiente o cualquier admin
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

            // Verificar si el usuario tiene rol payment independiente O es admin (cualquier admin)
            $hasPaymentRole = $user->isPaymentUser();
            $isAnyAdmin = $user->isAdmin();

            if (!$hasPaymentRole && !$isAnyAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Payment privileges required.',
                    'has_payment_role' => $hasPaymentRole,
                    'is_admin' => $isAnyAdmin,
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
