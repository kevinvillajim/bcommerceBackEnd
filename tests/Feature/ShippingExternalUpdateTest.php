<?php

namespace Tests\Feature;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Order;
use App\Models\Shipping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingExternalUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected Shipping $shipping;

    protected string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar API key para la prueba
        $this->apiKey = 'test_api_key';
        config(['services.shipping_api.key' => $this->apiKey]);

        // Crear usuario
        $user = User::factory()->create();

        // Crear orden
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 150,
            'status' => 'paid',
        ]);

        // Crear envío con un número de tracking que tenga exactamente 14 caracteres (TR + 12 caracteres)
        // para que pase la validación en ShippingAPIAdapter->isValidTrackingNumber
        $randomNum = str_pad(rand(100000, 999999), 12, '0');
        $trackingNumber = 'TR'.substr($randomNum, 0, 12);

        // Verificar longitud
        if (strlen($trackingNumber) !== 14) {
            $trackingNumber = substr('TR'.str_repeat('0', 12), 0, 14);
        }

        Log::info("Usando número de tracking en pruebas: $trackingNumber");

        $this->shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'tracking_number' => $trackingNumber,
            'status' => ShippingStatus::PENDING,
            'address' => 'Calle Principal 123',
            'city' => 'Ciudad Ejemplo',
            'state' => 'Estado Ejemplo',
            'country' => 'País Ejemplo',
            'postal_code' => '12345',
            'current_location' => json_encode([
                'lat' => 19.4326,
                'lng' => -99.1332,
                'address' => 'Centro de distribución principal',
            ]),
            'estimated_delivery' => now()->addDays(5),
        ]);
    }

    #[Test]
    public function it_accepts_external_updates_with_valid_api_key()
    {
        // Información de depuración
        Log::info('Test: Actualización con API key válida');
        Log::info('Tracking Number: '.$this->shipping->tracking_number);
        Log::info('API Key: '.$this->apiKey);

        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::IN_TRANSIT,
            'current_location' => [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de distribución secundario',
            ],
            'timestamp' => now()->toDateTimeString(),
            'details' => 'El paquete está en camino a la dirección de entrega',
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => $this->apiKey,
        ]);

        // Si hay error, mostrar para depuración
        if ($response->status() !== 200) {
            Log::error('Error en respuesta: '.$response->getContent());
        }

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Shipping status updated successfully',
        ]);

        // Verificar que el estado se actualizó en la base de datos
        $this->assertDatabaseHas('shippings', [
            'id' => $this->shipping->id,
            'status' => ShippingStatus::IN_TRANSIT,
        ]);

        // Verificar que se creó una entrada en el historial
        $this->assertDatabaseHas('shipping_history', [
            'shipping_id' => $this->shipping->id,
            'status' => ShippingStatus::IN_TRANSIT,
            'details' => 'El paquete está en camino a la dirección de entrega',
        ]);
    }

    #[Test]
    public function it_rejects_external_updates_with_invalid_api_key()
    {
        Log::info('Test: Rechazo con API key inválida');

        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::IN_TRANSIT,
            'current_location' => [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de distribución secundario',
            ],
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => 'invalid_key',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_rejects_external_updates_with_invalid_status()
    {
        Log::info('Test: Rechazo con estado inválido');

        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => 'invalid_status',
            'current_location' => [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de distribución secundario',
            ],
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => $this->apiKey,
        ]);

        // Si hay error, mostrar para depuración
        if ($response->status() !== 400) {
            Log::error('Estado inesperado: '.$response->status());
            Log::error('Contenido: '.$response->getContent());
        }

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Estado de envío inválido: invalid_status',
        ]);
    }

    #[Test]
    public function it_rejects_external_updates_to_nonexistent_shipping()
    {
        Log::info('Test: Rechazo con tracking number inexistente');

        // Usar un tracking number que cumpla el formato pero no exista
        $nonexistentTrackingNumber = 'TR'.str_pad(rand(1000000, 9999999), 12, '0');
        $nonexistentTrackingNumber = substr($nonexistentTrackingNumber, 0, 14);

        $updateData = [
            'tracking_number' => $nonexistentTrackingNumber,
            'status' => ShippingStatus::IN_TRANSIT,
            'current_location' => [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de distribución secundario',
            ],
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => $this->apiKey,
        ]);

        // Si hay error, mostrar para depuración
        if ($response->status() !== 400) {
            Log::error('Estado inesperado: '.$response->status());
            Log::error('Contenido: '.$response->getContent());
        }

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Envío no encontrado: '.$nonexistentTrackingNumber,
        ]);
    }

    #[Test]
    public function it_rejects_external_updates_to_final_state_shipping()
    {
        Log::info('Test: Rechazo de actualización para envío en estado final');

        // Actualizar envío a estado entregado (final)
        $this->shipping->status = ShippingStatus::DELIVERED;
        $this->shipping->delivered_at = now();
        $this->shipping->save();

        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::IN_TRANSIT,
            'current_location' => [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de distribución secundario',
            ],
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => $this->apiKey,
        ]);

        // Si hay error, mostrar para depuración
        if ($response->status() !== 400) {
            Log::error('Estado inesperado: '.$response->status());
            Log::error('Contenido: '.$response->getContent());
        }

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error',
            'message' => 'No se puede actualizar un envío que ya está en estado final: '.ShippingStatus::DELIVERED,
        ]);
    }

    #[Test]
    public function it_accepts_update_from_delivered_to_returned()
    {
        Log::info('Test: Actualización de entregado a devuelto');

        // Actualizar envío a estado entregado
        $this->shipping->status = ShippingStatus::DELIVERED;
        $this->shipping->delivered_at = now()->subDays(2);
        $this->shipping->save();

        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::RETURNED,
            'current_location' => [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de devoluciones',
            ],
            'details' => 'Cliente solicitó devolución del producto',
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => $this->apiKey,
        ]);

        // Si hay error, mostrar para depuración
        if ($response->status() !== 200) {
            Log::error('Estado inesperado: '.$response->status());
            Log::error('Contenido: '.$response->getContent());
        }

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        // Verificar que el estado se actualizó en la base de datos
        $this->assertDatabaseHas('shippings', [
            'id' => $this->shipping->id,
            'status' => ShippingStatus::RETURNED,
        ]);
    }
}
