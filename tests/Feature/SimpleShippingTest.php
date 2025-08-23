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

class SimpleShippingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_shipping_record()
    {
        // Crear usuario
        $user = User::factory()->create();

        // Crear producto
        $product = Product::factory()->create([
            'price' => 50,
        ]);

        // Crear orden con la estructura correcta
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 150,
            'status' => 'paid',
            'order_number' => 'ORD-'.rand(100000, 999999),
            'shipping_data' => json_encode([
                'address' => 'Calle Principal 123',
                'city' => 'Ciudad Ejemplo',
                'state' => 'Estado Ejemplo',
                'country' => 'País Ejemplo',
                'postal_code' => '12345',
            ]),
        ]);

        // Crear item de orden
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => $product->price,
            'subtotal' => $product->price * 3,
        ]);

        // Crear envío
        $shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'tracking_number' => 'TR'.rand(100000, 999999),
            'status' => ShippingStatus::PENDING,
            'address' => 'Calle Principal 123',
            'city' => 'Ciudad Ejemplo',
            'state' => 'Estado Ejemplo',
            'country' => 'País Ejemplo',
            'postal_code' => '12345',
        ]);

        // Verificar que se creó el envío
        $this->assertModelExists($shipping);
        $this->assertEquals(ShippingStatus::PENDING, $shipping->status);
        $this->assertEquals($order->id, $shipping->order_id);
    }

    #[Test]
    public function it_can_update_shipping_status()
    {
        // Create order with items and shipping
        $order = Order::factory()
            ->withItems(2)
            ->create(['status' => 'paid']);

        $shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'status' => ShippingStatus::PENDING,
        ]);

        // Update shipping status
        $shipping->update([
            'status' => ShippingStatus::IN_TRANSIT,
            'last_updated' => now(),
        ]);

        $shipping->refresh();

        // Assert status was updated
        $this->assertEquals(ShippingStatus::IN_TRANSIT, $shipping->status);
    }
}
