<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();

        // Check if user is blocked
        if ($user->isBlocked()) {
            Auth::logout();

            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Your account has been blocked.',
            ], 403);
        }
        
        // Check seller status and create notification if needed
        $seller = \App\Models\Seller::where('user_id', $user->id)->first();
        \Log::info("🔍 DEBUG - Seller encontrado:", ['seller_id' => $seller?->id, 'status' => $seller?->status, 'user_id' => $user->id]);
        
        if ($seller && in_array($seller->status, ['suspended', 'inactive'])) {
            \Log::info("🚨 Seller con status problemático detectado durante login", [
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'seller_status' => $seller->status,
                'store_name' => $seller->store_name
            ]);
            
            // Determinar el tipo de notificación específico
            $notificationType = $seller->status === 'suspended' ? 'seller_suspended' : 'seller_inactive';
            
            // Verificar si ya existe una notificación NO LEÍDA del tipo específico
            $unreadNotification = \App\Models\Notification::where('user_id', $user->id)
                ->where('type', $notificationType)
                ->where('read', false)
                ->first();
            
            $shouldCreateNotification = false;
            
            if (!$unreadNotification) {
                // No hay notificación no leída del tipo específico, crear una nueva
                $shouldCreateNotification = true;
                \Log::info("✅ No hay notificación no leída específica para status, creando nueva", [
                    'user_id' => $user->id,
                    'notification_type' => $notificationType,
                    'seller_status' => $seller->status
                ]);
            } else {
                \Log::info("ℹ️ Ya existe notificación no leída del tipo específico", [
                    'user_id' => $user->id,
                    'notification_id' => $unreadNotification->id,
                    'notification_type' => $notificationType,
                    'seller_status' => $seller->status
                ]);
            }
            
            if ($shouldCreateNotification) {
                // Preparar mensajes específicos y detallados
                if ($seller->status === 'suspended') {
                    $title = 'Cuenta de vendedor suspendida';
                    $message = 'Tu cuenta de vendedor ha sido suspendida. Puedes ver tus datos históricos pero no realizar nuevas ventas. Contacta al administrador para más información.';
                } else { // inactive
                    $title = 'Cuenta de vendedor desactivada';
                    $message = 'Tu cuenta de vendedor ha sido desactivada. Contacta al administrador para reactivar tu cuenta.';
                }
                
                try {
                    $notification = \App\Models\Notification::create([
                        'user_id' => $user->id,
                        'type' => $notificationType,
                        'title' => $title,
                        'message' => $message,
                        'read' => false,
                        'data' => [
                            'seller_status' => $seller->status,
                            'store_name' => $seller->store_name
                        ]
                    ]);
                    
                    \Log::info("✅ Notificación específica creada exitosamente", [
                        'user_id' => $user->id,
                        'notification_id' => $notification->id,
                        'notification_type' => $notificationType,
                        'seller_status' => $seller->status,
                        'title' => $title
                    ]);
                } catch (\Exception $e) {
                    \Log::error("❌ Error al crear notificación específica para seller", [
                        'user_id' => $user->id,
                        'seller_status' => $seller->status,
                        'notification_type' => $notificationType,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            // Invalidate the token
            JWTAuth::invalidate(JWTAuth::getToken());

            // Logout from Laravel's built-in authentication
            Auth::logout();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
