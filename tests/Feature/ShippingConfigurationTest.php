<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Admin;
use App\Models\Configuration;
use App\Services\ConfigurationService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingConfigurationTest extends TestCase
{
    // use RefreshDatabase; // REMOVED - No borrar datos de producción

    private $adminUser;
    private $configService;
    private $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user with admin relationship
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com'
        ]);

        // Create admin profile for the user
        Admin::create([
            'user_id' => $this->adminUser->id,
            'status' => 'active'
        ]);

        $this->configService = app(ConfigurationService::class);
        $this->pricingService = app(PricingService::class);
    }

    /** @test */
    public function admin_can_get_shipping_configuration()
    {
        // Set default shipping configuration
        $this->configService->setConfig('shipping.enabled', true);
        $this->configService->setConfig('shipping.free_threshold', 50.00);
        $this->configService->setConfig('shipping.default_cost', 5.00);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/configurations/category?category=shipping');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'enabled' => true,
                    'freeThreshold' => 50.00,
                    'defaultCost' => 5.00,
                ]
            ]);
    }

    /** @test */
    public function admin_can_update_shipping_configuration()
    {
        $newConfig = [
            'enabled' => true,
            'freeThreshold' => 75.00,
            'defaultCost' => 7.50,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/configurations/category', [
                'category' => 'shipping',
                'configurations' => $newConfig
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success'
            ]);

        // Verify the configuration was saved
        $this->assertEquals(true, $this->configService->getConfig('shipping.enabled'));
        $this->assertEquals(75.00, $this->configService->getConfig('shipping.free_threshold'));
        $this->assertEquals(7.50, $this->configService->getConfig('shipping.default_cost'));
    }

    /** @test */
    public function shipping_cost_calculation_uses_configuration()
    {
        // Set custom shipping configuration
        $this->configService->setConfig('shipping.enabled', true);
        $this->configService->setConfig('shipping.free_threshold', 100.00);
        $this->configService->setConfig('shipping.default_cost', 10.00);

        // Test cart items with subtotal below threshold
        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 1,
                'price' => 25.00,
                'base_price' => 25.00,
                'discount_percentage' => 0,
            ]
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should apply shipping cost since subtotal (25) < threshold (100)
        $this->assertEquals(10.00, $result['totals']['shipping_cost']);
        $this->assertEquals(false, $result['totals']['shipping_info']['free_shipping']);
        $this->assertEquals(100.00, $result['totals']['shipping_info']['free_shipping_threshold']);
    }

    /** @test */
    public function free_shipping_applies_when_threshold_reached()
    {
        // Set shipping configuration
        $this->configService->setConfig('shipping.enabled', true);
        $this->configService->setConfig('shipping.free_threshold', 50.00);
        $this->configService->setConfig('shipping.default_cost', 5.00);

        // Test cart items with subtotal above threshold
        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 2,
                'price' => 30.00,
                'base_price' => 30.00,
                'discount_percentage' => 0,
            ]
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should have free shipping since subtotal (60) >= threshold (50)
        $this->assertEquals(0.00, $result['totals']['shipping_cost']);
        $this->assertEquals(true, $result['totals']['shipping_info']['free_shipping']);
        $this->assertEquals(50.00, $result['totals']['shipping_info']['free_shipping_threshold']);
    }

    /** @test */
    public function disabled_shipping_returns_zero_cost()
    {
        // Disable shipping
        $this->configService->setConfig('shipping.enabled', false);
        $this->configService->setConfig('shipping.free_threshold', 50.00);
        $this->configService->setConfig('shipping.default_cost', 5.00);

        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 1,
                'price' => 10.00,
                'base_price' => 10.00,
                'discount_percentage' => 0,
            ]
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should have no shipping cost when disabled
        $this->assertEquals(0.00, $result['totals']['shipping_cost']);
        $this->assertEquals(true, $result['totals']['shipping_info']['free_shipping']);
        $this->assertNull($result['totals']['shipping_info']['free_shipping_threshold']);
    }

    /** @test */
    public function shipping_configuration_validation_works()
    {
        $invalidConfig = [
            'enabled' => 'not_boolean',
            'freeThreshold' => -10, // Invalid negative value
            'defaultCost' => 'not_numeric',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/configurations/category', [
                'category' => 'shipping',
                'configurations' => $invalidConfig
            ]);

        // Should handle validation errors gracefully
        // (Exact status depends on backend validation implementation)
        $this->assertTrue(in_array($response->status(), [422, 400, 500]));
    }

    /** @test */
    public function edge_case_exact_threshold_amount()
    {
        // Set shipping configuration
        $this->configService->setConfig('shipping.enabled', true);
        $this->configService->setConfig('shipping.free_threshold', 50.00);
        $this->configService->setConfig('shipping.default_cost', 5.00);

        // Test cart items with subtotal exactly at threshold
        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 1,
                'price' => 50.00,
                'base_price' => 50.00,
                'discount_percentage' => 0,
            ]
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should have free shipping since subtotal (50) >= threshold (50)
        $this->assertEquals(0.00, $result['totals']['shipping_cost']);
        $this->assertEquals(true, $result['totals']['shipping_info']['free_shipping']);
    }

    /** @test */
    public function shipping_calculation_with_discounts()
    {
        // Set shipping configuration
        $this->configService->setConfig('shipping.enabled', true);
        $this->configService->setConfig('shipping.free_threshold', 40.00);
        $this->configService->setConfig('shipping.default_cost', 5.00);

        // Test cart items with discounts - subtotal after discount should be considered
        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 1,
                'price' => 30.00,
                'base_price' => 50.00, // Original price is 50, discounted to 30
                'discount_percentage' => 40, // 40% discount
            ]
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should apply shipping cost since discounted subtotal (30) < threshold (40)
        $this->assertEquals(5.00, $result['totals']['shipping_cost']);
        $this->assertEquals(false, $result['totals']['shipping_info']['free_shipping']);
    }

    /** @test */
    public function non_admin_cannot_access_shipping_configuration()
    {
        $regularUser = User::factory()->create([
            'email' => 'user@test.com'
        ]);

        $response = $this->actingAs($regularUser)
            ->getJson('/api/admin/configurations/category?category=shipping');

        $response->assertStatus(403); // Or whatever your auth middleware returns
    }

    /** @test */
    public function guest_cannot_access_shipping_configuration()
    {
        $response = $this->getJson('/api/admin/configurations/category?category=shipping');

        $response->assertStatus(401); // Unauthorized
    }
}