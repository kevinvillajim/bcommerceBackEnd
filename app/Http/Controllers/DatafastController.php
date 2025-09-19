<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Validators\Payment\Datafast\UnifiedDatafastValidator;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use App\Models\DatafastPayment;
use App\Services\CheckoutDataService;
use App\Services\PaymentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DatafastController extends Controller
{
    public function __construct(
        private DatafastService $datafastService,
        private ShoppingCartRepositoryInterface $cartRepository,
        private CheckoutDataService $checkoutDataService,
        private PaymentProcessingService $paymentProcessingService,
        private UnifiedDatafastValidator $unifiedValidator
    ) {
        $this->middleware('jwt.auth');
    }

    /**
     * Almacenar CheckoutData temporal validado desde frontend
     */
    public function storeCheckoutData(Request $request)
    {
        try {
            $user = $request->user();

            // Validar datos de CheckoutData desde frontend
            $validated = $request->validate([
                'shippingData' => 'required|array',
                'billingData' => 'required|array',
                'items' => 'required|array|min:1',
                'totals' => 'required|array',
                'sessionId' => 'required|string|max:100',
                'discountCode' => 'sometimes|string|nullable',
                'discountInfo' => 'sometimes|array|nullable',
            ]);

            // Crear CheckoutData con expiración
            $checkoutData = new \App\Domain\ValueObjects\CheckoutData(
                userId: $user->id,
                shippingData: $validated['shippingData'],
                billingData: $validated['billingData'],
                items: $validated['items'],
                totals: $validated['totals'],
                sessionId: $validated['sessionId'],
                validatedAt: now(),
                expiresAt: now()->addMinutes(30), // 30 minutos de expiración
                discountCode: $validated['discountCode'] ?? null,
                discountInfo: $validated['discountInfo'] ?? null
            );

            // Almacenar en CheckoutDataService
            $cacheKey = $this->checkoutDataService->store($checkoutData);

            // Trackear session_id por usuario para simulaciones posteriores
            $userSessionsKey = "user_sessions_{$user->id}";
            $userSessions = Cache::get($userSessionsKey, []);
            $userSessions[] = $validated['sessionId'];
            // Mantener solo las últimas 5 sessions para evitar acumulación
            $userSessions = array_slice($userSessions, -5);
            Cache::put($userSessionsKey, $userSessions, 1800); // 30 min igual que CheckoutData

            Log::info('✅ CheckoutData almacenado exitosamente', [
                'user_id' => $user->id,
                'session_id' => $validated['sessionId'],
                'cache_key' => $cacheKey,
                'final_total' => $checkoutData->getFinalTotal(),
                'items_count' => count($validated['items']),
                'tracked_sessions' => count($userSessions),
            ]);

            return response()->json([
                'success' => true,
                'status' => 'success', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => 'CheckoutData almacenado exitosamente',
                'data' => [
                    'session_id' => $validated['sessionId'],
                    'expires_at' => $checkoutData->expiresAt->toISOString(),
                    'final_total' => $checkoutData->getFinalTotal(),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación en storeCheckoutData', [
                'errors' => $e->errors(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => 'Datos de checkout inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('❌ Error al almacenar CheckoutData', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => 'Error interno al almacenar checkout data',
            ], 500);
        }
    }

    /**
     * Crear un checkout de Datafast
     */
    public function createCheckout(Request $request)
    {
        try {
            $user = $request->user();


            // ✅ NUEVO: Validar datos incluyendo campos de CheckoutData temporal
            $validated = $request->validate([
                'shippingAddress' => 'required|array',
                'shippingAddress.street' => 'required|string|max:100', // street en lugar de address
                'shippingAddress.city' => 'required|string|max:50',
                'shippingAddress.country' => 'required|string|max:100', // max:100 en lugar de size:2 por compatibilidad
                // ✅ NO validar shippingAddress.identification - usar solo customer.doc_id
                'customer' => 'required|array', // ✅ OBLIGATORIO PARA SRI
                'customer.given_name' => 'sometimes|string|max:48',
                'customer.middle_name' => 'sometimes|string|max:50',
                'customer.surname' => 'sometimes|string|max:48',
                'customer.phone' => 'sometimes|string|min:7|max:25',
                'customer.doc_id' => 'required|string|size:10|regex:/^\d{10}$/', // ✅ OBLIGATORIO: Solo 10 dígitos numéricos para SRI
                'total' => 'required|numeric|min:0.01',
                'subtotal' => 'sometimes|numeric|min:0',
                'shipping_cost' => 'sometimes|numeric|min:0',
                'tax' => 'sometimes|numeric|min:0',
                'items' => 'sometimes|array',
                'discount_code' => 'sometimes|string|nullable',
                'discount_info' => 'sometimes|array|nullable',
                // ✅ NUEVOS CAMPOS PARA CHECKOUTDATA TEMPORAL
                'session_id' => 'sometimes|string|max:100',
                'validated_at' => 'sometimes|string',
            ]);


            // ✅ VALIDAR SI SE RECIBIÓ CHECKOUTDATA TEMPORAL
            $hasSessionId = isset($validated['session_id']) && ! empty($validated['session_id']);
            $hasValidatedAt = isset($validated['validated_at']) && ! empty($validated['validated_at']);
            $isTemporalCheckout = $hasSessionId && $hasValidatedAt;

            if ($isTemporalCheckout) {
                Log::info('🎯 Datafast: Procesando CheckoutData temporal validado', [
                    'session_id' => $validated['session_id'],
                    'validated_at' => $validated['validated_at'],
                    'user_id' => $user->id,
                    'total' => $validated['total'],
                ]);
            }

            // ✅ NUEVO: Requerir items cuando viene de CheckoutData temporal
            $cart = null;
            $hasRequestItems = isset($validated['items']) && is_array($validated['items']) && count($validated['items']) > 0;

            if ($isTemporalCheckout && ! $hasRequestItems) {
                return response()->json([
                    'success' => false,
                    'message' => 'CheckoutData temporal debe incluir items validados',
                ], 400);
            }

            if ($hasRequestItems) {
                Log::info('✅ Datafast: Usando items del CheckoutData validado', [
                    'items_count' => count($validated['items']),
                    'is_temporal' => $isTemporalCheckout,
                    'session_id' => $validated['session_id'] ?? 'none',
                ]);
            } else {
                // Fallback: buscar carrito en base de datos
                $cart = $this->cartRepository->findByUserId($user->id);

                if (! $cart || count($cart->getItems()) === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El carrito está vacío y no se recibieron items en el request',
                    ], 400);
                }

                Log::info('✅ Datafast: Usando items del carrito en BD', [
                    'items_count' => count($cart->getItems()),
                ]);
            }

            // ✅ CORREGIDO: Asegurar que el total sea numérico con casting explícito
            $calculatedTotal = (float) $validated['total'];

            // ✅ CORREGIDO: Log adaptado para ambos casos
            $logData = [
                'user_id' => $user->id,
                'calculated_total' => $calculatedTotal,
                // ✅ CORREGIDO: Casting explícito en datos de logging también
                'frontend_data' => [
                    'subtotal' => isset($validated['subtotal']) ? (float) $validated['subtotal'] : null,
                    'shipping_cost' => isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : null,
                    'tax' => isset($validated['tax']) ? (float) $validated['tax'] : null,
                ],
            ];

            if ($hasRequestItems) {
                $logData['items_count'] = count($validated['items']);
                $logData['source'] = 'request_items';
            } else {
                $logData['items_count'] = count($cart->getItems());
                $logData['cart_total'] = $cart->getTotal();
                $logData['source'] = 'database_cart';
            }

            Log::info('Datafast: Creando checkout para usuario', $logData);

            // Generar transaction_id único
            $transactionId = 'ORDER_'.time().'_'.$user->id.'_'.uniqid();

            // ✅ CREAR REGISTRO DE TRANSACCIÓN DATAFAST
            $datafastPayment = DatafastPayment::create([
                'user_id' => $user->id,
                'transaction_id' => $transactionId,
                'amount' => $calculatedTotal,
                'calculated_total' => $calculatedTotal,
                // ✅ CORREGIDO: Casting explícito de tipos numéricos opcionales
                'subtotal' => isset($validated['subtotal']) ? (float) $validated['subtotal'] : null,
                'shipping_cost' => isset($validated['shipping_cost']) ? (float) $validated['shipping_cost'] : null,
                'tax' => isset($validated['tax']) ? (float) $validated['tax'] : null,
                'currency' => 'USD',
                'status' => 'pending',
                'environment' => config('app.env') === 'production' ? 'production' : 'test',
                'phase' => 'phase2',

                // ✅ CRÍTICO: Validación estricta de customer.doc_id - SIN FALLBACKS HARDCODEADOS
                'customer_given_name' => $validated['customer']['given_name'] ?? $user->name ?? 'Cliente',
                'customer_middle_name' => $validated['customer']['middle_name'] ?? null,
                'customer_surname' => $validated['customer']['surname'] ?? 'Prueba',
                'customer_phone' => $validated['customer']['phone'] ?? $user->phone ?? null,
                'customer_doc_id' => $validated['customer']['doc_id'], // ✅ SIN FALLBACK - Ya validado como requerido
                'customer_email' => $user->email,

                // ✅ CORREGIDO: Información de envío usando shippingAddress
                'shipping_address' => $validated['shippingAddress']['street'], // street en lugar de address
                'shipping_city' => $validated['shippingAddress']['city'],
                'shipping_country' => strtoupper($validated['shippingAddress']['country']),
                'shipping_identification' => $validated['customer']['doc_id'], // ✅ USAR SOLO customer.doc_id VALIDADO

                // Información técnica
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),

                // Datos de descuentos
                'discount_code' => $validated['discount_code'] ?? null,
                'discount_info' => $validated['discount_info'] ?? null,

                // Timestamps
                'checkout_created_at' => now(),
            ]);

            Log::info('Datafast Payment creado en BD', [
                'datafast_payment_id' => $datafastPayment->id,
                'transaction_id' => $transactionId,
                'user_id' => $user->id,
                'amount' => $calculatedTotal,
            ]);

            // Preparar datos para Datafast
            $orderData = [
                'amount' => $calculatedTotal,
                'transaction_id' => $transactionId,
                'customer' => [
                    'id' => $user->id,
                    'given_name' => $validated['customer']['given_name'] ?? $user->name ?? 'Cliente',
                    'middle_name' => $validated['customer']['middle_name'] ?? null,
                    'surname' => $validated['customer']['surname'] ?? 'Prueba',
                    'email' => $user->email,
                    'phone' => $validated['customer']['phone'] ?? $user->phone ?? null,
                    'doc_id' => $validated['customer']['doc_id'], // ✅ SIN FALLBACK - Ya validado como requerido y debe tener 10 dígitos
                    'ip' => $request->ip(),
                ],
                'shipping' => [
                    'street' => $validated['shippingAddress']['street'], // ✅ CORREGIDO: usar 'street' consistente
                    'country' => strtoupper($validated['shippingAddress']['country']),
                ],
                'billing' => [
                    'street' => $validated['shippingAddress']['street'], // ✅ CORREGIDO: usar 'street' consistente
                    'country' => strtoupper($validated['shippingAddress']['country']),
                ],
                'items' => [],
            ];

            // ✅ CORREGIDO: Usar items del request si están disponibles, sino del carrito
            if ($hasRequestItems) {
                // Usar items del request (checkout directo)
                foreach ($validated['items'] as $requestItem) {
                    Log::info('Procesando item del request (checkout directo)', [
                        'product_id' => $requestItem['product_id'],
                        'quantity' => $requestItem['quantity'],
                        'price' => $requestItem['price'],
                    ]);

                    // Obtener información del producto
                    $productName = 'Producto '.$requestItem['product_id'];
                    $productDescription = 'Descripción del producto';

                    try {
                        $product = $this->productRepository->findById($requestItem['product_id']);
                        if ($product) {
                            $productName = $product->getName();
                            $productDescription = $product->getDescription() ?: 'Descripción del producto';
                        }
                    } catch (\Exception $e) {
                        Log::warning('No se pudo obtener información del producto', [
                            'product_id' => $requestItem['product_id'],
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $orderData['items'][] = [
                        'name' => $productName,
                        'description' => $productDescription,
                        'price' => $requestItem['price'],
                        'quantity' => $requestItem['quantity'],
                    ];
                }
            } else {
                // Fallback: usar items del carrito en BD
                foreach ($cart->getItems() as $item) {
                    Log::info('Procesando item del carrito (BD)', [
                        'product_id' => $item->getProductId(),
                        'quantity' => $item->getQuantity(),
                        'price' => $item->getPrice(),
                        'subtotal' => $item->getSubtotal(),
                    ]);

                    // Obtener información del producto
                    $productName = 'Producto '.$item->getProductId();
                    $productDescription = 'Descripción del producto';

                    try {
                        $product = $this->productRepository->findById($requestItem['product_id']);
                        if ($product) {
                            $productName = $product->getName();
                            $productDescription = $product->getDescription() ?: 'Descripción del producto';
                        }
                    } catch (\Exception $e) {
                        Log::warning('No se pudo obtener información del producto', [
                            'product_id' => $requestItem['product_id'],
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $orderData['items'][] = [
                        'name' => $productName,
                        'description' => $productDescription,
                        'price' => $item->getPrice(),
                        'quantity' => $item->getQuantity(),
                    ];
                }
            }

            Log::info('Datafast: Datos preparados para checkout', [
                'user_id' => $user->id,
                'transaction_id' => $transactionId,
                'amount' => $calculatedTotal,
                'items_count' => count($orderData['items']),
            ]);

            // Crear checkout en Datafast
            $result = $this->datafastService->createCheckout($orderData);

            Log::info('Datafast: Respuesta de createCheckout', $result);

            // ✅ ACTUALIZAR REGISTRO CON RESPUESTA DE DATAFAST
            $updateData = [
                'request_data' => $orderData,
                'response_data' => $result,
            ];

            if ($result['success']) {
                $updateData = array_merge($updateData, [
                    'checkout_id' => $result['checkout_id'],
                    'widget_url' => $result['widget_url'],
                    'status' => 'processing', // Checkout creado, esperando pago
                ]);

                $datafastPayment->update($updateData);

                return response()->json([
                    'success' => true, // ✅ CORREGIDO: Usar success boolean como campo principal
                    'status' => 'success', // ✅ AÑADIDO: Status descriptivo para consistencia con TypeScript
                    'data' => [
                        'checkout_id' => $result['checkout_id'],
                        'widget_url' => $result['widget_url'],
                        'transaction_id' => $transactionId,
                        'amount' => $calculatedTotal,
                    ],
                    'message' => 'Checkout creado exitosamente',
                ]);
            }

            // ✅ MARCAR COMO FAILED SI FALLÓ LA CREACIÓN DEL CHECKOUT
            $datafastPayment->update(array_merge($updateData, [
                'status' => 'failed',
                'error_message' => $result['message'] ?? 'Error al crear checkout',
                'result_code' => $result['error_code'] ?? null,
            ]));

            return response()->json([
                'success' => false,
                'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => $result['message'] ?? 'Error al crear checkout',
                'error_code' => $result['error_code'] ?? null,
            ], 400);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Datafast: Error de validación', [
                'errors' => $e->errors(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear checkout de Datafast', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => 'Error interno del servidor',
                'debug_error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * ✅ CONSULTAR ESTADO: Solo consulta estado sin procesamiento
     *
     * PROPÓSITO: Endpoint GET para debugging/monitoreo - No procesa órdenes
     * USADO POR: Herramientas de administración, debugging
     * DIFERENCIA: No crea órdenes ni procesa checkout, solo consulta estado
     */
    public function checkPaymentStatus($transactionId)
    {
        try {
            $user = auth()->user();

            Log::info('Datafast: Verificando estado del pago por transactionId', [
                'transaction_id' => $transactionId,
                'user_id' => $user->id ?? 'guest',
            ]);

            // Buscar registro de transacción Datafast
            $datafastPayment = DatafastPayment::where('transaction_id', $transactionId)->first();

            if (! $datafastPayment) {
                Log::warning('Datafast: No se encontró registro de transacción para verificación', [
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Transacción no encontrada',
                    'data' => [
                        'payment_status' => 'failed', // ✅ ESTANDARIZADO: not_found -> failed (estado válido)
                        'transaction_id' => $transactionId,
                    ],
                ], 404);
            }

            // Si el pago ya está completado, devolver el estado
            if ($datafastPayment->status === 'completed') {
                Log::info('Datafast: Pago ya completado', [
                    'transaction_id' => $transactionId,
                    'order_id' => $datafastPayment->order_id,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Pago completado exitosamente',
                    'data' => [
                        'payment_status' => $datafastPayment->payment_status, // ✅ UNIFICADO: Usar accessor del modelo
                        'transaction_id' => $transactionId,
                        'order_id' => $datafastPayment->order_id,
                        'checkout_id' => $datafastPayment->checkout_id,
                        'amount' => $datafastPayment->amount,
                    ],
                ]);
            }

            // Si el pago falló, devolver el estado
            if ($datafastPayment->status === 'failed') {
                return response()->json([
                    'status' => 'error',
                    'message' => $datafastPayment->error_message ?? 'Pago fallido',
                    'data' => [
                        'payment_status' => 'failed', // ✅ ESTADO ESTÁNDAR: Definido en modelo
                        'transaction_id' => $transactionId,
                        'error_code' => $datafastPayment->result_code,
                    ],
                ]);
            }

            // Si tiene checkout_id pero no resource_path, el pago está pendiente
            if ($datafastPayment->checkout_id && ! $datafastPayment->resource_path) {
                Log::info('Datafast: Pago pendiente, esperando completar el formulario', [
                    'transaction_id' => $transactionId,
                    'checkout_id' => $datafastPayment->checkout_id,
                ]);

                // Intentar verificar con Datafast directamente usando checkout_id
                try {
                    // Construir un resource path temporal para verificación
                    $tempResourcePath = "/v1/checkouts/{$datafastPayment->checkout_id}/payment";
                    $result = $this->datafastService->verifyPayment($tempResourcePath);

                    Log::info('Datafast: Resultado de verificación directa', [
                        'transaction_id' => $transactionId,
                        'result' => $result,
                    ]);

                    if ($result['success']) {
                        // Actualizar el estado del pago
                        $datafastPayment->update([
                            'status' => 'completed',
                            'verification_data' => $result,
                            'result_code' => $result['result_code'] ?? null,
                            'verification_completed_at' => now(),
                        ]);

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Pago verificado exitosamente',
                            'data' => [
                                'payment_status' => $datafastPayment->payment_status, // ✅ UNIFICADO: Usar accessor del modelo
                                'transaction_id' => $transactionId,
                                'checkout_id' => $datafastPayment->checkout_id,
                                'amount' => $result['amount'] ?? $datafastPayment->amount,
                            ],
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Datafast: No se pudo verificar directamente con checkout_id', [
                        'transaction_id' => $transactionId,
                        'error' => $e->getMessage(),
                    ]);
                }

                return response()->json([
                    'status' => 'pending',
                    'message' => 'Pago pendiente de completar',
                    'data' => [
                        'payment_status' => 'pending', // ✅ ESTADO ESTÁNDAR: Definido en modelo
                        'transaction_id' => $transactionId,
                        'checkout_id' => $datafastPayment->checkout_id,
                        'widget_url' => $datafastPayment->widget_url,
                    ],
                ]);
            }

            // Estado de procesamiento
            return response()->json([
                'status' => 'processing',
                'message' => 'Pago en proceso',
                'data' => [
                    'payment_status' => $datafastPayment->payment_status, // ✅ UNIFICADO: Usar accessor consistente
                    'transaction_id' => $transactionId,
                    'checkout_id' => $datafastPayment->checkout_id,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error al verificar estado del pago de Datafast', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al verificar el estado del pago',
                'data' => [
                    'payment_status' => 'error', // ✅ ESTADO ESTÁNDAR: Definido en modelo
                    'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
                ],
            ], 500);
        }
    }

    /**
     * ✅ VERIFICAR PAGO: Verificación completa con procesamiento de checkout
     *
     * PROPÓSITO: Endpoint POST principal para procesar pagos completados
     * USADO POR: DatafastResultPage (frontend) tras pago exitoso
     * FUNCIONALIDAD: Verifica pago + crea orden + procesa checkout completo
     * DIFERENCIA: Este SÍ procesa y crea órdenes, es el flujo principal
     */
    public function verifyPayment(Request $request)
    {
        try {
            // 🔍 LOGGING TEMPORAL: Capturar datos RAW de entrada para debug
            Log::info('🚨 [DEBUG] DatafastController->verifyPayment() - RAW REQUEST DATA', [
                'all_request_data' => $request->all(),
                'has_simulate_success' => $request->has('simulate_success'),
                'simulate_success_value' => $request->get('simulate_success'),
                'simulate_success_type' => gettype($request->get('simulate_success')),
                'headers' => $request->headers->all(),
                'method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
            ]);

            $validated = $request->validate([
                'resource_path' => 'required|string',
                'transaction_id' => 'required|string',
                'calculated_total' => 'sometimes|numeric|min:0', // ✅ OPCIONAL: Para verificación adicional de seguridad
                'session_id' => 'sometimes|string|max:100',       // ✅ OPCIONAL: Para arquitectura centralizada
                'simulate_success' => 'sometimes', // ✅ OPCIONAL: Para pruebas y simulaciones
            ]);

            $user = $request->user();

            Log::info('🔍 DatafastController: Verificando pago con arquitectura centralizada', [
                'transaction_id' => $validated['transaction_id'],
                'user_id' => $user->id,
                'has_session_id' => isset($validated['session_id']),
            ]);

            // Buscar registro de transacción Datafast
            $datafastPayment = DatafastPayment::where('transaction_id', $validated['transaction_id'])->first();

            if (! $datafastPayment) {
                Log::warning('Datafast: No se encontró registro de transacción', [
                    'transaction_id' => $validated['transaction_id'],
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró registro de la transacción',
                ], 404);
            }

            // Actualizar resource_path
            $datafastPayment->update([
                'resource_path' => $validated['resource_path'],
                'verification_completed_at' => now(),
            ]);

            // 🔍 LOGGING TEMPORAL: Datos después de validación
            Log::info('🚨 [DEBUG] Datos después de validation', [
                'validated_data' => $validated,
                'has_simulate_success_validated' => isset($validated['simulate_success']),
                'simulate_success_validated_value' => $validated['simulate_success'] ?? 'NOT_PRESENT',
                'simulate_success_validated_type' => gettype($validated['simulate_success'] ?? null),
            ]);

            // ✅ NUEVO: Determinar session_id correcto ANTES de validar
            $sessionId = $validated['session_id'] ?? null;
            if (!$sessionId && isset($validated['simulate_success']) && $validated['simulate_success']) {
                // Para simulaciones, buscar el session_id real basado en el usuario
                $sessionId = $this->findSessionIdForUser($user->id, $validated['transaction_id']);
                Log::info('🎯 Session_id para validación', [
                    'found_session_id' => $sessionId,
                    'user_id' => $user->id,
                    'transaction_id' => $validated['transaction_id'],
                ]);
            }

            // ✅ NUEVO: Recuperar CheckoutData con session_id correcto ANTES de validar
            $checkoutData = null;
            if ($sessionId) {
                $checkoutData = $this->checkoutDataService->retrieve($sessionId);
                if ($checkoutData) {
                    Log::info('✅ CheckoutData recuperado para validación Datafast', [
                        'session_id' => $sessionId,
                        'total' => $checkoutData->getFinalTotal(),
                    ]);
                }
            }

            // ✅ CORREGIDO: VALIDACIÓN UNIFICADA con monto correcto del CheckoutData
            $paymentData = array_merge($validated, [
                'calculated_total' => $checkoutData?->getFinalTotal() ??
                                    (isset($validated['calculated_total']) ? (float) $validated['calculated_total'] : null),
            ]);

            Log::info('🔄 Usando validador unificado Datafast', [
                'transaction_id' => $validated['transaction_id'],
                'has_simulate_success' => isset($validated['simulate_success']),
                'has_resource_path' => isset($validated['resource_path']),
                'calculated_total' => $paymentData['calculated_total'],
            ]);

            // Validar con el validador unificado
            $validationResult = $this->unifiedValidator->validatePayment($paymentData);

            // Actualizar registro con resultado unificado
            $datafastPayment->update([
                'verification_data' => [
                    'validation_type' => 'unified',
                    'result' => $validationResult->toArray(),
                ],
                'result_code' => $validationResult->metadata['result_code'] ?? null,
                'result_description' => $validationResult->errorMessage ?? 'Verificación completada',
            ]);

            if ($validationResult->isSuccessful()) {
                // Procesar pago exitoso con servicio centralizado
                Log::info('✅ Validación Datafast exitosa, procesando con PaymentProcessingService', [
                    'transaction_id' => $validated['transaction_id'],
                    'payment_method' => $validationResult->paymentMethod,
                    'validation_type' => $validationResult->validationType,
                ]);

                // Usar session_id ya calculado (evitar duplicación)
                $sessionId = $sessionId ?? 'datafast_' . $validated['transaction_id'];

                $processingResult = $this->paymentProcessingService->processSuccessfulPayment(
                    $validationResult,
                    $sessionId
                );

                if ($processingResult['success']) {
                    // Marcar como completado y vincular orden
                    $datafastPayment->markAsCompleted(
                        $validationResult->metadata['payment_id'] ?? null,
                        $validationResult->metadata['result_code'] ?? 'completed',
                        'Orden creada exitosamente con arquitectura centralizada'
                    );

                    $datafastPayment->update(['order_id' => $processingResult['order']['id']]);

                    // Limpiar CheckoutData temporal si existe
                    if ($sessionId && $sessionId !== 'datafast_' . $validated['transaction_id']) {
                        $this->checkoutDataService->deleteCheckoutData($sessionId);
                    }

                    Log::info('✅ Pago Datafast procesado exitosamente', [
                        'order_id' => $processingResult['order']['id'],
                        'transaction_id' => $validated['transaction_id'],
                    ]);

                    return response()->json([
                        'success' => true, // ✅ AÑADIDO: Campo principal boolean
                        'status' => 'success',
                        'data' => [
                            'order_id' => $processingResult['order']['id'],
                            'order_number' => $processingResult['order']['number'],
                            'total' => $processingResult['order']['total'],
                            'payment_status' => $datafastPayment->payment_status, // ✅ UNIFICADO: Usar accessor del modelo
                            'payment_id' => $validationResult->metadata['payment_id'] ?? '',
                            'transaction_id' => $validated['transaction_id'], // ✅ AÑADIDO: Requerido por TypeScript
                            'processed_at' => now()->toISOString(), // ✅ AÑADIDO: Timestamp requerido por TypeScript
                        ],
                        'message' => 'Pago procesado exitosamente',
                    ]);
                }

                // Error en procesamiento
                $datafastPayment->markAsFailed(
                    $processingResult['message'] ?? 'Error en procesamiento',
                    'processing_failed'
                );

                return response()->json([
                    'success' => false,
                    'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                    'message' => $processingResult['message'] ?? 'Error al procesar el pago',
                ], 400);
            }

            // Pago fallido
            $datafastPayment->markAsFailed(
                $validationResult->errorMessage ?? 'Validación de pago fallida',
                $validationResult->errorCode ?? 'validation_failed'
            );

            Log::warning('⚠️ Validación Datafast fallida', [
                'transaction_id' => $validated['transaction_id'],
                'error_code' => $validationResult->errorCode,
                'error_message' => $validationResult->errorMessage,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => $validationResult->errorMessage,
                'error_code' => $validationResult->errorCode,
                'result_code' => $validationResult->errorCode, // ✅ AÑADIDO: Alias para compatibilidad TypeScript
                'metadata' => $validationResult->metadata,
            ], 400);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Datafast: Error de validación', [
                'errors' => $e->errors(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error', // ✅ AÑADIDO: Consistencia con interfaces TypeScript
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al verificar pago de Datafast', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
                'request_data' => $request->all(),
            ]);

            // ✅ MANEJO ESPECÍFICO: Error de CheckoutData faltante
            if (str_contains($e->getMessage(), 'CheckoutData no encontrado')) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'El pago fue procesado exitosamente pero la sesión de checkout ha expirado. Contacte a soporte con su número de transacción.',
                    'error_code' => 'CHECKOUT_DATA_EXPIRED',
                    'metadata' => [
                        'result_code' => 'CHECKOUT_EXPIRED',
                        'original_message' => 'Sesión de checkout expirada después de pago exitoso',
                        'validation_type' => 'checkout_data_error'
                    ]
                ], 400); // 400 en lugar de 500 porque es un error de estado/tiempo
            }

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Error al verificar el pago: '.$e->getMessage(),
                'debug_error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }


    /**
     * Webhook para recibir notificaciones de Datafast usando arquitectura centralizada
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('🔔 Datafast Webhook recibido con arquitectura centralizada', [
                'payload' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            // Validar webhook con validador unificado
            $validationResult = $this->unifiedValidator->validatePayment($request->all());

            if ($validationResult->isSuccessful()) {
                Log::info('✅ Webhook Datafast validado exitosamente', [
                    'transaction_id' => $validationResult->metadata['transaction_id'] ?? 'N/A',
                    'payment_id' => $validationResult->metadata['payment_id'] ?? 'N/A',
                ]);

                // Buscar usuario asociado al transaction_id si disponible
                $transactionId = $validationResult->metadata['transaction_id'] ?? null;
                $userId = null;

                if ($transactionId) {
                    $datafastPayment = DatafastPayment::where('transaction_id', $transactionId)->first();
                    if ($datafastPayment) {
                        $userId = $datafastPayment->user_id;
                    }
                }

                if ($userId) {
                    // Procesar webhook con usuario identificado
                    $processingResult = $this->paymentProcessingService->processSuccessfulPayment(
                        $validationResult,
                        $userId,
                        null // No CheckoutData en webhooks
                    );

                    if ($processingResult['success']) {
                        Log::info('✅ Webhook Datafast procesado exitosamente', [
                            'order_id' => $processingResult['order']['id'],
                            'transaction_id' => $transactionId,
                        ]);

                        return response()->json(['status' => 'processed']);
                    }

                    Log::warning('⚠️ Error procesando webhook Datafast', [
                        'transaction_id' => $transactionId,
                        'message' => $processingResult['message'] ?? 'Error desconocido',
                    ]);

                    return response()->json(['status' => 'error', 'message' => 'Processing failed'], 400);
                }

                Log::warning('⚠️ Webhook Datafast válido pero sin usuario asociado', [
                    'transaction_id' => $transactionId,
                ]);

                return response()->json(['status' => 'received', 'message' => 'No user found']);
            }

            Log::warning('⚠️ Webhook Datafast inválido', [
                'error_code' => $validationResult->errorCode,
                'error_message' => $validationResult->errorMessage,
                'payload' => $request->all(),
            ]);

            return response()->json(['status' => 'invalid'], 400);

        } catch (\Exception $e) {
            Log::error('Error en webhook de Datafast', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Busca el session_id real para un usuario y transacción específica
     * Útil para simulaciones donde necesitamos encontrar el CheckoutData original
     */
    private function findSessionIdForUser(int $userId, string $transactionId): ?string
    {
        try {
            // Buscar sessions trackeadas del usuario
            $userSessionsKey = "user_sessions_{$userId}";
            $userSessions = Cache::get($userSessionsKey, []);

            Log::info('🔍 Buscando session_id para simulación', [
                'user_id' => $userId,
                'transaction_id' => $transactionId,
                'user_sessions_count' => count($userSessions),
            ]);

            // Revisar cada session del usuario
            foreach ($userSessions as $sessionId) {
                $cacheKey = "checkout_data_{$sessionId}";
                $checkoutData = Cache::get($cacheKey);

                if ($checkoutData) {
                    // Verificar si esta sesión pertenece al usuario correcto
                    if (isset($checkoutData['userId']) && $checkoutData['userId'] == $userId) {
                        Log::info('✅ Session_id encontrado para simulación', [
                            'session_id' => $sessionId,
                            'user_id' => $userId,
                            'checkout_total' => $checkoutData['totals']['final_total'] ?? 'N/A',
                        ]);
                        return $sessionId;
                    }
                }
            }

            Log::warning('⚠️ No se encontró session_id válido para simulación', [
                'user_id' => $userId,
                'transaction_id' => $transactionId,
                'checked_sessions' => count($userSessions),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('❌ Error buscando session_id para simulación', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
