<?php

namespace App\Http\Controllers;

use App\Models\ExternalPaymentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controlador para usuarios con rol 'payment'
 * Solo pueden crear y gestionar SUS propios links de pago
 */
class ExternalPaymentController extends Controller
{
    /**
     * Dashboard con estadísticas del usuario payment
     */
    public function dashboard(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Estadísticas solo de los links creados por este usuario
            $stats = [
                'total_links' => ExternalPaymentLink::byUser($user->id)->count(),
                'pending_links' => ExternalPaymentLink::byUser($user->id)->where('status', 'pending')->count(),
                'paid_links' => ExternalPaymentLink::byUser($user->id)->where('status', 'paid')->count(),
                'expired_links' => ExternalPaymentLink::byUser($user->id)->where('status', 'expired')->count(),
                'total_amount_collected' => ExternalPaymentLink::byUser($user->id)
                    ->where('status', 'paid')
                    ->sum('amount'),
                'active_links' => ExternalPaymentLink::byUser($user->id)->active()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Error loading payment dashboard', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading dashboard',
            ], 500);
        }
    }

    /**
     * Listar links del usuario actual
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->get('per_page', 15);

            $links = ExternalPaymentLink::byUser($user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $links,
            ]);

        } catch (\Exception $e) {
            Log::error('Error listing payment links', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error loading links',
            ], 500);
        }
    }

    /**
     * Crear nuevo link de pago
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_name' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01|max:99999.99',
                'description' => 'nullable|string|max:1000',
                'expires_in_days' => 'nullable|integer|min:1|max:30',
            ]);

            $user = Auth::user();

            // Generar código único
            $linkCode = ExternalPaymentLink::generateUniqueCode();

            // Calcular fecha de expiración (default 7 días)
            $expiresInDays = (int)($validated['expires_in_days'] ?? 7);
            $expiresAt = now()->addDays($expiresInDays);

            $link = ExternalPaymentLink::create([
                'link_code' => $linkCode,
                'customer_name' => $validated['customer_name'],
                'amount' => $validated['amount'],
                'description' => $validated['description'],
                'expires_at' => $expiresAt,
                'created_by' => $user->id,
            ]);

            Log::info('Payment link created', [
                'link_id' => $link->id,
                'link_code' => $linkCode,
                'amount' => $validated['amount'],
                'created_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Link de pago creado exitosamente',
                'data' => [
                    'id' => $link->id,
                    'link_code' => $linkCode,
                    'public_url' => $link->getPublicUrl(),
                    'customer_name' => $link->customer_name,
                    'amount' => $link->amount,
                    'expires_at' => $link->expires_at->toISOString(),
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error creating payment link', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error creando el link de pago',
            ], 500);
        }
    }

    /**
     * Ver detalles de un link específico (solo del usuario actual)
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $link = ExternalPaymentLink::byUser($user->id)->findOrFail($id);

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
                    'expires_at' => $link->expires_at->toISOString(),
                    'paid_at' => $link->paid_at?->toISOString(),
                    'created_at' => $link->created_at->toISOString(),
                    'is_expired' => $link->isExpired(),
                    'is_available' => $link->isAvailableForPayment(),
                ],
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Link no encontrado',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error showing payment link', [
                'user_id' => Auth::id(),
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
     * Cancelar un link (solo del usuario actual)
     */
    public function cancel(int $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $link = ExternalPaymentLink::byUser($user->id)->findOrFail($id);

            if ($link->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden cancelar links pendientes',
                ], 400);
            }

            $link->update(['status' => 'cancelled']);

            Log::info('Payment link cancelled', [
                'link_id' => $link->id,
                'link_code' => $link->link_code,
                'cancelled_by' => $user->id,
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
            Log::error('Error cancelling payment link', [
                'user_id' => Auth::id(),
                'link_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error cancelando el link',
            ], 500);
        }
    }
}
