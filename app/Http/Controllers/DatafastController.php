<?php

namespace App\Http\Controllers;

use App\Domain\Entities\OrderEntity;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use App\Models\DatafastPayment;
use App\UseCases\Order\CreateOrderUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DatafastController extends Controller
{
    private DatafastService $datafastService;

    private ShoppingCartRepositoryInterface $cartRepository;

    private OrderRepositoryInterface $orderRepository;

    private ProductRepositoryInterface $productRepository;

    private CreateOrderUseCase $createOrderUseCase;

    public function __construct(
        DatafastService $datafastService,
        ShoppingCartRepositoryInterface $cartRepository,
        OrderRepositoryInterface $orderRepository,
        ProductRepositoryInterface $productRepository,
        CreateOrderUseCase $createOrderUseCase
    ) {
        $this->datafastService = $datafastService;
        $this->cartRepository = $cartRepository;
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->createOrderUseCase = $createOrderUseCase;
        $this->middleware('jwt.auth');
    }

    /**
     * Crear un checkout de Datafast
     */
    public function createCheckout(Request $request)
    {
        try {
            $user = $request->user();

            // ✅ CORREGIDO: Validar datos usando formato shippingAddress consistente con CheckoutController
            $validated = $request->validate([
                'shippingAddress' => 'required|array',
                'shippingAddress.street' => 'required|string|max:100', // street en lugar de address
                'shippingAddress.city' => 'required|string|max:50',
                'shippingAddress.country' => 'required|string|max:100', // max:100 en lugar de size:2 por compatibilidad
                'shippingAddress.identification' => 'sometimes|string|max:13', // max:13 para RUC
                'customer' => 'required|array', // ✅ OBLIGATORIO PARA SRI
                'customer.given_name' => 'sometimes|string|max:48',
                'customer.middle_name' => 'sometimes|string|max:50',
                'customer.surname' => 'sometimes|string|max:48',
                'customer.phone' => 'sometimes|string|min:7|max:25',
                'customer.doc_id' => 'required|string|size:10', // ✅ OBLIGATORIO PARA SRI
                'total' => 'required|numeric|min:0.01',
                'subtotal' => 'sometimes|numeric|min:0',
                'shipping_cost' => 'sometimes|numeric|min:0',
                'tax' => 'sometimes|numeric|min:0',
                'items' => 'sometimes|array',
                'discount_code' => 'sometimes|string|nullable',
                'discount_info' => 'sometimes|array|nullable',
            ]);

            // ✅ CORREGIDO: Usar items del request si están disponibles, sino buscar en carrito
            $cart = null;
            $hasRequestItems = isset($validated['items']) && is_array($validated['items']) && count($validated['items']) > 0;

            if ($hasRequestItems) {
                Log::info('✅ Datafast: Usando items del request (checkout directo)', [
                    'items_count' => count($validated['items']),
                    'items' => $validated['items'],
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

            // Usar el total calculado que viene del frontend (con descuentos, envío e IVA)
            $calculatedTotal = $validated['total'];

            // ✅ CORREGIDO: Log adaptado para ambos casos
            $logData = [
                'user_id' => $user->id,
                'calculated_total' => $calculatedTotal,
                'frontend_data' => [
                    'subtotal' => $validated['subtotal'] ?? null,
                    'shipping_cost' => $validated['shipping_cost'] ?? null,
                    'tax' => $validated['tax'] ?? null,
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
                'subtotal' => $validated['subtotal'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? null,
                'tax' => $validated['tax'] ?? null,
                'currency' => 'USD',
                'status' => 'pending',
                'environment' => config('app.env') === 'production' ? 'production' : 'test',
                'phase' => 'phase2',

                // Información del cliente
                'customer_given_name' => $validated['customer']['given_name'] ?? $user->name ?? 'Cliente',
                'customer_middle_name' => $validated['customer']['middle_name'] ?? 'De',
                'customer_surname' => $validated['customer']['surname'] ?? 'Prueba',
                'customer_phone' => $validated['customer']['phone'] ?? '0999999999',
                'customer_doc_id' => str_pad($validated['customer']['doc_id'] ?? '1234567890', 10, '0', STR_PAD_LEFT),
                'customer_email' => $user->email,

                // ✅ CORREGIDO: Información de envío usando shippingAddress
                'shipping_address' => $validated['shippingAddress']['street'], // street en lugar de address
                'shipping_city' => $validated['shippingAddress']['city'],
                'shipping_country' => strtoupper($validated['shippingAddress']['country']),
                'shipping_identification' => $validated['shippingAddress']['identification'] ?? $validated['customer']['doc_id'] ?? null, // ✅ CÉDULA/RUC PARA ENVÍO

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
                    'middle_name' => $validated['customer']['middle_name'] ?? 'De',
                    'surname' => $validated['customer']['surname'] ?? 'Prueba',
                    'email' => $user->email,
                    'phone' => $validated['customer']['phone'] ?? '0999999999',
                    'doc_id' => str_pad($validated['customer']['doc_id'] ?? '1234567890', 10, '0', STR_PAD_LEFT),
                    'ip' => $request->ip(),
                ],
                'shipping' => [
                    'address' => $validated['shippingAddress']['street'], // street en lugar de address
                    'country' => strtoupper($validated['shippingAddress']['country']),
                ],
                'billing' => [
                    'address' => $validated['shippingAddress']['street'], // street en lugar de address
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
                        $product = $this->productRepository->findById($item->getProductId());
                        if ($product) {
                            $productName = $product->getName();
                            $productDescription = $product->getDescription() ?: 'Descripción del producto';
                        }
                    } catch (\Exception $e) {
                        Log::warning('No se pudo obtener información del producto', [
                            'product_id' => $item->getProductId(),
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
                    'status' => 'success', // ✅ CORREGIDO: Cambiar 'success' por 'status' para consistencia con CheckoutController
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
                'message' => 'Error interno del servidor',
                'debug_error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Verificar el estado del pago por transaction ID (sin resource path)
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
                        'payment_status' => 'not_found',
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
                        'payment_status' => 'completed',
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
                        'payment_status' => 'failed',
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
                                'payment_status' => 'completed',
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
                        'payment_status' => 'pending',
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
                    'payment_status' => $datafastPayment->status,
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
                    'payment_status' => 'error',
                    'error' => config('app.debug') ? $e->getMessage() : 'Error interno',
                ],
            ], 500);
        }
    }

    /**
     * Verificar el estado del pago después del proceso de Datafast
     */
    public function verifyPayment(Request $request)
    {
        try {
            $validated = $request->validate([
                'resource_path' => 'required|string',
                'transaction_id' => 'required|string',
                'calculated_total' => 'sometimes|numeric|min:0', // ✅ ACEPTAR TOTAL CALCULADO
            ]);

            $user = $request->user();
            $simulateSuccess = $request->has('simulate_success') && $request->get('simulate_success') === 'true';

            // ✅ BUSCAR REGISTRO DE TRANSACCIÓN DATAFAST
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

            // ✅ ACTUALIZAR RESOURCE_PATH Y MARCAR INTENTO DE VERIFICACIÓN
            $datafastPayment->update([
                'resource_path' => $validated['resource_path'],
                'verification_completed_at' => now(),
            ]);

            Log::info('Datafast: Verificando pago', [
                'resource_path' => $validated['resource_path'],
                'transaction_id' => $validated['transaction_id'],
                'user_id' => $user->id,
                'datafast_payment_id' => $datafastPayment->id,
                'simulate_success' => $simulateSuccess,
            ]);

            // *** MODO SIMULACIÓN PARA PRUEBAS ***
            if ($simulateSuccess && config('app.env') !== 'production') {
                Log::info('Datafast: Modo simulación activado para pruebas');

                // ✅ FIXED: Usar datos almacenados en DatafastPayment en lugar de verificar carrito
                // El carrito puede estar vacío si ya se procesó otro pago, pero los datos están guardados
                $cart = $this->cartRepository->findByUserId($user->id);

                // Si no hay carrito o está vacío, usar el total calculado que viene en la request
                if (! $cart || count($cart->getItems()) === 0) {
                    if (! isset($validated['calculated_total'])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No se puede procesar la simulación: carrito vacío y sin total calculado',
                        ], 400);
                    }
                    $totalToUse = $validated['calculated_total'];
                    Log::info('Datafast: Usando total calculado porque el carrito está vacío', [
                        'calculated_total' => $totalToUse,
                        'reason' => 'cart_empty_or_missing',
                    ]);
                } else {
                    // ✅ USAR TOTAL CALCULADO SI ESTÁ DISPONIBLE, sino el del carrito
                    $totalToUse = $validated['calculated_total'] ?? $cart->getTotal();
                }

                // Crear orden simulada exitosa
                $simulatedResult = [
                    'success' => true,
                    'payment_id' => 'SIMULATED_'.time().'_'.uniqid(),
                    'status' => 'completed',
                    'result_code' => '000.100.110',
                    'message' => 'Pago simulado exitoso (Fase 1)',
                    'amount' => $totalToUse, // ✅ USAR TOTAL CALCULADO CORRECTO
                    'currency' => 'USD',
                ];

                Log::info('Datafast: Creando orden simulada', [
                    'user_id' => $user->id,
                    'simulated_payment_id' => $simulatedResult['payment_id'],
                    'amount' => $simulatedResult['amount'],
                    'calculated_total_used' => $totalToUse,
                    'cart_total' => $cart->getTotal(),
                ]);

                return $this->createOrderFromSuccessfulPayment($request, $simulatedResult, $validated, $datafastPayment);
            }

            // *** VERIFICACIÓN REAL CON DATAFAST ***
            $result = $this->datafastService->verifyPayment($validated['resource_path']);

            Log::info('Datafast: Resultado de verificación real', $result);

            // ✅ ACTUALIZAR REGISTRO CON DATOS DE VERIFICACIÓN
            $datafastPayment->update([
                'verification_data' => $result,
                'result_code' => $result['result_code'] ?? null,
                'result_description' => $result['message'] ?? null,
            ]);

            if ($result['success']) {
                // ✅ MARCAR COMO PROCESANDO ANTES DE CREAR ORDEN
                $datafastPayment->markAsProcessing();

                // Pago exitoso - crear orden
                return $this->createOrderFromSuccessfulPayment($request, $result, $validated, $datafastPayment);
            } else {
                // Manejar casos específicos de error
                $resultCode = $result['result_code'] ?? '';

                // Error 800.900.300 es común en Fase 1 cuando no hay transacción real
                if ($resultCode === '800.900.300') {
                    Log::info('Datafast: Error de autorización 800.900.300 - típico de Fase 1', [
                        'transaction_id' => $validated['transaction_id'],
                        'user_id' => $user->id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'No se completó una transacción de pago. En modo de prueba (Fase 1), esto es normal si no se realizó un pago real con tarjeta.',
                        'result_code' => $resultCode,
                        'is_phase_1_error' => true,
                        'suggestion' => 'Para probar el flujo completo, use el botón "Simular Pago Exitoso" o implemente la Fase 2 con datos reales.',
                    ], 400);
                }

                // Otros códigos de error
                $message = $result['message'] ?? 'Pago no completado';

                // ✅ FIXED: Auto-detectar simulaciones basadas en código 000.200.000
                if ($resultCode === '000.200.000') {
                    Log::info('Datafast: Código 000.200.000 detectado - analizando contexto', [
                        'result_code' => $resultCode,
                        'transaction_id' => $validated['transaction_id'],
                        'datafast_payment_id' => $datafastPayment->id,
                        'environment' => config('app.env'),
                    ]);

                    // En desarrollo, 000.200.000 generalmente significa checkout sin transacción real
                    // Lo cual es el comportamiento del botón de prueba, así que simulamos éxito
                    if (config('app.env') !== 'production') {
                        Log::info('Datafast: Auto-simulando éxito para 000.200.000 en desarrollo');

                        // Usar la misma lógica de simulación exitosa
                        $cart = $this->cartRepository->findByUserId($user->id);

                        $totalToUse = $validated['calculated_total'] ?? $cart->getTotal() ?? 1.0;

                        $simulatedResult = [
                            'success' => true,
                            'payment_id' => 'SIMULATED_'.time().'_'.uniqid(),
                            'status' => 'completed',
                            'result_code' => '000.100.110',
                            'message' => 'Pago simulado exitosamente (auto-detectado)',
                            'amount' => $totalToUse,
                            'currency' => 'USD',
                            'total' => $totalToUse,
                        ];

                        Log::info('Datafast: Auto-simulación activada para transacción pendiente', [
                            'original_code' => $resultCode,
                            'simulated_result' => $simulatedResult,
                            'calculated_total_used' => $totalToUse,
                            'cart_total' => $cart->getTotal(),
                        ]);

                        return $this->createOrderFromSuccessfulPayment($request, $simulatedResult, $validated, $datafastPayment);
                    }

                    // En producción, mantener el comportamiento actual (transacción realmente pendiente)
                    $message = 'La transacción está pendiente de procesamiento';

                    $datafastPayment->update([
                        'result_code' => $resultCode,
                        'result_description' => $message,
                        'status' => 'pending',
                    ]);

                    Log::info('Datafast: Transacción pendiente en producción', [
                        'result_code' => $resultCode,
                        'transaction_id' => $validated['transaction_id'],
                        'datafast_payment_id' => $datafastPayment->id,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $message,
                        'result_code' => $resultCode,
                        'status' => 'pending',
                        'transaction_id' => $validated['transaction_id'],
                    ], 202);
                } elseif ($resultCode === '000.200.100') {
                    $message = 'El checkout fue creado exitosamente pero no se completó el pago. Por favor, complete el formulario de pago.';
                } elseif ($resultCode && str_starts_with($resultCode, '800')) {
                    $message = 'El pago fue rechazado. Por favor, verifique sus datos e intente nuevamente.';
                }

                // ✅ MARCAR COMO FALLIDO EN BD
                $datafastPayment->markAsFailed($message, $resultCode);

                Log::warning('Datafast: Pago no exitoso', [
                    'result' => $result,
                    'transaction_id' => $validated['transaction_id'],
                    'datafast_payment_id' => $datafastPayment->id,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'result_code' => $resultCode,
                ], 400);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Datafast: Error de validación en verificación', [
                'errors' => $e->errors(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
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

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el pago: '.$e->getMessage(),
                'debug_error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Crear orden a partir de un pago exitoso usando ProcessCheckoutUseCase
     */
    private function createOrderFromSuccessfulPayment(Request $request, array $result, array $validated, ?DatafastPayment $datafastPayment = null): \Illuminate\Http\JsonResponse
    {
        try {
            $user = $request->user();

            // Obtener carrito
            $cart = $this->cartRepository->findByUserId($user->id);

            if (! $cart || count($cart->getItems()) === 0) {
                throw new \Exception('El carrito está vacío');
            }

            // ✅ USAR EL TOTAL CALCULADO ALMACENADO EN DATAFAST_PAYMENT (DATOS ORIGINALES DEL FRONTEND)
            $calculatedTotal = $datafastPayment->calculated_total ?? $validated['calculated_total'] ?? $result['amount'] ?? $cart->getTotal();

            Log::info('✅ DATAFAST: Usando ProcessCheckoutUseCase para cálculos centralizados', [
                'user_id' => $user->id,
                'transaction_id' => $validated['transaction_id'],
                'payment_id' => $result['payment_id'],
                'calculated_total' => $calculatedTotal,
                'cart_total' => $cart->getTotal(),
                'result_amount' => $result['amount'] ?? 'N/A',
            ]);

            // Preparar items del carrito para ProcessCheckoutUseCase
            $cartItems = [];
            foreach ($cart->getItems() as $item) {
                $cartItems[] = [
                    'product_id' => $item->getProductId(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                    'subtotal' => $item->getSubtotal(),
                ];
            }

            // Preparar datos de pago
            $paymentData = [
                'method' => 'datafast',
                'transaction_id' => $validated['transaction_id'],
                'payment_id' => $result['payment_id'],
                'status' => 'completed',
                'amount' => $calculatedTotal,
                'skip_price_verification' => true, // ✅ Saltarse verificación de precios para Datafast
            ];

            // 🚨 CRÍTICO: Verificar que $datafastPayment existe antes de usar sus propiedades
            if (!$datafastPayment) {
                throw new \Exception('No se encontraron datos de pago de Datafast para crear la orden');
            }

            // Preparar datos de envío usando datos reales del usuario
            $originalRequestData = $datafastPayment->request_data ?? [];
            $originalShippingData = $originalRequestData['shipping_data'] ?? [];

            $shippingData = [
                'name' => $originalShippingData['name'] ?? ($datafastPayment->customer_given_name . ' ' . $datafastPayment->customer_surname), // Nombre completo del receptor
                'street' => $originalShippingData['address'] ?? $datafastPayment->shipping_address, // Fix: usar 'street' en lugar de 'address'
                'city' => $datafastPayment->shipping_city,
                'state' => $datafastPayment->shipping_city, // Usar city como state ya que Ecuador no maneja estados
                'country' => $datafastPayment->shipping_country,
                'postal_code' => $originalShippingData['postal_code'] ?? null, // Usar datos originales del frontend
                'phone' => $datafastPayment->customer_phone,
                'identification' => $datafastPayment->shipping_identification ?? $datafastPayment->customer_doc_id, // ✅ DATO CRÍTICO PARA SRI
            ];

            // Preparar datos de facturación (para mayoría de casos, billing = shipping en Ecuador)
            $billingData = [
                'name' => $originalShippingData['name'] ?? ($datafastPayment->customer_given_name . ' ' . $datafastPayment->customer_surname), // Nombre completo del receptor
                'street' => $originalShippingData['address'] ?? $datafastPayment->shipping_address, // Fix: usar 'street' en lugar de 'address'
                'city' => $datafastPayment->shipping_city,
                'state' => $datafastPayment->shipping_city, // Ecuador no usa states
                'country' => $datafastPayment->shipping_country,
                'postal_code' => $originalShippingData['postal_code'] ?? null, // Usar datos originales del frontend
                'phone' => $datafastPayment->customer_phone,
                'identification' => $datafastPayment->shipping_identification ?? $datafastPayment->customer_doc_id,
            ];

            // 💳 LOG: Preparar billing data para Datafast
            Log::info('💳 DATAFAST: Preparando billing data', [
                'datafast_payment_id' => $datafastPayment->id,
                'shipping_address' => $datafastPayment->shipping_address,
                'billing_constructed' => $billingData,
                'same_as_shipping' => $billingData === $shippingData,
                'billing_identification' => $billingData['identification']
            ]);

            // Calcular subtotal desde los items del carrito
            $cartSubtotal = 0;
            foreach ($cart->getItems() as $item) {
                $cartSubtotal += $item->getSubtotal();
            }

            // ✅ USAR TOTALES CON NOMBRES CORRECTOS PARA PriceVerificationService
            $calculatedTotals = [
                'final_total' => $datafastPayment->calculated_total ?? $calculatedTotal,
                'subtotal_with_discounts' => $datafastPayment->subtotal ?? $cartSubtotal,
                'iva_amount' => $datafastPayment->tax ?? 0,
                'shipping_cost' => $datafastPayment->shipping_cost ?? 0,
            ];

            // ✅ $billingData ya está definido arriba con datos reales del frontend

            // ✅ USAR ProcessCheckoutUseCase PARA CÁLCULOS CENTRALIZADOS
            try {
                // Usar el use case centralizado que maneja todos los cálculos correctamente
                $checkoutResult = app(\App\UseCases\Checkout\ProcessCheckoutUseCase::class)->execute(
                    $user->id,
                    $paymentData,
                    $shippingData,
                    $billingData,
                    $cartItems,
                    null, // seller_id se detecta automáticamente
                    null, // discount_code
                    $calculatedTotals
                );

                Log::info('✅ DATAFAST: ProcessCheckoutUseCase ejecutado exitosamente', [
                    'order_id' => $checkoutResult['order']->getId(),
                    'order_number' => $checkoutResult['order']->getOrderNumber(),
                    'total' => $checkoutResult['order']->getTotal(),
                    'seller_orders_created' => count($checkoutResult['seller_orders'] ?? []),
                ]);

                // Usar los resultados del ProcessCheckoutUseCase
                $order = $checkoutResult['order'];

            } catch (\Exception $checkoutError) {
                Log::error('❌ DATAFAST: ProcessCheckoutUseCase falló - NO se creará orden', [
                    'error' => $checkoutError->getMessage(),
                    'user_id' => $user->id,
                    'transaction_id' => $validated['transaction_id'],
                ]);

                // ❌ NO CREAR ORDEN SI HAY PROBLEMAS DE SEGURIDAD
                // Marcar el pago de Datafast como fallido
                if ($datafastPayment) {
                    $datafastPayment->markAsFailed(
                        'Error en validación: '.$checkoutError->getMessage(),
                        'validation_failed'
                    );
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Error en validación del pago: '.$checkoutError->getMessage(),
                    'error_type' => 'validation_error',
                ], 400);
            }

            // ✅ ACTUALIZAR REGISTRO DATAFAST CON ORDEN COMPLETADA
            if ($datafastPayment) {
                $datafastPayment->markAsCompleted(
                    $result['payment_id'] ?? null,
                    $result['result_code'] ?? 'completed',
                    'Orden creada exitosamente'
                );

                // Vincular orden con transacción Datafast
                $datafastPayment->update(['order_id' => $order->getId()]);

                Log::info('Datafast: Transacción completada y vinculada a orden', [
                    'datafast_payment_id' => $datafastPayment->id,
                    'order_id' => $order->getId(),
                    'transaction_id' => $validated['transaction_id'],
                    'payment_id' => $result['payment_id'] ?? null,
                ]);
            }

            // Actualizar con información de pago de Datafast
            $this->orderRepository->updatePaymentInfo($order->getId(), [
                'payment_id' => $result['payment_id'],
                'payment_status' => 'completed',
                'payment_method' => 'datafast',
                'status' => 'processing',
                'datafast_transaction_id' => $validated['transaction_id'],
                'datafast_result_code' => $result['result_code'] ?? 'simulated',
            ]);

            // Actualizar stock de productos
            foreach ($cart->getItems() as $item) {
                try {
                    $this->productRepository->updateStock(
                        $item->getProductId(),
                        $item->getQuantity(),
                        'decrease'
                    );
                } catch (\Exception $e) {
                    Log::warning('Error al actualizar stock del producto', [
                        'product_id' => $item->getProductId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Limpiar carrito
            $this->cartRepository->clearCart($cart->getId());

            Log::info('Datafast: Orden creada exitosamente', [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'status' => 'success', // ✅ CORREGIDO: Cambiar 'success' por 'status' para consistencia
                'data' => [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'total' => $order->getTotal(),
                    'payment_status' => 'completed',
                    'payment_id' => $result['payment_id'],
                ],
                'message' => 'Pago procesado exitosamente',
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Error en createOrderFromSuccessfulPayment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook para recibir notificaciones de Datafast (si es necesario)
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('Datafast Webhook recibido', $request->all());

            // Procesar webhook según documentación de Datafast
            // (Este endpoint se usaría si Datafast envía notificaciones)

            return response()->json(['status' => 'received']);
        } catch (\Exception $e) {
            Log::error('Error en webhook de Datafast', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    /**
     * Método fallback para crear orden directamente (sin ProcessCheckoutUseCase)
     * Solo se usa si ProcessCheckoutUseCase falla por conflictos de transacción
     */
    private function createOrderDirectly($cart, $user, $calculatedTotal, $paymentData, $shippingData)
    {
        Log::info('🔄 DATAFAST: Creando orden con método fallback directo', [
            'user_id' => $user->id,
            'calculated_total' => $calculatedTotal,
            'cart_items' => count($cart->getItems()),
        ]);

        // ✅ CRÍTICO: Usar PricingCalculatorService para cálculos correctos
        $cartItems = [];
        foreach ($cart->getItems() as $item) {
            $cartItems[] = [
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
            ];
        }

        // Calcular pricing breakdown usando el servicio centralizado
        $pricingService = app(\App\Domain\Services\PricingCalculatorService::class);
        $pricingResult = $pricingService->calculateCartTotals($cartItems, $user->id, null);

        Log::info('✅ DATAFAST FALLBACK: PricingCalculatorService calculó breakdowns', [
            'subtotal_original' => $pricingResult['subtotal_original'],
            'subtotal_with_discounts' => $pricingResult['subtotal_with_discounts'],
            'seller_discounts' => $pricingResult['seller_discounts'],
            'volume_discounts' => $pricingResult['volume_discounts'],
            'shipping_cost' => $pricingResult['shipping_cost'],
            'iva_amount' => $pricingResult['iva_amount'],
            'final_total' => $pricingResult['final_total'],
        ]);

        // Preparar datos de la orden con breakdowns correctos
        $orderItems = [];
        foreach ($cart->getItems() as $item) {
            $product = $this->productRepository->findById($item->getProductId());
            $sellerId = $product ? $product->getSellerId() : null;

            $orderItems[] = [
                'product_id' => $item->getProductId(),
                'quantity' => $item->getQuantity(),
                'price' => $item->getPrice(),
                'subtotal' => $item->getSubtotal(),
                'seller_id' => $sellerId,
            ];
        }

        // ✅ CRÍTICO: Crear OrderEntity con todos los campos de pricing en orden correcto
        $order = OrderEntity::create(
            $user->id,
            null, // seller_id (se maneja por items)
            $orderItems,
            $pricingResult['final_total'], // total
            'processing', // status
            $shippingData,
            $pricingResult['subtotal_original'], // original_total
            $pricingResult['volume_discounts'], // volume_discount_savings
            false, // volume_discounts_applied
            $pricingResult['seller_discounts'], // seller_discount_savings
            $pricingResult['subtotal_with_discounts'], // subtotal_products
            $pricingResult['iva_amount'], // iva_amount
            $pricingResult['shipping_cost'], // shipping_cost
            $pricingResult['total_discounts'], // total_discounts
            $pricingResult['free_shipping'], // free_shipping
            null, // free_shipping_threshold
            null // pricing_breakdown
        );

        // Guardar orden sin transacciones
        $order = $this->orderRepository->saveWithoutTransaction($order);

        Log::info('✅ DATAFAST FALLBACK: Orden creada con breakdowns correctos', [
            'order_id' => $order->getId(),
            'original_total' => $order->getOriginalTotal(),
            'subtotal_products' => $order->getSubtotalProducts(),
            'iva_amount' => $order->getIvaAmount(),
            'shipping_cost' => $order->getShippingCost(),
            'total_discounts' => $order->getTotalDiscounts(),
            'final_total' => $order->getTotal(),
        ]);

        // Crear seller_orders manualmente
        $this->createSellerOrdersForOrder($order, $cart->getItems(), $calculatedTotal);

        // ✅ CRÍTICO: Disparar evento OrderCreated para generación de facturas
        Log::info('🚀 DATAFAST FALLBACK: Disparando evento OrderCreated para generación de facturas', [
            'order_id' => $order->getId(),
            'user_id' => $order->getUserId(),
            'seller_id' => $order->getSellerId(),
        ]);

        event(new \App\Events\OrderCreated(
            $order->getId(),
            $order->getUserId(),
            $order->getSellerId(),
            ['method' => 'datafast_fallback']
        ));

        Log::info('✅ DATAFAST FALLBACK: Evento OrderCreated disparado');

        return $order;
    }

    /**
     * Crear seller_orders para una orden (usado en fallback)
     */
    private function createSellerOrdersForOrder($order, $cartItems, $calculatedTotal)
    {
        try {
            Log::info('🛒 DATAFAST: Creando seller_orders para orden fallback', [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'items_count' => count($cartItems),
                'calculated_total' => $calculatedTotal,
            ]);

            // Agrupar items por seller_id
            $itemsBySeller = [];
            foreach ($cartItems as $item) {
                $product = $this->productRepository->findById($item->getProductId());
                if ($product) {
                    $sellerId = $product->getSellerId();
                    if (! isset($itemsBySeller[$sellerId])) {
                        $itemsBySeller[$sellerId] = [];
                    }
                    $itemsBySeller[$sellerId][] = [
                        'item' => $item,
                        'product' => $product,
                    ];
                }
            }

            // Distribuir totales entre sellers (proporcional a sus items)
            $totalItemsValue = array_sum(array_map(function ($items) {
                return array_sum(array_map(fn ($i) => $i['item']->getSubtotal(), $items));
            }, $itemsBySeller));

            // Crear seller_order para cada seller
            foreach ($itemsBySeller as $sellerId => $sellerItems) {
                $sellerSubtotal = 0;
                $originalTotal = 0;

                foreach ($sellerItems as $sellerItem) {
                    $sellerSubtotal += $sellerItem['item']->getSubtotal();
                    $originalTotal += $sellerItem['product']->getPrice() * $sellerItem['item']->getQuantity();
                }

                // Calcular proporción de costos adicionales (envío + IVA)
                $proportion = $totalItemsValue > 0 ? ($sellerSubtotal / $totalItemsValue) : 1;
                $additionalCosts = $calculatedTotal - $totalItemsValue; // Envío + IVA
                $sellerShipping = $additionalCosts * $proportion * 0.85; // ~85% es envío
                $sellerIVA = $additionalCosts * $proportion * 0.15; // ~15% es IVA
                $sellerTotal = $sellerSubtotal + $sellerShipping + $sellerIVA;

                // ✅ CRÍTICO: Crear seller_order con distribución correcta de costos
                $sellerOrder = \App\Models\SellerOrder::create([
                    'order_id' => $order->getId(),
                    'seller_id' => $sellerId,
                    'order_number' => $order->getOrderNumber().'-S'.$sellerId,
                    'status' => 'processing',
                    'total' => round($sellerTotal, 2),
                    'original_total' => $originalTotal,
                    'subtotal_products' => $sellerSubtotal,
                    'subtotal' => $sellerSubtotal,
                    'shipping_cost' => round($sellerShipping, 2),
                    'iva_amount' => round($sellerIVA, 2),
                    'total_discounts' => $originalTotal - $sellerSubtotal,
                    'payment_status' => 'completed',
                    'payment_method' => 'datafast',
                    'shipping_data' => $order->getShippingData(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Actualizar claves foráneas
                \App\Models\Order::where('id', $order->getId())
                    ->update(['seller_order_id' => $sellerOrder->id]);

                \App\Models\OrderItem::where('order_id', $order->getId())
                    ->where('seller_id', $sellerId)
                    ->update(['seller_order_id' => $sellerOrder->id]);

                Log::info('✅ DATAFAST: Seller order fallback creado', [
                    'seller_order_id' => $sellerOrder->id,
                    'seller_id' => $sellerId,
                    'subtotal' => $sellerSubtotal,
                    'shipping' => round($sellerShipping, 2),
                    'iva' => round($sellerIVA, 2),
                    'total' => round($sellerTotal, 2),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ DATAFAST: Error creando seller_orders fallback', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
