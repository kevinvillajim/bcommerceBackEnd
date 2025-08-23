<?php

namespace Tests\Feature;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Order;
use App\Models\Shipping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

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

        // Obtener token de autenticación
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->token = $response->json('access_token');

        // Crear orden
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total' => 150,
            'status' => 'paid',
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
    }

    #[Test]
    public function it_can_get_shipping_tracking_info()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/shipping/'.$this->shipping->tracking_number);

        // Mostrar contenido de respuesta y número de tracking si falla
        if ($response->status() !== 200) {
            var_dump([
                'tracking_number' => $this->shipping->tracking_number,
                'response' => $response->getContent(),
            ]);
        }

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'tracking_number' => $this->shipping->tracking_number,
                'status' => ShippingStatus::PENDING,
                'estimated_delivery' => $this->shipping->estimated_delivery->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Test]
    public function it_can_get_shipping_history()
    {
        // Añadir algunos eventos de historial
        $this->shipping->addHistoryEvent(
            ShippingStatus::PENDING,
            is_array($this->shipping->current_location) ?
                $this->shipping->current_location :
                [
                    'lat' => 19.4326,
                    'lng' => -99.1332,
                    'address' => 'Centro de distribución principal',
                ],
            'Pedido registrado en el sistema de envíos'
        );

        $this->shipping->addHistoryEvent(
            ShippingStatus::PROCESSING,
            $this->shipping->current_location, // Ya es un array, no necesita json_decode
            'Pedido en procesamiento en el almacén'
        );

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/shipping/'.$this->shipping->tracking_number.'/history');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'tracking_number' => $this->shipping->tracking_number,
                'current_status' => ShippingStatus::PROCESSING,
            ],
        ]);

        // Verificar que hay 2 eventos en el historial
        $response->assertJsonCount(2, 'data.history');
    }

    #[Test]
    public function it_rejects_invalid_tracking_numbers()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/shipping/INVALID123');

        $response->assertStatus(400);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    #[Test]
    public function it_can_get_shipping_route()
    {
        // Añadir varios puntos de ruta
        $locations = [
            [
                'lat' => 19.4326,
                'lng' => -99.1332,
                'address' => 'Centro de distribución principal',
            ],
            [
                'lat' => 19.3910,
                'lng' => -99.2837,
                'address' => 'Centro de distribución secundario',
            ],
            [
                'lat' => 19.2964,
                'lng' => -99.1679,
                'address' => 'Punto de entrega',
            ],
        ];

        foreach ($locations as $index => $location) {
            $status = $index == 0 ? ShippingStatus::PENDING : ($index == 1 ? ShippingStatus::IN_TRANSIT : ShippingStatus::OUT_FOR_DELIVERY);

            $details = 'Actualización de ubicación #'.($index + 1);
            $timestamp = now()->subHours(24 - $index * 8);

            $this->shipping->addHistoryEvent($status, $location, $details, $timestamp);
        }

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
                'status',
                'is_delivered',
            ],
        ]);

        // Verificar que hay 3 puntos en la ruta
        $response->assertJsonCount(3, 'data.route_points');
    }
}
