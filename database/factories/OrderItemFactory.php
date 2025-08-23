<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $price = $this->faker->randomFloat(2, 10, 200);
        $subtotal = $quantity * $price;

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'price' => $price,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Set specific product for the order item
     */
    public function forProduct(Product $product): self
    {
        return $this->state(function (array $attributes) use ($product) {
            $quantity = $attributes['quantity'] ?? $this->faker->numberBetween(1, 5);

            return [
                'product_id' => $product->id,
                'price' => $product->price,
                'subtotal' => $product->price * $quantity,
            ];
        });
    }
}
