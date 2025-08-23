<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Admin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(['super_admin', 'content_manager', 'customer_support', 'analytics']),
            'permissions' => $this->getRandomPermissions(),

            'last_login_at' => $this->faker->optional(0.7)->dateTimeThisMonth(),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }

    /**
     * Get random permissions based on role
     */
    protected function getRandomPermissions(): array
    {
        $allPermissions = [
            'manage_users',
            'manage_sellers',
            'manage_products',
            'manage_orders',
            'manage_categories',
            'manage_ratings',
            'manage_content',
            'view_reports',
            'export_data',
        ];

        // Return a random subset of permissions
        return $this->faker->randomElements(
            $allPermissions,
            $this->faker->numberBetween(2, count($allPermissions))
        );
    }

    /**
     * Indicate that the admin is super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'permissions' => [],  // Super admins have all permissions implicitly
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the admin is content manager.
     */
    public function contentManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'content_manager',
            'permissions' => ['manage_products', 'manage_categories', 'manage_content'],
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the admin is customer support.
     */
    public function customerSupport(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'customer_support',
            'permissions' => ['manage_orders', 'manage_ratings', 'view_reports'],
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the admin is analytics focused.
     */
    public function analytics(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'analytics',
            'permissions' => ['view_reports', 'export_data'],
            'status' => 'active',
        ]);
    }
}
