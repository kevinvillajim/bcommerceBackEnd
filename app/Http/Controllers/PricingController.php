<?php

namespace App\Http\Controllers;

use App\Domain\Services\PricingCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * 🧮 CONTROLADOR DE CÁLCULOS DE PRICING
 *
 * Proporciona endpoints para calcular totales de manera centralizada
 * desde el frontend y otros sistemas
 */
class PricingController extends Controller
{
    private PricingCalculatorService $pricingService;

    public function __construct(PricingCalculatorService $pricingService)
    {
        $this->pricingService = $pricingService;
        $this->middleware('jwt.auth');
    }

    /**
     * 🎯 Calcular totales del carrito de manera centralizada
     *
     * POST /api/calculate-totals
     */
    public function calculateTotals(Request $request): JsonResponse
    {
        try {
            // Validar entrada
            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|min:1',
                'items.*.quantity' => 'required|integer|min:1',
                'coupon_code' => 'sometimes|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            /** @var \App\Models\User $user */
            $user = $request->user();
            $userId = $user->id;

            Log::info('🧮 PricingController - Calculando totales centralizados', [
                'user_id' => $userId,
                'items_count' => count($data['items']),
                'coupon_code' => $data['coupon_code'] ?? null,
            ]);

            // Calcular totales usando el servicio centralizado
            $result = $this->pricingService->calculateCartTotals(
                $data['items'],
                $userId,
                $data['coupon_code'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => [
                    // Totales principales
                    'subtotal_original' => $result['subtotal_original'],
                    'subtotal_with_discounts' => $result['subtotal_with_discounts'],
                    'subtotal_after_coupon' => $result['subtotal_after_coupon'],
                    'final_total' => $result['final_total'],

                    // Descuentos desglosados
                    'seller_discounts' => $result['seller_discounts'],
                    'volume_discounts' => $result['volume_discounts'],
                    'coupon_discount' => $result['coupon_discount'],
                    'total_discounts' => $result['total_discounts'],

                    // Envío e IVA
                    'shipping_cost' => $result['shipping_cost'],
                    'free_shipping' => $result['free_shipping'],
                    'free_shipping_threshold' => $result['free_shipping_threshold'],
                    'iva_amount' => $result['iva_amount'],
                    'tax_rate' => $result['tax_rate'],

                    // Información adicional
                    'volume_discounts_applied' => $result['volume_discounts_applied'],
                    'coupon_info' => $result['coupon_info'],

                    // Items procesados (para debug)
                    'processed_items' => $result['processed_items'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error calculando totales centralizados', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error calculando totales: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 🔍 Validar totales enviados desde el frontend
     *
     * POST /api/validate-totals
     */
    public function validateTotals(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|min:1',
                'items.*.quantity' => 'required|integer|min:1',
                'frontend_totals.final_total' => 'required|numeric|min:0',
                'frontend_totals.subtotal_original' => 'required|numeric|min:0',
                'frontend_totals.total_discounts' => 'required|numeric|min:0',
                'frontend_totals.iva_amount' => 'required|numeric|min:0',
                'frontend_totals.shipping_cost' => 'required|numeric|min:0',
                'coupon_code' => 'sometimes|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de entrada inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            /** @var \App\Models\User $user */
            $user = $request->user();
            $userId = $user->id;

            // Calcular totales reales en backend
            $backendResult = $this->pricingService->calculateCartTotals(
                $data['items'],
                $userId,
                $data['coupon_code'] ?? null
            );

            $frontendTotals = $data['frontend_totals'];

            // Comparar totales con tolerancia de $0.01 para redondeo
            $tolerance = 0.01;
            $discrepancies = [];

            $fieldsToCompare = [
                'final_total' => 'final_total',
                'subtotal_original' => 'subtotal_original',
                'total_discounts' => 'total_discounts',
                'iva_amount' => 'iva_amount',
                'shipping_cost' => 'shipping_cost',
            ];

            foreach ($fieldsToCompare as $frontendField => $backendField) {
                $frontendValue = (float) ($frontendTotals[$frontendField] ?? 0);
                $backendValue = (float) ($backendResult[$backendField] ?? 0);
                $difference = abs($frontendValue - $backendValue);

                if ($difference > $tolerance) {
                    $discrepancies[] = [
                        'field' => $frontendField,
                        'frontend_value' => $frontendValue,
                        'backend_value' => $backendValue,
                        'difference' => round($difference, 2),
                    ];
                }
            }

            $isValid = empty($discrepancies);

            Log::info('🔍 Validación de totales frontend vs backend', [
                'user_id' => $userId,
                'is_valid' => $isValid,
                'discrepancies_count' => count($discrepancies),
                'discrepancies' => $discrepancies,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'discrepancies' => $discrepancies,
                    'backend_totals' => [
                        'final_total' => $backendResult['final_total'],
                        'subtotal_original' => $backendResult['subtotal_original'],
                        'total_discounts' => $backendResult['total_discounts'],
                        'iva_amount' => $backendResult['iva_amount'],
                        'shipping_cost' => $backendResult['shipping_cost'],
                    ],
                    'frontend_totals' => $frontendTotals,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error validando totales', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error validando totales: '.$e->getMessage(),
            ], 500);
        }
    }
}
