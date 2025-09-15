<?php

namespace App\Http\Middleware;

use App\Models\Seller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SellerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Verificar si el usuario está autenticado
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuario no autenticado',
            ], 401);
        }

        // Verificar si el usuario está bloqueado
        if ($user->is_blocked) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tu cuenta de usuario está bloqueada',
            ], 403);
        }

        // Método 1: Verificar por el rol del usuario (admin siempre tiene acceso)
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Método 2: Verificar si el usuario tiene un registro en la tabla de vendedores
        $seller = Seller::where('user_id', $user->id)->first();

        if (! $seller) {
            return response()->json([
                'status' => 'error',
                'message' => 'No estás registrado como vendedor. Debes registrarte como vendedor para acceder a esta funcionalidad.',
            ], 403);
        }

        // Verificar el status del vendedor
        // EXCEPCIÓN: Permitir acceso de solo lectura al dashboard para vendedores suspendidos/inactivos
        $readOnlyRoutes = [
            'api/seller/dashboard',
            'api/seller/info',
        ];

        $currentPath = $request->path();
        $isReadOnlyRoute = false;

        foreach ($readOnlyRoutes as $route) {
            if (str_contains($currentPath, $route)) {
                $isReadOnlyRoute = true;
                break;
            }
        }

        // Si no es una ruta de solo lectura y el vendedor no está activo, denegar acceso
        if (! $isReadOnlyRoute && $seller->status !== 'active') {
            $statusMessages = [
                'pending' => 'Tu cuenta de vendedor está pendiente de aprobación. No puedes realizar operaciones de venta.',
                'inactive' => 'Tu cuenta de vendedor está inactiva. Contacta al administrador.',
                'suspended' => 'Tu cuenta de vendedor está suspendida. No puedes realizar operaciones de venta.',
                'rejected' => 'Tu solicitud de vendedor ha sido rechazada',
            ];

            $message = $statusMessages[$seller->status] ?? 'Tu cuenta de vendedor no está disponible';

            // Agregar razón si existe
            if ($seller->status_reason) {
                $message .= ' Motivo: '.$seller->status_reason;
            }

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'seller_status' => $seller->status,
                'status_reason' => $seller->status_reason ?? null,
            ], 403);
        }

        // Para rutas de solo lectura con vendedor suspendido/inactivo, agregar flag
        if ($isReadOnlyRoute && $seller->status !== 'active') {
            $request->attributes->add([
                'seller_read_only' => true,
                'seller_limited_access' => true,
            ]);
        }

        // Si llegamos aquí, el usuario es un vendedor activo
        // Agregar información del vendedor al request para uso posterior
        $request->attributes->add([
            'seller' => $seller,
            'seller_id' => $seller->id,
        ]);

        return $next($request);
    }
}
