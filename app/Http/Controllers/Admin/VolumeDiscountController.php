<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VolumeDiscount;
use App\Services\ConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VolumeDiscountController extends Controller
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Obtener configuración general de descuentos por volumen
     */
    public function getConfiguration(): JsonResponse
    {
        try {
            $config = [
                'enabled' => $this->configService->getConfig('volume_discounts.enabled', true),
                'stackable' => $this->configService->getConfig('volume_discounts.stackable', false),
                'show_savings_message' => $this->configService->getConfig('volume_discounts.show_savings_message', true),
                'default_tiers' => $this->configService->getConfig('volume_discounts.default_tiers', [
                    ['quantity' => 3, 'discount' => 5, 'label' => 'Descuento 3+'],
                    ['quantity' => 6, 'discount' => 10, 'label' => 'Descuento 6+'],
                    ['quantity' => 12, 'discount' => 15, 'label' => 'Descuento 12+'],
                ]),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo configuración de descuentos por volumen:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Actualizar configuración general de descuentos por volumen
     */
    public function updateConfiguration(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'enabled' => 'required|boolean',
                'stackable' => 'required|boolean',
                'show_savings_message' => 'required|boolean',
                'default_tiers' => 'required|array',
                'default_tiers.*.quantity' => 'required|integer|min:1',
                'default_tiers.*.discount' => 'required|numeric|min:0|max:100',
                'default_tiers.*.label' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de configuración inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $this->configService->setConfig('volume_discounts.enabled', $request->input('enabled'));
            $this->configService->setConfig('volume_discounts.stackable', $request->input('stackable'));
            $this->configService->setConfig('volume_discounts.show_savings_message', $request->input('show_savings_message'));
            $this->configService->setConfig('volume_discounts.default_tiers', $request->input('default_tiers'));
            
            // ✅ NUEVO: Actualizar versión para invalidar cache del frontend
            $this->configService->setConfig('volume_discounts.version', time());

            return response()->json([
                'status' => 'success',
                'message' => 'Configuración actualizada exitosamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando configuración de descuentos por volumen:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar configuración',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Obtener descuentos por volumen de un producto específico
     */
    public function getProductDiscounts(int $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            $discounts = VolumeDiscount::where('product_id', $productId)
                ->active()
                ->orderBy('min_quantity', 'asc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'price' => $product->price,
                    ],
                    'discounts' => $discounts->map(function ($discount) {
                        return [
                            'id' => $discount->id,
                            'min_quantity' => $discount->min_quantity,
                            'discount_percentage' => $discount->discount_percentage,
                            'label' => $discount->label,
                            'active' => $discount->active,
                        ];
                    })->toArray(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo descuentos de producto:', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener descuentos del producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Actualizar descuentos por volumen de un producto
     */
    public function updateProductDiscounts(Request $request, int $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'discounts' => 'required|array',
                'discounts.*.min_quantity' => 'required|integer|min:1',
                'discounts.*.discount_percentage' => 'required|numeric|min:0|max:100',
                'discounts.*.label' => 'nullable|string|max:255',
                'discounts.*.active' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de descuentos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::transaction(function () use ($productId, $request) {
                // Eliminar descuentos existentes
                VolumeDiscount::where('product_id', $productId)->delete();

                // Crear nuevos descuentos
                $discounts = $request->input('discounts');

                foreach ($discounts as $discountData) {
                    VolumeDiscount::create([
                        'product_id' => $productId,
                        'min_quantity' => $discountData['min_quantity'],
                        'discount_percentage' => $discountData['discount_percentage'],
                        'label' => $discountData['label'] ?? "Descuento {$discountData['min_quantity']}+",
                        'active' => $discountData['active'] ?? true,
                    ]);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Descuentos por volumen actualizados exitosamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando descuentos de producto:', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar descuentos del producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Aplicar descuentos por defecto a un producto
     */
    public function applyDefaultDiscounts(int $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            $defaultTiers = $this->configService->getConfig('volume_discounts.default_tiers', []);

            if (empty($defaultTiers)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No hay niveles de descuento por defecto configurados',
                ], 400);
            }

            DB::transaction(function () use ($productId, $defaultTiers) {
                // Eliminar descuentos existentes
                VolumeDiscount::where('product_id', $productId)->delete();

                // Crear descuentos por defecto
                foreach ($defaultTiers as $tier) {
                    VolumeDiscount::create([
                        'product_id' => $productId,
                        'min_quantity' => $tier['quantity'],
                        'discount_percentage' => $tier['discount'],
                        'label' => $tier['label'],
                        'active' => true,
                    ]);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Descuentos por defecto aplicados exitosamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error aplicando descuentos por defecto:', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aplicar descuentos por defecto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Eliminar todos los descuentos por volumen de un producto
     */
    public function removeProductDiscounts(int $productId): JsonResponse
    {
        try {
            $product = Product::find($productId);

            if (! $product) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Producto no encontrado',
                ], 404);
            }

            $deletedCount = VolumeDiscount::where('product_id', $productId)->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Se eliminaron {$deletedCount} descuentos por volumen del producto",
            ]);
        } catch (\Exception $e) {
            Log::error('Error eliminando descuentos de producto:', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar descuentos del producto',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de descuentos por volumen
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = [
                'total_products_with_discounts' => VolumeDiscount::distinct('product_id')->count(),
                'total_discount_tiers' => VolumeDiscount::active()->count(),
                'average_discount_percentage' => VolumeDiscount::active()->avg('discount_percentage'),
                'most_common_quantity' => VolumeDiscount::active()
                    ->select('min_quantity')
                    ->groupBy('min_quantity')
                    ->orderByRaw('COUNT(*) DESC')
                    ->first()?->min_quantity,
                'enabled_globally' => $this->configService->getConfig('volume_discounts.enabled', true),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de descuentos por volumen:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    /**
     * Aplicar descuentos por defecto a múltiples productos en lote
     */
    public function bulkApplyDefaults(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_ids' => 'required|array',
                'product_ids.*' => 'integer|exists:products,id',
                'overwrite_existing' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $productIds = $request->input('product_ids');
            $overwriteExisting = $request->input('overwrite_existing', false);
            $defaultTiers = $this->configService->getConfig('volume_discounts.default_tiers', []);

            if (empty($defaultTiers)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No hay niveles de descuento por defecto configurados',
                ], 400);
            }

            $processedCount = 0;
            $skippedCount = 0;

            DB::transaction(function () use ($productIds, $overwriteExisting, $defaultTiers, &$processedCount, &$skippedCount) {
                foreach ($productIds as $productId) {
                    // Verificar si ya tiene descuentos
                    $hasExistingDiscounts = VolumeDiscount::where('product_id', $productId)->exists();

                    if ($hasExistingDiscounts && ! $overwriteExisting) {
                        $skippedCount++;

                        continue;
                    }

                    // Eliminar descuentos existentes si se solicita sobreescribir
                    if ($hasExistingDiscounts && $overwriteExisting) {
                        VolumeDiscount::where('product_id', $productId)->delete();
                    }

                    // Crear descuentos por defecto
                    foreach ($defaultTiers as $tier) {
                        VolumeDiscount::create([
                            'product_id' => $productId,
                            'min_quantity' => $tier['quantity'],
                            'discount_percentage' => $tier['discount'],
                            'label' => $tier['label'],
                            'active' => true,
                        ]);
                    }

                    $processedCount++;
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => "Proceso completado: {$processedCount} productos actualizados, {$skippedCount} omitidos",
                'data' => [
                    'processed' => $processedCount,
                    'skipped' => $skippedCount,
                    'total' => count($productIds),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error en aplicación masiva de descuentos:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al aplicar descuentos en lote',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }
}
