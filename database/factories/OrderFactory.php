<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'seller_id' => Seller::factory(),
            'total' => $this->faker->randomFloat(2, 50, 500),
            'status' => $this->faker->randomElement(['pending', 'processing', 'paid', 'completed', 'cancelled']),
            'payment_method' => $this->faker->randomElement(['credit_card', 'paypal', 'transfer', null]),
            'payment_status' => $this->faker->randomElement(['pending', 'completed', 'failed', null]),
            'payment_id' => $this->faker->optional(0.7)->uuid(),
            'order_number' => 'ORD-'.$this->faker->unique()->numberBetween(100000, 999999),
            'shipping_data' => json_encode([
                'address' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->state(),
                'country' => $this->faker->country(),
                'postal_code' => $this->faker->postcode(),
            ]),
        ];
    }

    /**
     * Configure the order as completed
     */
    public function completed(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'payment_status' => 'completed',
            ];
        });
    }

    /**
     * Configure the order as paid
     */
    public function paid(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paid',
                'payment_status' => 'completed',
            ];
        });
    }

    /**
     * Add items to the order
     */
    public function withItems(int $count = 1): self
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            // Crear primero el seller_order si es necesario
            if (class_exists('\App\Models\SellerOrder')) {
                $sellerOrder = \App\Models\SellerOrder::create([
                    'order_id' => $order->id,
                    'seller_id' => $order->seller_id,
                    'total' => 0,
                    'status' => $order->status,
                    'order_number' => 'SO-'.substr($order->order_number, 4),
                    'shipping_data' => $order->shipping_data,
                ]);

                // Create order items through relationship with seller_order_id
                $items = \App\Models\OrderItem::factory()->count($count)->make([
                    'order_id' => $order->id,
                    'seller_order_id' => $sellerOrder->id,
                ]);

                $total = 0;
                foreach ($items as $item) {
                    $item->save();
                    $total += $item->subtotal;
                }

                // Update order and seller_order totals
                $order->update(['total' => $total]);
                $sellerOrder->update(['total' => $total]);
            } else {
                // Fallback original para sistemas sin seller_orders
                // Create order items through relationship
                \App\Models\OrderItem::factory()->count($count)->create([
                    'order_id' => $order->id,
                ]);

                // Update order total based on items
                $total = \App\Models\OrderItem::where('order_id', $order->id)->sum('subtotal');
                $order->update(['total' => $total]);
            }
        });
    }
}
