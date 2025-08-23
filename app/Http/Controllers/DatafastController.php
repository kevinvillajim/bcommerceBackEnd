<?php

namespace App\Http\Controllers;

use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Domain\Repositories\ShoppingCartRepositoryInterface;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use App\Models\DatafastPayment;
use App\UseCases\Order\CreateOrderUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

            // Validar datos requeridos
            $validated = $request->validate([
                'shipping' => 'required|array',
                'shipping.address' => 'required|string|max:100',
                'shipping.city' => 'required|string|max:50',
                'shipping.country' => 'required|string|size:2',
                'customer' => 'sometimes|array',
                'customer.given_name' => 'sometimes|string|max:48',
                'customer.middle_name' => 'sometimes|string|max:50',
                'customer.surname' => 'sometimes|string|max:48',
                'customer.phone' => 'sometimes|string|min:7|max:25',
                'customer.doc_id' => 'sometimes|string|size:10',
                'total' => 'required|numeric|min:0.01',
                'subtotal' => 'sometimes|numeric|min:0',
                'shipping_cost' => 'sometimes|numeric|min:0',
                'tax' => 'sometimes|numeric|min:0',
                'items' => 'sometimes|array',
                'discount_code' => 'sometimes|string|nullable',
                'discount_info' => 'sometimes|array|nullable',
            ]);

            // Obtener carrito del usuario
            $cart = $this->cartRepository->findByUserId($user->id);

            if (! $cart || count($cart->getItems()) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El carrito está vacío',
                ], 400);
            }

            // Usar el total calculado que viene del frontend (con descuentos, envío e IVA)
            $calculatedTotal = $validated['total'];

            Log::info('Datafast: Creando checkout para usuario', [
                'user_id' => $user->id,
                'items_count' => count($cart->getItems()),
                'cart_total' => $cart->getTotal(),
                'calculated_total' => $calculatedTotal,
                'frontend_data' => [
                    'subtotal' => $validated['subtotal'] ?? null,
                    'shipping_cost' => $validated['shipping_cost'] ?? null,
                    'tax' => $validated['tax'] ?? null,
                ],
            ]);

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

                // Información de envío
                'shipping_address' => $validated['shipping']['address'],
                'shipping_city' => $validated['shipping']['city'],
                'shipping_country' => strtoupper($validated['shipping']['country']),

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
                    'address' => $validated['shipping']['address'],
                    'country' => strtoupper($validated['shipping']['country']),
                ],
                'billing' => [
                    'address' => $validated['shipping']['address'], // Usar misma dirección
                    'country' => strtoupper($validated['shipping']['country']),
                ],
                'items' => [],
            ];

            // Agregar items del carrito
            foreach ($cart->getItems() as $item) {
                Log::info('Procesando item del carrito', [
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
                    'success' => true,
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

                // Verificar que el usuario tenga un carrito
                $cart = $this->cartRepository->findByUserId($user->id);
                if (! $cart || count($cart->getItems()) === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El carrito está vacío para procesar la simulación',
                    ], 400);
                }

                // ✅ USAR TOTAL CALCULADO SI ESTÁ DISPONIBLE
                $totalToUse = $validated['calculated_total'] ?? $cart->getTotal();

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

                if ($resultCode === '000.200.100') {
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
     * Crear orden a partir de un pago exitoso
     */
    private function createOrderFromSuccessfulPayment(Request $request, array $result, array $validated, ?DatafastPayment $datafastPayment = null): \Illuminate\Http\JsonResponse
    {
        return DB::transaction(function () use ($request, $result, $validated, $datafastPayment) {
            $user = $request->user();

            // Obtener carrito
            $cart = $this->cartRepository->findByUserId($user->id);

            if (! $cart || count($cart->getItems()) === 0) {
                throw new \Exception('El carrito está vacío');
            }

            // ✅ USAR EL TOTAL CALCULADO QUE VIENE DEL FRONTEND (CON DESCUENTOS, ENVÍO E IVA)
            $calculatedTotal = $validated['calculated_total'] ?? $result['amount'] ?? $cart->getTotal();

            Log::info('Datafast: Creando orden después de pago exitoso', [
                'user_id' => $user->id,
                'transaction_id' => $validated['transaction_id'],
                'payment_id' => $result['payment_id'],
                'calculated_total' => $calculatedTotal,
                'cart_total' => $cart->getTotal(),
                'result_amount' => $result['amount'] ?? 'N/A',
            ]);

            // Crear orden
            $orderData = [
                'user_id' => $user->id,
                'total' => $calculatedTotal, // ✅ USAR TOTAL CALCULADO CORRECTO
                'shipping_data' => [
                    'address' => 'Dirección del checkout de Datafast',
                    'city' => 'Ciudad',
                    'state' => 'Estado',
                    'country' => 'EC',
                    'postal_code' => '00000',
                    'phone' => '0999999999',
                ],
                'items' => [],
            ];

            // Agregar items
            foreach ($cart->getItems() as $item) {
                $orderData['items'][] = [
                    'product_id' => $item->getProductId(),
                    'quantity' => $item->getQuantity(),
                    'price' => $item->getPrice(),
                    'subtotal' => $item->getSubtotal(),
                ];
            }

            // Crear orden
            $order = $this->createOrderUseCase->execute($orderData);

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
                'success' => true,
                'data' => [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getOrderNumber(),
                    'total' => $order->getTotal(),
                    'payment_status' => 'completed',
                    'payment_id' => $result['payment_id'],
                ],
                'message' => 'Pago procesado exitosamente',
            ]);
        });
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
}
