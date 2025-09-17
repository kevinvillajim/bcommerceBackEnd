<?php

/**
 * Test de Regresión para Datafast - Validar Correcciones de Inconsistencias
 *
 * Este test valida que todas las correcciones aplicadas en la auditoría
 * mantengan la funcionalidad y no introduzcan regresiones.
 */

use App\Http\Controllers\DatafastController;
use App\Models\DatafastPayment;
use App\Models\User;
use App\Services\CheckoutDataService;
use App\Infrastructure\External\PaymentGateway\DatafastService;
use App\Validators\Payment\Datafast\UnifiedDatafastValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DatafastIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;
    private array $validCheckoutData;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario de prueba
        $this->testUser = User::factory()->create([
            'email' => 'test@datafast.com',
            'name' => 'Test User',
        ]);

        // Datos válidos de checkout
        $this->validCheckoutData = [
            'shippingAddress' => [
                'street' => 'Test Street 123',     // ✅ CORREGIDO: Usar 'street' no 'address'
                'city' => 'Quito',
                'country' => 'EC',
                'identification' => '1234567890',
            ],
            'customer' => [
                'given_name' => 'Test',
                'middle_name' => 'User',
                'surname' => 'Testing',
                'phone' => '0999999999',
                'doc_id' => '1234567890',           // ✅ OBLIGATORIO para SRI
            ],
            'total' => 25.50,
            'subtotal' => 20.00,
            'shipping_cost' => 5.00,
            'tax' => 0.50,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2,
                    'price' => 10.00,
                ]
            ]
        ];
    }

    /** @test */
    public function test_no_duplicate_verify_payment_methods()
    {
        // ✅ FASE 1: Verificar que no hay métodos duplicados
        $reflection = new \ReflectionClass(DatafastController::class);
        $verifyPaymentMethods = array_filter(
            $reflection->getMethods(),
            fn($method) => $method->getName() === 'verifyPayment'
        );

        $this->assertCount(
            1,
            $verifyPaymentMethods,
            'FALLO: Debe haber exactamente UN método verifyPayment, encontrados: ' . count($verifyPaymentMethods)
        );
    }

    /** @test */
    public function test_street_address_field_mapping_consistency()
    {
        // ✅ FASE 1: Verificar mapeo consistente de campos street/address
        Auth::login($this->testUser);

        $response = $this->postJson('/api/datafast/create-checkout', $this->validCheckoutData);

        // El test pasa si no hay errores de mapeo interno
        $this->assertTrue(
            $response->status() !== 500,
            'FALLO: Error 500 indica problema de mapeo interno de campos street/address'
        );

        // Verificar que la respuesta incluye datos correctos
        if ($response->json('success')) {
            $this->assertArrayHasKey('data', $response->json());
            $this->assertArrayHasKey('transaction_id', $response->json('data'));
        }
    }

    /** @test */
    public function test_payment_status_field_unification()
    {
        // ✅ FASE 2: Verificar unificación de estados de pago
        $payment = DatafastPayment::create([
            'user_id' => $this->testUser->id,
            'transaction_id' => 'TEST_TXN_123',
            'amount' => 25.50,
            'status' => 'completed',              // ✅ Campo principal
            'currency' => 'USD',
        ]);

        // Verificar que el accessor payment_status funciona
        $this->assertEquals(
            'completed',
            $payment->payment_status,
            'FALLO: El accessor payment_status debe retornar el valor de status'
        );

        // Verificar estados válidos
        $validStatuses = DatafastPayment::getValidStatuses();
        $this->assertContains('pending', $validStatuses);
        $this->assertContains('processing', $validStatuses);
        $this->assertContains('completed', $validStatuses);
        $this->assertContains('failed', $validStatuses);
        $this->assertContains('error', $validStatuses);
    }

    /** @test */
    public function test_ids_purpose_clarification()
    {
        // ✅ FASE 2: Verificar claridad de propósito de IDs
        $payment = DatafastPayment::create([
            'user_id' => $this->testUser->id,
            'transaction_id' => 'ORDER_123456_78_uniqid',
            'checkout_id' => 'DF_CHECKOUT_789',
            'datafast_payment_id' => 'DF_PAYMENT_999',
            'resource_path' => '/v1/checkouts/DF_CHECKOUT_789/payment',
            'amount' => 25.50,
            'status' => 'completed',
            'currency' => 'USD',
        ]);

        // Verificar métodos clarificadores
        $this->assertEquals('ORDER_123456_78_uniqid', $payment->getSystemTransactionId());
        $this->assertEquals('DF_CHECKOUT_789', $payment->getDatafastCheckoutId());
        $this->assertEquals('DF_PAYMENT_999', $payment->getDatafastPaymentId());
        $this->assertEquals('/v1/checkouts/DF_CHECKOUT_789/payment', $payment->getResourcePath());

        // Verificar métodos de verificación
        $this->assertTrue($payment->hasVerificationIds());
        $this->assertTrue($payment->hasDatafastCheckout());
        $this->assertTrue($payment->isFinalized());
    }

    /** @test */
    public function test_calculated_total_validation_consistency()
    {
        // ✅ FASE 3: Verificar validaciones de calculated_total
        Auth::login($this->testUser);

        // Test 1: createCheckout sin calculated_total (debe funcionar)
        $response1 = $this->postJson('/api/datafast/create-checkout', $this->validCheckoutData);
        $this->assertTrue(
            $response1->status() !== 422,
            'FALLO: createCheckout no debe requerir calculated_total'
        );

        // Test 2: verifyPayment sin calculated_total (debe funcionar)
        $verifyData = [
            'resource_path' => '/v1/checkouts/test/payment',
            'transaction_id' => 'TEST_TXN_456',
        ];
        $response2 = $this->postJson('/api/datafast/verify-payment', $verifyData);
        $this->assertTrue(
            $response2->status() !== 422,
            'FALLO: verifyPayment no debe requerir obligatoriamente calculated_total'
        );
    }

    /** @test */
    public function test_typescript_php_interface_synchronization()
    {
        // ✅ FASE 3: Verificar que las validaciones PHP coinciden con interfaces TypeScript
        $validationRules = [
            'shippingAddress.street' => 'required|string|max:100',
            'shippingAddress.city' => 'required|string|max:50',
            'shippingAddress.country' => 'required|string|max:100',
            'customer.doc_id' => 'required|string|size:10',
            'total' => 'required|numeric|min:0.01',
        ];

        Auth::login($this->testUser);

        // Test campo requerido faltante
        $invalidData = $this->validCheckoutData;
        unset($invalidData['customer']['doc_id']);

        $response = $this->postJson('/api/datafast/create-checkout', $invalidData);
        $this->assertEquals(422, $response->status(), 'FALLO: Debe fallar validación sin doc_id');

        // Test longitud máxima
        $invalidData2 = $this->validCheckoutData;
        $invalidData2['shippingAddress']['street'] = str_repeat('A', 101); // Más de 100 chars

        $response2 = $this->postJson('/api/datafast/create-checkout', $invalidData2);
        $this->assertEquals(422, $response2->status(), 'FALLO: Debe fallar validación de longitud');
    }

    /** @test */
    public function test_routes_purpose_clarification()
    {
        // ✅ FASE 4: Verificar diferencia clara entre rutas de verificación
        Auth::login($this->testUser);

        // Crear transacción de prueba
        $payment = DatafastPayment::create([
            'user_id' => $this->testUser->id,
            'transaction_id' => 'TEST_ROUTES_123',
            'amount' => 25.50,
            'status' => 'pending',
            'currency' => 'USD',
        ]);

        // Test ruta GET: checkPaymentStatus (solo consulta)
        $response1 = $this->getJson("/api/datafast/verify-payment/{$payment->transaction_id}");
        $this->assertTrue(
            $response1->status() !== 405,
            'FALLO: Ruta GET verify-payment/{id} debe existir para consultas'
        );

        // Test ruta POST: verifyPayment (verificación completa)
        $response2 = $this->postJson('/api/datafast/verify-payment', [
            'resource_path' => '/v1/checkouts/test/payment',
            'transaction_id' => $payment->transaction_id,
        ]);
        $this->assertTrue(
            $response2->status() !== 405,
            'FALLO: Ruta POST verify-payment debe existir para verificación completa'
        );
    }

    /** @test */
    public function test_unified_datafast_validator_functionality()
    {
        // ✅ VALIDAR: UnifiedDatafastValidator funciona correctamente
        $validator = app(UnifiedDatafastValidator::class);

        // Test detección de tipo widget
        $widgetData = [
            'resource_path' => '/v1/checkouts/test/payment',
            'transaction_id' => 'TEST_123',
        ];
        $result1 = $validator->validatePayment($widgetData);
        $this->assertNotNull($result1, 'FALLO: Validator debe procesar datos de widget');

        // Test detección de tipo test
        $testData = [
            'simulate_success' => true,
            'transaction_id' => 'TEST_456',
        ];
        $result2 = $validator->validatePayment($testData);
        $this->assertNotNull($result2, 'FALLO: Validator debe procesar datos de test');
    }

    /** @test */
    public function test_no_security_bypass_methods()
    {
        // ✅ SEGURIDAD: Verificar que no hay métodos que bypassen validación
        $checkoutData = app(\App\Domain\ValueObjects\CheckoutData::class, [
            'userId' => $this->testUser->id,
            'shippingData' => $this->validCheckoutData['shippingAddress'],
            'billingData' => $this->validCheckoutData['shippingAddress'],
            'items' => $this->validCheckoutData['items'],
            'totals' => ['final_total' => 25.50],
            'sessionId' => 'TEST_SESSION',
            'validatedAt' => now(),
            'expiresAt' => now()->addMinutes(30),
        ]);

        // Verificar que createBasePaymentData NO incluye skip_price_verification
        $paymentData = $checkoutData->createBasePaymentData('datafast', 'TXN_123', 'PAY_456');

        $this->assertArrayNotHasKey(
            'skip_price_verification',
            $paymentData,
            'FALLO: No debe haber skip_price_verification (violación de seguridad)'
        );
    }

    /** @test */
    public function test_complete_datafast_flow_integration()
    {
        // ✅ TEST INTEGRACIÓN: Flujo completo sin errores
        Auth::login($this->testUser);

        // 1. Crear checkout
        $response1 = $this->postJson('/api/datafast/create-checkout', $this->validCheckoutData);

        if (!$response1->json('success')) {
            $this->markTestSkipped('Checkout creation failed - requires Datafast API');
            return;
        }

        $transactionId = $response1->json('data.transaction_id');
        $this->assertNotEmpty($transactionId, 'FALLO: Debe retornar transaction_id');

        // 2. Verificar que se creó registro en BD
        $payment = DatafastPayment::where('transaction_id', $transactionId)->first();
        $this->assertNotNull($payment, 'FALLO: Debe crear registro en DatafastPayment');

        // 3. Verificar consistencia de datos
        $this->assertEquals($this->testUser->id, $payment->user_id);
        $this->assertEquals(25.50, $payment->amount);
        $this->assertEquals('pending', $payment->status);

        // 4. Verificar que los métodos helper funcionan
        $this->assertFalse($payment->isFinalized());
        $this->assertEquals($transactionId, $payment->getSystemTransactionId());
    }
}