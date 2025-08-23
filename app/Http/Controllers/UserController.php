<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\UseCases\User\CheckUserRoleUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    /**
     * Verificar el rol y privilegios del usuario actual
     */
    public function checkRole(CheckUserRoleUseCase $checkUserRoleUseCase): JsonResponse
    {
        $result = $checkUserRoleUseCase->execute();

        return response()->json($result, $result['status'] ?? 200);
    }

    /**
     * ✅ NUEVO: Obtener estado online de un usuario
     */
    public function getStatus(int $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Por ahora, devolver un estado básico
            // En el futuro podrías implementar lógica más sofisticada
            $lastActivity = $user->updated_at ?? $user->created_at;
            $isOnline = $lastActivity && $lastActivity->diffInMinutes() < 15; // Online si actividad en últimos 15 min

            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_online' => $isOnline,
                    'last_seen' => $lastActivity ? $lastActivity->toISOString() : null,
                    'is_typing' => false, // Por defecto false, se puede implementar lógica más compleja
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estado de usuario: '.$e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estado del usuario',
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Actualizar actividad del usuario
     */
    public function updateActivity(Request $request, int $id): JsonResponse
    {
        try {
            $currentUserId = Auth::id();

            // Solo permitir que el usuario actualice su propia actividad
            if ($currentUserId !== $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No tienes permiso para actualizar la actividad de este usuario',
                ], 403);
            }

            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Actualizar timestamp de actividad
            $user->touch(); // Esto actualiza updated_at

            // Si se proporcionan datos adicionales, procesarlos
            if ($request->has('last_seen')) {
                // Podrías guardar esto en un campo específico si lo agregas a la tabla
                Log::info('Actividad de usuario actualizada', [
                    'user_id' => $id,
                    'last_seen' => $request->input('last_seen'),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Actividad actualizada correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar actividad de usuario: '.$e->getMessage(), [
                'user_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar actividad del usuario',
            ], 500);
        }
    }
}
