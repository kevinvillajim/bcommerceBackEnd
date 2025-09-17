<?php

/**
 * Test de Contratos de Datos - Datafast
 *
 * Valida que los datos enviados por el frontend coincidan exactamente
 * con lo que espera el backend, incluyendo tipos, estructuras y formatos.
 *
 * PROPÓSITO: Prevenir errores de tipo y estructura entre frontend/backend
 */

use App\Http\Controllers\DatafastController;
use App\Models\DatafastPayment;
use App\Models\User;
use App\Validators\Payment\Datafast\UnifiedDatafastValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DatafastDataContractTest extends TestCase
{
    use RefreshDatabase;

    private User $testUser;
    private array $validStoreCheckoutData;
    private array $validCreateCheckoutData;
    private array $validVerifyPaymentData;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario de prueba
        $this->testUser = User::factory()->create([
            'email' => 'datacontract@test.com',
            'name' => 'Data Contract Test User',
        ]);

        // ✅ DATOS TIPADOS SEGÚN INTERFACES TYPESCRIPT
        $this->validStoreCheckoutData = [
            'shippingData' => [
                'street' => 'Test Street 123',
                'city' => 'Quito',
                'country' => 'EC',
                'identification' => '1234567890',
            ],
            'billingData' => [
                'street' => 'Test Street 123',
                'city' => 'Quito',
                'country' => 'EC',
                'identification' => '1234567890',
            ],
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2,
                    'price' => 10.50,
                    'name' => 'Producto Test',
                    'subtotal' => 21.00,
                ]
            ],
            'totals' => [
                'subtotal' => 21.00,
                'shipping_cost' => 5.00,
                'tax' => 3.90,
                'discount' => 0.00,
                'final_total' => 29.90,
            ],
            'sessionId' => 'test_session_' . uniqid(),
            'discountCode' => null,
            'discountInfo' => [],
        ];

        $this->validCreateCheckoutData = [
            'shippingAddress' => [
                'street' => 'Test Street 123',       // ✅ CAMPO UNIFICADO
                'city' => 'Quito',
                'country' => 'EC',
                'identification' => '1234567890',
            ],
            'customer' => [
                'given_name' => 'Test',
                'middle_name' => 'User',
                'surname' => 'Testing',
                'phone' => '0999999999',
                'doc_id' => '1234567890',             // ✅ OBLIGATORIO PARA SRI
            ],
            'total' => 29.90,                        // ✅ NUMERIC TYPE
            'subtotal' => 21.00,
            'shipping_cost' => 5.00,
            'tax' => 3.90,
            'items' => [
                [
                    'product_id' => 1,
                    'quantity' => 2,
                    'price' => 10.50,
                ]
            ],
        ];

        $this->validVerifyPaymentData = [
            'resource_path' => '/v1/checkouts/test_checkout/payment',
            'transaction_id' => 'ORDER_' . time() . '_' . $this->testUser->id . '_' . uniqid(),
            'calculated_total' => 29.90,            // ✅ NUMERIC TYPE
            'session_id' => 'test_session_' . uniqid(),
            'simulate_success' => true,              // ✅ PARA TESTS
        ];
    }

    /** @test */
    public function test_store_checkout_data_request_contract()
    {
        // ✅ VALIDAR: Request coincide con StoreCheckoutDataRequest TypeScript
        Auth::login($this->testUser);

        $response = $this->postJson('/api/datafast/store-checkout-data', $this->validStoreCheckoutData);

        // Verificar estructura de response coincide con StoreCheckoutDataResponse
        $responseData = $response->json();

        $this->assertArrayHasKey('success', $responseData, 'Response debe tener campo success boolean');
        $this->assertArrayHasKey('status', $responseData, 'Response debe tener campo status string');
        $this->assertArrayHasKey('message', $responseData, 'Response debe tener campo message string');
        $this->assertArrayHasKey('data', $responseData, 'Response debe tener campo data object');

        if ($response->json('success')) {
            $data = $responseData['data'];
            $this->assertArrayHasKey('session_id', $data, 'Data debe tener session_id string');
            $this->assertArrayHasKey('expires_at', $data, 'Data debe tener expires_at ISO 8601 string');
            $this->assertArrayHasKey('final_total', $data, 'Data debe tener final_total number');

            // ✅ VALIDAR TIPOS EXACTOS
            $this->assertIsString($data['session_id'], 'session_id debe ser string');
            $this->assertIsString($data['expires_at'], 'expires_at debe ser string ISO 8601');
            $this->assertIsNumeric($data['final_total'], 'final_total debe ser numeric');
        }
    }

    /** @test */
    public function test_create_checkout_request_contract()
    {
        // ✅ VALIDAR: Request coincide con DatafastCheckoutRequest TypeScript
        Auth::login($this->testUser);

        $response = $this->postJson('/api/datafast/create-checkout', $this->validCreateCheckoutData);

        // Verificar estructura de response coincide con DatafastCheckoutResponse
        $responseData = $response->json();

        $this->assertArrayHasKey('success', $responseData, 'Response debe tener campo success boolean');
        $this->assertArrayHasKey('status', $responseData, 'Response debe tener campo status string');
        $this->assertArrayHasKey('message', $responseData, 'Response debe tener campo message string');

        $this->assertIsBool($responseData['success'], 'success debe ser boolean');
        $this->assertIsString($responseData['status'], 'status debe ser string');
        $this->assertIsString($responseData['message'], 'message debe ser string');

        if ($responseData['success'] && isset($responseData['data'])) {
            $data = $responseData['data'];
            $this->assertArrayHasKey('checkout_id', $data, 'Data debe tener checkout_id string');
            $this->assertArrayHasKey('widget_url', $data, 'Data debe tener widget_url string');
            $this->assertArrayHasKey('transaction_id', $data, 'Data debe tener transaction_id string');
            $this->assertArrayHasKey('amount', $data, 'Data debe tener amount number');

            // ✅ VALIDAR TIPOS EXACTOS
            $this->assertIsString($data['checkout_id'], 'checkout_id debe ser string');
            $this->assertIsString($data['widget_url'], 'widget_url debe ser string URL');
            $this->assertIsString($data['transaction_id'], 'transaction_id debe ser string');
            $this->assertIsNumeric($data['amount'], 'amount debe ser numeric');
        }
    }

    /** @test */
    public function test_verify_payment_request_contract()
    {
        // ✅ VALIDAR: Request coincide con DatafastVerifyPaymentRequest TypeScript
        Auth::login($this->testUser);

        // Primero crear un pago de prueba
        $payment = DatafastPayment::create([
            'user_id' => $this->testUser->id,
            'transaction_id' => $this->validVerifyPaymentData['transaction_id'],
            'amount' => $this->validVerifyPaymentData['calculated_total'],
            'status' => 'pending',
            'currency' => 'USD',
        ]);

        $response = $this->postJson('/api/datafast/verify-payment', $this->validVerifyPaymentData);

        // Verificar estructura de response coincide con DatafastVerifyPaymentResponse
        $responseData = $response->json();

        $this->assertArrayHasKey('success', $responseData, 'Response debe tener campo success boolean');
        $this->assertArrayHasKey('status', $responseData, 'Response debe tener campo status string');
        $this->assertArrayHasKey('message', $responseData, 'Response debe tener campo message string');

        $this->assertIsBool($responseData['success'], 'success debe ser boolean');
        $this->assertIsString($responseData['status'], 'status debe ser string');
        $this->assertIsString($responseData['message'], 'message debe ser string');

        // Validar valores posibles de status según TypeScript
        $validStatuses = ['success', 'processing', 'error', 'pending'];
        $this->assertContains($responseData['status'], $validStatuses,
            'status debe ser uno de: ' . implode(', ', $validStatuses));

        if ($responseData['success'] && isset($responseData['data'])) {
            $data = $responseData['data'];
            $this->assertArrayHasKey('order_id', $data, 'Data debe tener order_id number');
            $this->assertArrayHasKey('order_number', $data, 'Data debe tener order_number string');
            $this->assertArrayHasKey('total', $data, 'Data debe tener total number');
            $this->assertArrayHasKey('payment_status', $data, 'Data debe tener payment_status string');
            $this->assertArrayHasKey('payment_id', $data, 'Data debe tener payment_id string');
            $this->assertArrayHasKey('transaction_id', $data, 'Data debe tener transaction_id string');
            $this->assertArrayHasKey('processed_at', $data, 'Data debe tener processed_at ISO 8601 string');

            // ✅ VALIDAR TIPOS EXACTOS
            $this->assertIsNumeric($data['order_id'], 'order_id debe ser numeric');
            $this->assertIsString($data['order_number'], 'order_number debe ser string');
            $this->assertIsNumeric($data['total'], 'total debe ser numeric');
            $this->assertIsString($data['payment_status'], 'payment_status debe ser string');
            $this->assertIsString($data['payment_id'], 'payment_id debe ser string');
            $this->assertIsString($data['transaction_id'], 'transaction_id debe ser string');
            $this->assertIsString($data['processed_at'], 'processed_at debe ser string ISO 8601');

            // Validar valores posibles de payment_status según TypeScript
            $validPaymentStatuses = ['completed', 'pending', 'failed', 'error'];
            $this->assertContains($data['payment_status'], $validPaymentStatuses,
                'payment_status debe ser uno de: ' . implode(', ', $validPaymentStatuses));
        }
    }

    /** @test */
    public function test_numeric_type_casting_consistency()
    {
        // ✅ VALIDAR: Números se mantienen como números en todo el flujo
        Auth::login($this->testUser);

        // Test con números como strings (simulando JavaScript)
        $dataWithStringNumbers = $this->validCreateCheckoutData;
        $dataWithStringNumbers['total'] = '29.90';              // String number
        $dataWithStringNumbers['subtotal'] = '21.00';           // String number
        $dataWithStringNumbers['shipping_cost'] = '5.00';       // String number
        $dataWithStringNumbers['tax'] = '3.90';                 // String number

        $response = $this->postJson('/api/datafast/create-checkout', $dataWithStringNumbers);

        if ($response->json('success') && $response->json('data')) {
            $amount = $response->json('data.amount');

            // ✅ VERIFICAR: Backend convirtió strings a números correctamente
            $this->assertIsNumeric($amount, 'amount debe ser numérico después del casting');
            $this->assertEquals(29.90, (float) $amount, 'amount debe tener el valor correcto');
        }
    }

    /** @test */
    public function test_required_vs_optional_fields_contract()
    {
        // ✅ VALIDAR: Campos obligatorios vs opcionales según TypeScript
        Auth::login($this->testUser);

        // Test sin campos opcionales
        $minimalData = [
            'shippingAddress' => [
                'street' => 'Test Street 123',      // ✅ OBLIGATORIO
                'city' => 'Quito',                  // ✅ OBLIGATORIO
                'country' => 'EC',                  // ✅ OBLIGATORIO
                // identification omitido (opcional)
            ],
            'customer' => [
                'doc_id' => '1234567890',            // ✅ OBLIGATORIO
                // Otros campos omitidos (opcionales)
            ],
            'total' => 25.50,                       // ✅ OBLIGATORIO
            // subtotal, shipping_cost, tax omitidos (opcionales)
        ];

        $response = $this->postJson('/api/datafast/create-checkout', $minimalData);

        // Debe funcionar con solo campos obligatorios
        $this->assertTrue($response->status() !== 422,
            'Request con solo campos obligatorios debe ser válido. Errores: ' .
            json_encode($response->json('errors') ?? []));

        // Test faltando campo obligatorio
        $invalidData = $minimalData;
        unset($invalidData['customer']['doc_id']); // Quitar campo obligatorio

        $response2 = $this->postJson('/api/datafast/create-checkout', $invalidData);

        $this->assertEquals(422, $response2->status(),
            'Request sin campo obligatorio doc_id debe fallar');
        $this->assertArrayHasKey('errors', $response2->json(),
            'Response de error debe incluir campo errors');
    }

    /** @test */
    public function test_error_response_contract_consistency()
    {
        // ✅ VALIDAR: Respuestas de error siguen el mismo contrato
        Auth::login($this->testUser);

        // Enviar datos inválidos para forzar error
        $invalidData = ['invalid_field' => 'invalid_value'];

        $response = $this->postJson('/api/datafast/create-checkout', $invalidData);

        $responseData = $response->json();

        // ✅ VERIFICAR: Estructura de error consistente
        $this->assertArrayHasKey('success', $responseData, 'Error response debe tener success');
        $this->assertArrayHasKey('status', $responseData, 'Error response debe tener status');
        $this->assertArrayHasKey('message', $responseData, 'Error response debe tener message');

        $this->assertFalse($responseData['success'], 'success debe ser false en errores');
        $this->assertEquals('error', $responseData['status'], 'status debe ser "error" en errores');
        $this->assertIsString($responseData['message'], 'message debe ser string en errores');

        // Verificar que tiene errors o error_code cuando corresponde
        $this->assertTrue(
            isset($responseData['errors']) || isset($responseData['error_code']),
            'Error response debe tener errors o error_code para debugging'
        );
    }
}