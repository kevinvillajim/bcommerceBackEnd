<?php

namespace App\Http\Controllers;

use App\Models\ExternalPaymentLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para administradores
 * Pueden ver TODOS los links de pago de TODOS los usuarios
 */
class ExternalPaymentAdminController extends Controller
{
    /**
     * Dashboard global de pagos externos para administradores
     */
    public function dashboard(): JsonResponse
    {
        try {
            // Estadísticas globales de todos los links
            $stats = [
                'total_links' => ExternalPaymentLink::count(),
                'pending_links' => ExternalPaymentLink::where('status', 'pending')->count(),
                'paid_links' => ExternalPaymentLink::where('status', 'paid')->count(),
                'expired_links' => ExternalPaymentLink::where('status', 'expired')->count(),
                'cancelled_links' => ExternalPaymentLink::where('status', 'cancelled')->count(),
                'total_amount_collected' => ExternalPaymentLink::where('status', 'paid')->sum('amount'),
                'active_links' => ExternalPaymentLink::active()->count(),
                'total_payment_users' => User::whereHas('admin', function ($query) {
                    $query->where('role', 'payment');
                })->count(),
            ];

            // Top 5 usuarios payment más activos
            $topUsers = User::select('users.id', 'users.name', 'users.email')
                ->join('external_payment_links', 'users.id', '=', 'external_payment_links.created_by')
                ->selectRaw('COUNT(*) as total_links, SUM(CASE WHEN external_payment_links.status = "paid" THEN external_payment_links.amount ELSE 0 END) as total_collected')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc('total_links')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'top_users' => $topUsers,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading admin payment dashboard', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading dashboard',
            ], 500);
        }
    }

    /**
     * Listar TODOS los links de pago (de todos los usuarios)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $userId = $request->get('user_id');
            $search = $request->get('search');

            $query = ExternalPaymentLink::with('creator:id,name,email');

            // Filtrar por estado
            if ($status) {
                $query->where('status', $status);
            }

            // Filtrar por usuario
            if ($userId) {
                $query->where('created_by', $userId);
            }

            // Búsqueda por nombre de cliente o código de link
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                      ->orWhere('link_code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $links = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $links,
            ]);

        } catch (\Exception $e) {
            Log::error('Error listing all payment links', [
                'error' => $e->getMessage(),
                'filters' => $request->only(['status', 'user_id', 'search']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading links',
            ], 500);
        }
    }

    /**
     * Ver detalles de cualquier link (incluso de otros usuarios)
     */
    public function show(int $id): JsonResponse
    {
        try {
            $link = ExternalPaymentLink::with('creator:id,name,email')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $link->id,
                    'link_code' => $link->link_code,
                    'public_url' => $link->getPublicUrl(),
                    'customer_name' => $link->customer_name,
                    'amount' => $link->amount,
                    'description' => $link->description,
                    'status' => $link->status,
                    'payment_method' => $link->payment_method,
                    'transaction_id' => $link->transaction_id,
                    'payment_id' => $link->payment_id,
                    'expires_at' => $link->expires_at->toISOString(),
                    'paid_at' => $link->paid_at?->toISOString(),
                    'created_at' => $link->created_at->toISOString(),
                    'updated_at' => $link->updated_at->toISOString(),
                    'is_expired' => $link->isExpired(),
                    'is_available' => $link->isAvailableForPayment(),
                    'creator' => $link->creator,
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Link no encontrado',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error showing payment link', [
                'link_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error cargando el link',
            ], 500);
        }
    }

    /**
     * Cancelar cualquier link (poder administrativo)
     */
    public function cancel(int $id): JsonResponse
    {
        try {
            $link = ExternalPaymentLink::findOrFail($id);

            if ($link->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden cancelar links pendientes',
                ], 400);
            }

            $link->update(['status' => 'cancelled']);

            Log::info('Payment link cancelled by admin', [
                'link_id' => $link->id,
                'link_code' => $link->link_code,
                'original_creator' => $link->created_by,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Link cancelado exitosamente',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Link no encontrado',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error cancelling payment link (admin)', [
                'link_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error cancelando el link',
            ], 500);
        }
    }

    /**
     * Obtener lista de usuarios con rol payment
     */
    public function getPaymentUsers(): JsonResponse
    {
        try {
            $users = User::select('id', 'name', 'email')
                ->whereHas('admin', function ($query) {
                    $query->where('role', 'payment');
                })
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting payment users', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading payment users',
            ], 500);
        }
    }

    /**
     * Estadísticas por fechas
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date', now()->subDays(30)->startOfDay());
            $endDate = $request->get('end_date', now()->endOfDay());

            $stats = [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'links_created' => ExternalPaymentLink::whereBetween('created_at', [$startDate, $endDate])->count(),
                'links_paid' => ExternalPaymentLink::whereBetween('paid_at', [$startDate, $endDate])->count(),
                'amount_collected' => ExternalPaymentLink::whereBetween('paid_at', [$startDate, $endDate])->sum('amount'),
                'conversion_rate' => 0,
            ];

            // Calcular tasa de conversión
            if ($stats['links_created'] > 0) {
                $stats['conversion_rate'] = round(($stats['links_paid'] / $stats['links_created']) * 100, 2);
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating payment stats', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generating statistics',
            ], 500);
        }
    }
}
