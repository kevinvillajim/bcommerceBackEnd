<?php

namespace Tests\Feature;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingTrackingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Product $product;

    protected Order $order;

    protected Shipping $shipping;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Crear producto
        $this->product = Product::factory()->create([
            'name' => 'Producto Tracking',
            'price' => 150,
            'stock' => 10,
        ]);

        // Crear orden
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total' => 150,
            'status' => 'paid',
        ]);

        // Crear item de orden
        OrderItem::factory()->create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 150,
            'subtotal' => 150,
        ]);

        // Crear envío
        $this->shipping = Shipping::factory()->create([
            'order_id' => $this->order->id,
            'tracking_number' => Shipping::generateTrackingNumber(),
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

        // Obtener token de autenticación
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->token = $response->json('access_token');
    }

    #[Test]
    public function it_gets_shipping_tracking_details()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/shipping/'.$this->shipping->tracking_number);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'tracking_number' => $this->shipping->tracking_number,
                'status' => ShippingStatus::PENDING,
                'estimated_delivery' => $this->shipping->estimated_delivery->toDateTimeString(),
            ],
        ]);
    }

    #[Test]
    public function it_updates_shipping_status_through_external_api()
    {
        // Simular una actualización desde la API externa de envíos
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
            'X-API-KEY' => config('services.shipping_api.key'),
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Shipping status updated successfully',
        ]);

        // Verificar que el estado del envío se actualizó en la base de datos
        $this->assertDatabaseHas('shippings', [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::IN_TRANSIT,
        ]);

        // Verificar que se creó una entrada de historial de envío
        $this->assertDatabaseHas('shipping_history', [
            'shipping_id' => $this->shipping->id,
            'status' => ShippingStatus::IN_TRANSIT,
            'details' => 'El paquete está en camino a la dirección de entrega',
        ]);
    }

    #[Test]
    public function it_completes_full_shipping_lifecycle()
    {
        // Estatus inicial: PENDING
        $this->assertEquals(ShippingStatus::PENDING, $this->shipping->status);

        // 1. Actualizar a IN_TRANSIT
        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::IN_TRANSIT,
            'current_location' => [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de distribución secundario',
            ],
            'timestamp' => now()->toDateTimeString(),
            'details' => 'El paquete ha sido recogido por el transportista',
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => config('services.shipping_api.key'),
        ]);

        $response->assertStatus(200);

        // 2. Actualizar ubicación durante el tránsito
        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::IN_TRANSIT,
            'current_location' => [
                'lat' => 19.2964,
                'lng' => -99.1679,
                'address' => 'En ruta - Ciudad Ejemplo Sur',
            ],
            'timestamp' => now()->addHours(5)->toDateTimeString(),
            'details' => 'El paquete está en ruta hacia su destino',
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => config('services.shipping_api.key'),
        ]);

        $response->assertStatus(200);

        // 3. Actualizar a OUT_FOR_DELIVERY
        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::OUT_FOR_DELIVERY,
            'current_location' => [
                'lat' => 19.4010,
                'lng' => -99.1705,
                'address' => 'Centro de distribución local',
            ],
            'timestamp' => now()->addHours(24)->toDateTimeString(),
            'details' => 'El paquete está siendo preparado para entrega final',
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => config('services.shipping_api.key'),
        ]);

        $response->assertStatus(200);

        // 4. Actualizar a DELIVERED
        $updateData = [
            'tracking_number' => $this->shipping->tracking_number,
            'status' => ShippingStatus::DELIVERED,
            'current_location' => [
                'lat' => 19.4326,
                'lng' => -99.1332,
                'address' => 'Calle Principal 123, Ciudad Ejemplo',
            ],
            'timestamp' => now()->addHours(30)->toDateTimeString(),
            'details' => 'El paquete ha sido entregado. Firmado por: Cliente',
        ];

        $response = $this->postJson('/api/shipping/external/update', $updateData, [
            'X-API-KEY' => config('services.shipping_api.key'),
        ]);

        $response->assertStatus(200);

        // Verificar que el envío está marcado como entregado
        $this->assertDatabaseHas('shippings', [
            'id' => $this->shipping->id,
            'status' => ShippingStatus::DELIVERED,
        ]);

        // Verificar todo el historial de envío
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/shipping/'.$this->shipping->tracking_number.'/history');

        $response->assertStatus(200);
        $response->assertJsonCount(4, 'data.history');
        $response->assertJsonPath('data.history.0.status', ShippingStatus::IN_TRANSIT);
        $response->assertJsonPath('data.history.1.status', ShippingStatus::IN_TRANSIT);
        $response->assertJsonPath('data.history.2.status', ShippingStatus::OUT_FOR_DELIVERY);
        $response->assertJsonPath('data.history.3.status', ShippingStatus::DELIVERED);

        // Verificar que el usuario puede ver un mapa con la ruta completa
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/shipping/'.$this->shipping->tracking_number.'/route');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'tracking_number',
                'route_points' => [
                    '*' => [
                        'lat',
                        'lng',
                        'address',
                        'timestamp',
                        'status',
                    ],
                ],
            ],
        ]);
    }
}
