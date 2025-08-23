<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $seller;

    protected $anotherSeller;

    protected $order;

    protected $userToken;

    protected $sellerToken;

    protected $anotherSellerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear un usuario vendedor
        $this->seller = User::factory()->create([
            'email' => 'seller@test.com',
            'password' => Hash::make('password'),
        ]);

        // Crear un perfil de vendedor
        Seller::factory()->create([
            'user_id' => $this->seller->id,
            'store_name' => 'Test Store',
            'status' => 'active',
        ]);

        // Crear otro vendedor para pruebas de restricción
        $this->anotherSeller = User::factory()->create([
            'email' => 'seller2@test.com',
            'password' => Hash::make('password'),
        ]);

        // Crear perfil para el otro vendedor
        Seller::factory()->create([
            'user_id' => $this->anotherSeller->id,
            'store_name' => 'Another Test Store',
            'status' => 'active',
        ]);

        // Crear un usuario normal
        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
        ]);

        // Generar tokens JWT
        $this->userToken = JWTAuth::fromUser($this->user);
        $this->sellerToken = JWTAuth::fromUser($this->seller);
        $this->anotherSellerToken = JWTAuth::fromUser($this->anotherSeller);

        // Crear una orden con el seller_id
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'status' => 'pending',
            'payment_status' => 'completed',
        ]);

        // Añadir algunos items a la orden
        OrderItem::factory()->count(2)->create([
            'order_id' => $this->order->id,
        ]);
    }

    /** @test */
    public function seller_can_view_their_orders()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->getJson('/api/seller/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'currentPage',
                    'totalPages',
                    'totalItems',
                    'itemsPerPage',
                ],
            ]);
    }

    /** @test */
    public function seller_can_filter_orders_by_status()
    {
        // Crear una segunda orden con un estado diferente
        Order::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'status' => 'processing',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->getJson('/api/seller/orders?status=pending');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verificar que los resultados solo incluyen órdenes con estado 'pending'
        $orders = $response->json('data');
        foreach ($orders as $order) {
            $this->assertEquals('pending', $order['status']);
        }
    }

    /** @test */
    public function seller_can_update_order_status()
    {
        Event::fake();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->patchJson('/api/seller/orders/'.$this->order->id.'/status', [
            'status' => 'processing',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'processing',
                ],
            ]);

        // Verificar que el evento de cambio de estado fue disparado
        Event::assertDispatched(OrderStatusChanged::class, function ($event) {
            return $event->orderId === $this->order->id &&
                $event->previousStatus === 'pending' &&
                $event->currentStatus === 'processing';
        });

        // Verificar que el estado de la orden se actualizó en la base de datos
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'processing',
        ]);
    }

    /** @test */
    public function seller_cannot_update_another_sellers_order()
    {
        // Crear una orden para otro vendedor
        $anotherOrder = Order::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->anotherSeller->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->patchJson('/api/seller/orders/'.$anotherOrder->id.'/status', [
            'status' => 'processing',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function seller_can_complete_an_order()
    {
        Event::fake();

        // Cambiar el estado de la orden a 'delivered' para poder completarla
        $this->order->update(['status' => 'delivered']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->postJson('/api/seller/orders/'.$this->order->id.'/complete');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verificar que los eventos fueron disparados
        Event::assertDispatched(OrderStatusChanged::class);
        Event::assertDispatched(OrderCompleted::class);

        // Verificar que el estado de la orden se actualizó en la base de datos
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function seller_can_view_order_stats()
    {
        // Crear varias órdenes con diferentes estados
        Order::factory()->count(3)->create([
            'seller_id' => $this->seller->id,
            'status' => 'pending',
        ]);

        Order::factory()->count(2)->create([
            'seller_id' => $this->seller->id,
            'status' => 'processing',
        ]);

        Order::factory()->count(1)->create([
            'seller_id' => $this->seller->id,
            'status' => 'completed',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->getJson('/api/seller/orders/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'totalOrders',
                    'pendingOrders',
                    'processingOrders',
                    'shippedOrders',
                    'deliveredOrders',
                    'cancelledOrders',
                    'totalSales',
                ],
            ]);

        // Verificar que los contadores coinciden con los datos creados
        $stats = $response->json('data');
        $this->assertEquals(7, $stats['totalOrders']); // 1 de setUp + 6 adicionales
        $this->assertEquals(4, $stats['pendingOrders']); // 1 de setUp + 3 adicionales
        $this->assertEquals(2, $stats['processingOrders']);
        $this->assertEquals(1, $stats['completedOrders']);
    }

    /** @test */
    public function seller_can_update_shipping_info()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->patchJson('/api/seller/orders/'.$this->order->id.'/shipping', [
            'tracking_number' => 'TRACK123456',
            'carrier' => 'DHL',
            'shipping_date' => now()->format('Y-m-d'),
            'estimated_delivery' => now()->addDays(3)->format('Y-m-d'),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verificar que la información de envío se guardó correctamente
        $order = Order::find($this->order->id);
        $shippingData = $order->shipping_data;

        $this->assertEquals('TRACK123456', $shippingData['tracking_number']);
        $this->assertEquals('DHL', $shippingData['carrier']);

        // Verificar que el estado cambió a 'shipped' al proporcionar número de seguimiento
        $this->assertEquals('shipped', $order->status);
    }

    /** @test */
    public function seller_can_cancel_order()
    {
        Event::fake();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->postJson('/api/seller/orders/'.$this->order->id.'/cancel', [
            'reason' => 'Producto no disponible',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verificar que el estado de la orden se actualizó a 'cancelled'
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => 'cancelled',
        ]);

        // Verificar que el evento de cambio de estado fue disparado
        Event::assertDispatched(OrderStatusChanged::class, function ($event) {
            return $event->orderId === $this->order->id &&
                $event->previousStatus === 'pending' &&
                $event->currentStatus === 'cancelled';
        });
    }

    /** @test */
    public function user_cannot_access_seller_endpoints()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->userToken,
            'Accept' => 'application/json',
        ])->getJson('/api/seller/orders');

        // Esperamos código 403 Forbidden o 401 Unauthorized dependiendo de tu implementación
        $response->assertStatus(403);
    }

    /** @test */
    public function seller_can_view_orders_awaiting_shipment()
    {
        // Crear órdenes en estado 'processing'
        Order::factory()->count(2)->create([
            'seller_id' => $this->seller->id,
            'status' => 'processing',
            'payment_status' => 'completed',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->getJson('/api/seller/orders/awaiting-shipment');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        // Verificar que hay al menos 2 órdenes pendientes de envío
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function seller_can_view_order_details()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->sellerToken,
            'Accept' => 'application/json',
        ])->getJson('/api/seller/orders/'.$this->order->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_id',
                    'seller_id',
                    'total',
                    'status',
                    'payment_status',
                    'items',
                ],
            ]);
    }
}
