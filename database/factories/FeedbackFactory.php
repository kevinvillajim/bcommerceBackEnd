<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Feedback::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'seller_id' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(3),
            'type' => $this->faker->randomElement(['bug', 'improvement', 'other']),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'admin_notes' => $this->faker->optional(0.3)->sentence(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    /**
     * Indicate that the feedback is pending.
     */
    public function pending(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'pending',
                'admin_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];
        });
    }

    /**
     * Indicate that the feedback is approved.
     */
    public function approved(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'approved',
                'admin_notes' => $this->faker->sentence(),
                'reviewed_by' => User::factory(),
                'reviewed_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the feedback is rejected.
     */
    public function rejected(): Factory
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'rejected',
                'admin_notes' => $this->faker->sentence(),
                'reviewed_by' => User::factory(),
                'reviewed_at' => now(),
            ];
        });
    }
}
