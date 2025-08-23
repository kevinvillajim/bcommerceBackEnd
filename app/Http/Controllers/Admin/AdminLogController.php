<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Infrastructure\Repositories\EloquentAdminLogRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminLogController extends Controller
{
    private EloquentAdminLogRepository $adminLogRepository;

    public function __construct(EloquentAdminLogRepository $adminLogRepository)
    {
        $this->adminLogRepository = $adminLogRepository;
    }

    /**
     * Obtener logs paginados con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:5|max:100',
            'level' => Rule::in(['error', 'critical', 'warning', 'info']),
            'event_type' => 'string|max:50',
            'user_id' => 'integer|exists:users,id',
            'status_code' => 'integer|between:100,599',
            'from_date' => 'date',
            'to_date' => 'date|after_or_equal:from_date',
            'search' => 'string|max:255',
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 20;
        $offset = ($page - 1) * $perPage;

        $filters = array_filter([
            'level' => $validated['level'] ?? null,
            'event_type' => $validated['event_type'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'status_code' => $validated['status_code'] ?? null,
            'from_date' => $validated['from_date'] ?? null,
            'to_date' => $validated['to_date'] ?? null,
            'search' => $validated['search'] ?? null,
        ]);

        $logs = $this->adminLogRepository->findAll($filters, $perPage, $offset);
        $total = $this->adminLogRepository->count($filters);

        $logsArray = array_map(fn ($log) => $log->toArray(), $logs);

        return response()->json([
            'data' => $logsArray,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ]);
    }

    /**
     * Obtener log específico por ID
     */
    public function show(int $id): JsonResponse
    {
        $log = $this->adminLogRepository->findById($id);

        if (! $log) {
            return response()->json(['message' => 'Log not found'], 404);
        }

        return response()->json([
            'data' => $log->toArray(),
        ]);
    }

    /**
     * Obtener estadísticas de logs
     */
    public function stats(): JsonResponse
    {
        $stats = $this->adminLogRepository->getStats();

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Obtener logs recientes
     */
    public function recent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'integer|min:1|max:100',
        ]);

        $limit = $validated['limit'] ?? 50;
        $logs = $this->adminLogRepository->getRecent($limit);

        $logsArray = array_map(fn ($log) => $log->toArray(), $logs);

        return response()->json([
            'data' => $logsArray,
        ]);
    }

    /**
     * Obtener logs críticos recientes
     */
    public function critical(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hours' => 'integer|min:1|max:168', // max 1 semana
        ]);

        $hours = $validated['hours'] ?? 24;
        $logs = $this->adminLogRepository->getCritical($hours);

        $logsArray = array_map(fn ($log) => $log->toArray(), $logs);

        return response()->json([
            'data' => $logsArray,
            'meta' => [
                'hours' => $hours,
                'count' => count($logsArray),
            ],
        ]);
    }

    /**
     * Obtener logs por tipo de evento
     */
    public function byEventType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'required|string|max:50',
            'limit' => 'integer|min:1|max:100',
        ]);

        $eventType = $validated['event_type'];
        $limit = $validated['limit'] ?? 10;

        $logs = $this->adminLogRepository->findByEventType($eventType, $limit);

        $logsArray = array_map(fn ($log) => $log->toArray(), $logs);

        return response()->json([
            'data' => $logsArray,
            'meta' => [
                'event_type' => $eventType,
                'count' => count($logsArray),
            ],
        ]);
    }

    /**
     * Eliminar log específico
     */
    public function destroy(int $id): JsonResponse
    {
        $log = $this->adminLogRepository->findById($id);

        if (! $log) {
            return response()->json(['message' => 'Log not found'], 404);
        }

        $deleted = $this->adminLogRepository->delete($id);

        if ($deleted) {
            return response()->json(['message' => 'Log deleted successfully']);
        }

        return response()->json(['message' => 'Failed to delete log'], 500);
    }

    /**
     * Ejecutar limpieza manual de logs
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'integer|min:0|max:365', // Allow 0 to delete all logs
            'batch_size' => 'integer|min:10|max:1000',
        ]);

        $days = $validated['days'] ?? 30;
        $batchSize = $validated['batch_size'] ?? 100;

        try {
            $deletedCount = $this->adminLogRepository->cleanupOldLogs($days, $batchSize);

            return response()->json([
                'message' => 'Cleanup completed successfully',
                'data' => [
                    'deleted_count' => $deletedCount,
                    'days' => $days,
                    'batch_size' => $batchSize,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Cleanup failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener tipos de eventos únicos
     */
    public function eventTypes(): JsonResponse
    {
        $eventTypes = \DB::table('admin_logs')
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->toArray();

        return response()->json([
            'data' => $eventTypes,
        ]);
    }

    /**
     * Obtener usuarios únicos que han generado logs
     */
    public function users(): JsonResponse
    {
        $users = \DB::table('admin_logs')
            ->join('users', 'admin_logs.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email')
            ->distinct()
            ->orderBy('users.name')
            ->get()
            ->toArray();

        return response()->json([
            'data' => $users,
        ]);
    }
}
