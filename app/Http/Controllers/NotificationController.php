<?php

// app/Http/Controllers/NotificationController.php - Método mejorado

namespace App\Http\Controllers;

use App\UseCases\Notification\DeleteNotificationUseCase;
use App\UseCases\Notification\GetUserNotificationsUseCase;
use App\UseCases\Notification\MarkAllNotificationsAsReadUseCase;
use App\UseCases\Notification\MarkNotificationAsReadUseCase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    private GetUserNotificationsUseCase $getUserNotificationsUseCase;

    private MarkNotificationAsReadUseCase $markNotificationAsReadUseCase;

    private MarkAllNotificationsAsReadUseCase $markAllNotificationsAsReadUseCase;

    private DeleteNotificationUseCase $deleteNotificationUseCase;

    public function __construct(
        GetUserNotificationsUseCase $getUserNotificationsUseCase,
        MarkNotificationAsReadUseCase $markNotificationAsReadUseCase,
        MarkAllNotificationsAsReadUseCase $markAllNotificationsAsReadUseCase,
        DeleteNotificationUseCase $deleteNotificationUseCase
    ) {
        $this->getUserNotificationsUseCase = $getUserNotificationsUseCase;
        $this->markNotificationAsReadUseCase = $markNotificationAsReadUseCase;
        $this->markAllNotificationsAsReadUseCase = $markAllNotificationsAsReadUseCase;
        $this->deleteNotificationUseCase = $deleteNotificationUseCase;

        $this->middleware('jwt.auth');
    }

    /**
     * Obtener todas las notificaciones del usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        // ARREGLO: Calcular offset a partir de page
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        $onlyUnread = $request->boolean('unread', false);

        $result = $this->getUserNotificationsUseCase->execute(
            Auth::id(),
            $limit,
            $offset,
            $onlyUnread
        );

        // NUEVO: Formatear fechas para incluir zona horaria
        if ($result['status'] === 'success' && isset($result['data']['notifications'])) {
            $result['data']['notifications'] = array_map(function ($notification) {
                return $this->formatNotificationDates($notification);
            }, $result['data']['notifications']);
        }

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Obtener notificaciones no leídas del usuario autenticado
     */
    public function unread(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        // ARREGLO: Calcular offset a partir de page
        $page = $request->input('page', 1);
        $offset = ($page - 1) * $limit;

        $result = $this->getUserNotificationsUseCase->execute(
            Auth::id(),
            $limit,
            $offset,
            true
        );

        // NUEVO: Formatear fechas para incluir zona horaria
        if ($result['status'] === 'success' && isset($result['data']['notifications'])) {
            $result['data']['notifications'] = array_map(function ($notification) {
                return $this->formatNotificationDates($notification);
            }, $result['data']['notifications']);
        }

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Obtener el número de notificaciones no leídas
     */
    public function count(): JsonResponse
    {
        $result = $this->getUserNotificationsUseCase->execute(
            Auth::id(),
            1,
            0,
            true
        );

        if ($result['status'] === 'success') {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'unread_count' => $result['data']['unread_count'],
                ],
            ]);
        }

        return response()->json($result, 400);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead(int $id): JsonResponse
    {
        $result = $this->markNotificationAsReadUseCase->execute(
            $id,
            Auth::id()
        );

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead(): JsonResponse
    {
        $result = $this->markAllNotificationsAsReadUseCase->execute(
            Auth::id()
        );

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->deleteNotificationUseCase->execute(
            $id,
            Auth::id()
        );

        return response()->json($result, $result['status'] === 'success' ? 200 : 400);
    }

    /**
     * NUEVO: Formatear fechas de notificación para incluir zona horaria
     */
    private function formatNotificationDates(array $notification): array
    {
        // Formatear created_at
        if (isset($notification['created_at'])) {
            $notification['created_at'] = Carbon::parse($notification['created_at'])
                ->setTimezone('America/Guayaquil')
                ->toISOString();
        }

        // Formatear read_at si existe
        if (isset($notification['read_at']) && $notification['read_at']) {
            $notification['read_at'] = Carbon::parse($notification['read_at'])
                ->setTimezone('America/Guayaquil')
                ->toISOString();
        }

        return $notification;
    }
}
