<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Repositories\RatingRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Services\NotificationService;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminRatingController extends Controller
{
    private RatingRepositoryInterface $ratingRepository;

    private NotificationService $notificationService;

    /**
     * Constructor
     */
    public function __construct(
        RatingRepositoryInterface $ratingRepository,
        NotificationService $notificationService
    ) {
        $this->ratingRepository = $ratingRepository;
        $this->notificationService = $notificationService;
        $this->middleware('jwt.auth');
        $this->middleware('admin');
    }

    /**
     * Obtener todas las valoraciones con filtros
     */
    public function index(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $status = $request->get('status');
            $type = $request->get('type');
            $rating = $request->get('rating');
            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');

            // 🔧 NUEVO: Consulta con TODAS las relaciones necesarias
            $query = Rating::with([
                'user:id,name,avatar,email',
                'product:id,name,images,price,status',
                'seller:id,store_name,user_id,status',
                'order:id,order_number,status,total,created_at',
            ]);

            // Aplicar filtros
            if ($status && $status !== 'all') {
                $query->where('status', $status);
            }

            if ($type && $type !== 'all') {
                $query->where('type', $type);
            }

            if ($rating) {
                $query->where('rating', $rating);
            }

            if ($fromDate) {
                $query->whereDate('created_at', '>=', $fromDate);
            }

            if ($toDate) {
                $query->whereDate('created_at', '<=', $toDate);
            }

            // Obtener resultados paginados
            $ratings = $query->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();

            // Contar total con los mismos filtros
            $countQuery = Rating::query();

            if ($status && $status !== 'all') {
                $countQuery->where('status', $status);
            }
            if ($type && $type !== 'all') {
                $countQuery->where('type', $type);
            }
            if ($rating) {
                $countQuery->where('rating', $rating);
            }
            if ($fromDate) {
                $countQuery->whereDate('created_at', '>=', $fromDate);
            }
            if ($toDate) {
                $countQuery->whereDate('created_at', '<=', $toDate);
            }

            $count = $countQuery->count();
            $totalPages = ceil($count / $perPage);

            // 🔧 NUEVO: Transformar las entidades con relaciones enriquecidas
            $ratingsArray = $ratings->map(function ($rating) {
                return [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'title' => $rating->title,
                    'comment' => $rating->comment,
                    'type' => $rating->type,
                    'status' => $rating->status,
                    'user_id' => $rating->user_id,
                    'seller_id' => $rating->seller_id,
                    'product_id' => $rating->product_id,
                    'order_id' => $rating->order_id,
                    'created_at' => $rating->created_at,
                    'updated_at' => $rating->updated_at,
                    'is_verified_purchase' => $rating->is_verified_purchase ?? false,

                    // Relaciones enriquecidas
                    'user' => $rating->user ? [
                        'id' => $rating->user->id,
                        'name' => $rating->user->name,
                        'avatar' => $rating->user->avatar,
                        'email' => $rating->user->email,
                    ] : null,

                    'product' => $rating->product ? [
                        'id' => $rating->product->id,
                        'name' => $rating->product->name,
                        'image' => $rating->product->main_image,
                        'price' => $rating->product->price,
                        'status' => $rating->product->status,
                    ] : null,

                    'seller' => $rating->seller ? [
                        'id' => $rating->seller->id,
                        'store_name' => $rating->seller->store_name,
                        'user_id' => $rating->seller->user_id,
                        'status' => $rating->seller->status,
                    ] : null,

                    'order_details' => $rating->order ? [
                        'id' => $rating->order->id,
                        'order_number' => $rating->order->order_number,
                        'status' => $rating->order->status,
                        'total' => $rating->order->total,
                        'created_at' => $rating->order->created_at,
                    ] : null,
                ];
            })->toArray();

            return response()->json([
                'status' => 'success',
                'data' => $ratingsArray,
                'meta' => [
                    'total' => $count,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => $totalPages,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener valoraciones: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener valoraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprobar una valoración
     */
    public function approve(Request $request, int $id)
    {
        try {
            $note = $request->get('note');
            $rating = $this->ratingRepository->findById($id);

            if (! $rating) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Valoración no encontrada',
                ], 404);
            }

            $previousStatus = $rating->getStatus();
            $rating->setStatus('approved');
            $this->ratingRepository->update($rating);

            // 🔧 NUEVO: Notificar al vendedor cuando se aprueba una valoración
            if ($previousStatus === 'pending') {
                try {
                    // Obtener el modelo Eloquent para usar en las notificaciones
                    $ratingModel = Rating::find($id);
                    if ($ratingModel && $ratingModel->type === 'user_to_seller') {
                        Log::info('Enviando notificación al vendedor por aprobación de rating', [
                            'rating_id' => $id,
                            'previous_status' => $previousStatus,
                        ]);

                        $this->notificationService->notifyRatingReceived($ratingModel);
                    }
                } catch (\Exception $notifyError) {
                    // No fallar la aprobación si falla la notificación
                    Log::error('Error enviando notificación de aprobación: '.$notifyError->getMessage(), [
                        'rating_id' => $id,
                    ]);
                }
            }

            Log::info('Valoración aprobada por admin', [
                'rating_id' => $id,
                'admin_note' => $note,
                'previous_status' => $previousStatus,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Valoración aprobada correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al aprobar valoración: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar valoración: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rechazar una valoración
     */
    public function reject(Request $request, int $id)
    {
        try {
            $request->validate([
                'note' => 'required|string|max:500',
            ]);

            $note = $request->get('note');
            $rating = $this->ratingRepository->findById($id);

            if (! $rating) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Valoración no encontrada',
                ], 404);
            }

            $previousStatus = $rating->getStatus();
            $rating->setStatus('rejected');
            $this->ratingRepository->update($rating);

            Log::info('Valoración rechazada por admin', [
                'rating_id' => $id,
                'admin_note' => $note,
                'previous_status' => $previousStatus,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Valoración rechazada correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al rechazar valoración: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al rechazar valoración: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar o desmarcar una valoración para revisión adicional
     */
    public function flag(Request $request, int $id)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            $reason = $request->get('reason');
            $rating = $this->ratingRepository->findById($id);

            if (! $rating) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Valoración no encontrada',
                ], 404);
            }

            $previousStatus = $rating->getStatus();

            // Verificar el estado actual y cambiarlo
            if ($rating->getStatus() === 'flagged') {
                // Si ya está marcada, cambiar a aprobada
                $rating->setStatus('approved');
                $newStatus = 'approved';
                $message = 'Valoración desmarcada y aprobada correctamente';

                // 🔧 NUEVO: Notificar al vendedor si se aprueba desde flagged
                try {
                    $ratingModel = Rating::find($id);
                    if ($ratingModel && $ratingModel->type === 'user_to_seller') {
                        $this->notificationService->notifyRatingReceived($ratingModel);
                    }
                } catch (\Exception $notifyError) {
                    Log::error('Error enviando notificación al desmarcar: '.$notifyError->getMessage());
                }
            } else {
                // Si no está marcada, marcarla
                $rating->setStatus('flagged');
                $newStatus = 'flagged';
                $message = 'Valoración marcada para revisión correctamente';
            }

            $this->ratingRepository->update($rating);

            Log::info('Estado de valoración modificado', [
                'rating_id' => $id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'reason' => $reason,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'ratingId' => $id,
                    'newStatus' => $newStatus,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al modificar estado de valoración: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al modificar estado de valoración: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de valoraciones
     */
    public function getStats()
    {
        try {
            $total = $this->ratingRepository->countAll();
            $pending = $this->ratingRepository->countByStatus('pending');
            $approved = $this->ratingRepository->countByStatus('approved');
            $rejected = $this->ratingRepository->countByStatus('rejected');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'totalCount' => $total,
                    'pendingCount' => $pending,
                    'approvedCount' => $approved,
                    'rejectedCount' => $rejected,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔧 NUEVO: Mostrar detalles completos de una valoración (solo admin)
     */
    public function show(int $id)
    {
        try {
            // 🔧 NUEVO: Cargar valoración con TODAS las relaciones y datos adicionales para admins
            $rating = Rating::with([
                'user:id,name,avatar,email,created_at',
                'product:id,name,images,price,status',
                'seller:id,store_name,user_id,status',
                'order:id,order_number,status,total,created_at',
            ])->find($id);

            if (! $rating) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Valoración no encontrada',
                ], 404);
            }

            // 🔧 NUEVO: Los admins pueden ver detalles completos incluido metadata
            $ratingData = [
                'id' => $rating->id,
                'rating' => $rating->rating,
                'title' => $rating->title,
                'comment' => $rating->comment,
                'type' => $rating->type,
                'status' => $rating->status,
                'user_id' => $rating->user_id,
                'seller_id' => $rating->seller_id,
                'product_id' => $rating->product_id,
                'order_id' => $rating->order_id,
                'created_at' => $rating->created_at,
                'updated_at' => $rating->updated_at,
                'is_verified_purchase' => $rating->is_verified_purchase ?? false,

                // Información detallada del usuario (solo para admins)
                'user_details' => $rating->user ? [
                    'id' => $rating->user->id,
                    'name' => $rating->user->name,
                    'email' => $rating->user->email,
                    'avatar' => $rating->user->avatar,
                    'created_at' => $rating->user->created_at,
                ] : null,

                // Información detallada del vendedor
                'seller_details' => $rating->seller ? [
                    'id' => $rating->seller->id,
                    'store_name' => $rating->seller->store_name,
                    'user_id' => $rating->seller->user_id,
                    'status' => $rating->seller->status,
                ] : null,

                // Información detallada del producto
                'product_details' => $rating->product ? [
                    'id' => $rating->product->id,
                    'name' => $rating->product->name,
                    'price' => $rating->product->price,
                    'image' => $rating->product->main_image,
                    'status' => $rating->product->status ?? 'active',
                ] : null,

                // Información detallada de la orden
                'order_details' => $rating->order ? [
                    'id' => $rating->order->id,
                    'order_number' => $rating->order->order_number,
                    'status' => $rating->order->status,
                    'total' => $rating->order->total,
                    'created_at' => $rating->order->created_at,
                ] : null,

                // Historial de cambios de estado (si existe tabla de logs)
                'status_history' => $this->getRatingStatusHistory($id),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $ratingData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalles de valoración: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener detalles de la valoración: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔧 NUEVO: Obtener historial de cambios de estado de una valoración
     */
    private function getRatingStatusHistory(int $ratingId): array
    {
        try {
            // Si tienes una tabla de logs, puedes consultar aquí
            // Por ahora devolvemos array vacío, pero puedes implementar esto después
            return [];
        } catch (\Exception $e) {
            Log::error('Error obteniendo historial de rating: '.$e->getMessage());

            return [];
        }
    }
}
