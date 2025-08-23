<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rating>
 */
class RatingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Rating::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([
            Rating::TYPE_USER_TO_SELLER,
            Rating::TYPE_SELLER_TO_USER,
            Rating::TYPE_USER_TO_PRODUCT,
        ]);

        $sellerId = null;
        $productId = null;
        $orderId = null;

        if ($type === Rating::TYPE_USER_TO_SELLER || $type === Rating::TYPE_SELLER_TO_USER) {
            $sellerId = Seller::factory();
        }

        if ($type === Rating::TYPE_USER_TO_PRODUCT) {
            $productId = Product::factory();
        }

        // 60% chance of having an associated order for verified purchase
        if ($this->faker->boolean(60)) {
            $orderId = Order::factory();
        }

        return [
            'user_id' => User::factory(),
            'seller_id' => $sellerId,
            'order_id' => $orderId,
            'product_id' => $productId,
            'rating' => $this->faker->randomFloat(1, 1, 5),
            'title' => $this->faker->sentence(),
            'comment' => $this->faker->paragraph(),
            'status' => $this->faker->randomElement([
                Rating::STATUS_PENDING,
                Rating::STATUS_APPROVED,
                Rating::STATUS_REJECTED,
                Rating::STATUS_FLAGGED,
            ]),
            'type' => $type,
        ];
    }

    /**
     * Indicate that the rating is for a product.
     */
    public function forProduct(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Rating::TYPE_USER_TO_PRODUCT,
            'product_id' => Product::factory(),
            'seller_id' => null,
        ]);
    }

    /**
     * Indicate that the rating is for a seller.
     */
    public function forSeller(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Rating::TYPE_USER_TO_SELLER,
            'seller_id' => Seller::factory(),
            'product_id' => null,
        ]);
    }

    /**
     * Indicate that the rating is for a user.
     */
    public function forUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Rating::TYPE_SELLER_TO_USER,
            'seller_id' => Seller::factory(),
            'product_id' => null,
        ]);
    }

    /**
     * Indicate that the rating is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Rating::STATUS_APPROVED,
        ]);
    }

    /**
     * Indicate that the rating is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Rating::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the rating is from a verified purchase.
     */
    public function verifiedPurchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => Order::factory(),
        ]);
    }
}
