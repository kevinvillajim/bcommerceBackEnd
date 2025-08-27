<?php

namespace App\Http\Controllers;

use App\Http\Controllers\DatafastController;
use App\Http\Requests\CheckoutRequest;
use App\UseCases\Checkout\ProcessCheckoutUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    private ProcessCheckoutUseCase $processCheckoutUseCase;

    public function __construct(ProcessCheckoutUseCase $processCheckoutUseCase)
    {
        $this->processCheckoutUseCase = $processCheckoutUseCase;
        $this->middleware('jwt.auth');
    }

    /**
     * ✅ CORREGIDO: Procesar el pago con items validados
     */
    public function process(CheckoutRequest $request)
    {
        try {
            // Obtener datos validados
            $validated = $request->validated();

            // ✅ CORREGIDO: Obtener ID del usuario autenticado con type hint
            /** @var \App\Models\User $user */
            $user = $request->user();
            $userId = $user->id;

            // ✅ NUEVO: Obtener items validados del request
            $items = $request->getValidatedItems();

            // ✅ NUEVO: Obtener totales calculados del frontend
            $calculatedTotals = $request->getCalculatedTotals();

            // ✅ ACTUALIZADO: Log para debug incluyendo información de items y totales
            Log::info('🛒 Iniciando checkout con items validados y totales del frontend', [
                'user_id' => $userId,
                'payment_method' => $validated['payment']['method'],
                'items_count' => count($items),
                'items' => $items,
                'calculated_totals' => $calculatedTotals,
            ]);

            // 🚨 CORRECCIÓN CRÍTICA: Para DATAFAST, delegar al DatafastController
            // para evitar conflictos de transacción SERIALIZABLE
            if ($validated['payment']['method'] === 'datafast') {
                Log::info('🔄 Delegando checkout DATAFAST al DatafastController especializado', [
                    'calculated_totals' => $calculatedTotals,
                    'items' => $items
                ]);
                
                // ✅ TRANSFORMAR datos del cálculo centralizado al formato que espera DatafastController
                $transformedData = array_merge($request->all(), [
                    'total' => $calculatedTotals['total'],
                    'subtotal' => $calculatedTotals['subtotal'], 
                    'shipping_cost' => $calculatedTotals['shipping'],
                    'tax' => $calculatedTotals['tax'],
                    'items' => $items, // Usar items validados
                ]);
                
                // Crear nuevo request con datos transformados
                $transformedRequest = new Request($transformedData);
                $transformedRequest->setUserResolver($request->getUserResolver());
                
                // Crear instancia del DatafastController y delegar
                $datafastController = app(DatafastController::class);
                return $datafastController->createCheckout($transformedRequest);
            }

            // Ejecutar el caso de uso con items validados y totales calculados
            $result = $this->processCheckoutUseCase->execute(
                $userId,
                $validated['payment'],
                $validated['shipping'],
                $items, // ✅ NUEVO: Pasar items validados
                $validated['seller_id'] ?? null,
                $validated['discount_code'] ?? null,
                $calculatedTotals // ✅ NUEVO: Pasar totales calculados
            );

            // Si hay múltiples órdenes de vendedor, incluir información básica
            $sellerOrdersInfo = [];
            if (isset($result['seller_orders']) && ! empty($result['seller_orders'])) {
                foreach ($result['seller_orders'] as $sellerOrder) {
                    $sellerOrdersInfo[] = [
                        'id' => $sellerOrder->getId(),
                        'seller_id' => $sellerOrder->getSellerId(),
                        'total' => $sellerOrder->getTotal(),
                        'status' => $sellerOrder->getStatus(),
                        'order_number' => $sellerOrder->getOrderNumber(),
                    ];
                }
            }

            // ✅ CORREGIDO: Respuesta con manejo seguro de campos
            $responseData = [
                'order_id' => $result['order']->getId(),
                'order_number' => $result['order']->getOrderNumber(),
                'total' => $result['order']->getTotal(),
                'payment_status' => $result['payment']['status'] ?? 'completed',
                'seller_orders' => $sellerOrdersInfo,
            ];

            // ✅ CORREGIDO: Agregar información de pricing con validación de campos
            if (isset($result['pricing_info'])) {
                $pricingInfo = $result['pricing_info'];

                // Información básica siempre disponible
                $responseData['billed_amount'] = $pricingInfo['billed_amount'] ?? null;
                $responseData['paid_amount'] = $pricingInfo['paid_amount'] ?? null;
                $responseData['total_savings'] = $pricingInfo['total_savings'] ?? 0;
                $responseData['volume_discounts_applied'] = $pricingInfo['volume_discounts_applied'] ?? false;

                // ✅ CORREGIDO: Verificar que los campos existan antes de acceder
                if (isset($pricingInfo['totals'])) {
                    $totals = $pricingInfo['totals'];
                    $responseData['shipping_cost'] = $totals['shipping_cost'] ?? 0;
                    $responseData['iva_amount'] = $totals['iva_amount'] ?? 0;
                    $responseData['seller_discount_savings'] = $totals['seller_discounts'] ?? 0;
                    $responseData['volume_discount_savings'] = $totals['volume_discounts'] ?? 0;
                }

                // Información de envío
                if (isset($pricingInfo['shipping_info'])) {
                    $responseData['free_shipping'] = $pricingInfo['shipping_info']['free_shipping'] ?? false;
                }

                // Desglose completo (opcional)
                if (isset($pricingInfo['breakdown'])) {
                    $responseData['breakdown'] = $pricingInfo['breakdown'];
                }
            }

            // ✅ CORREGIDO: Mensaje de éxito con manejo seguro de campos
            $message = 'Pedido completado con éxito';
            $messageParts = [];

            // Verificar ahorros
            $totalSavings = $responseData['total_savings'] ?? 0;
            if ($totalSavings > 0) {
                $messageParts[] = sprintf('Has ahorrado $%.2f con descuentos', $totalSavings);
            }

            // Verificar envío gratis
            $freeShipping = $responseData['free_shipping'] ?? false;
            if ($freeShipping) {
                $messageParts[] = '¡Envío gratis aplicado!';
            }

            if (! empty($messageParts)) {
                $message .= ' - '.implode(' y ', $messageParts);
            }

            Log::info('✅ Checkout completado exitosamente', [
                'order_id' => $responseData['order_id'],
                'order_number' => $responseData['order_number'],
                'total' => $responseData['total'],
                'savings' => $totalSavings,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error en proceso de checkout: '.$e->getMessage(), [
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
