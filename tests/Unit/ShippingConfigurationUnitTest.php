<?php

namespace Tests\Unit;

use App\Services\ConfigurationService;
use App\Services\PricingService;
use Tests\TestCase;

class ShippingConfigurationUnitTest extends TestCase
{
    private $configService;

    private $pricingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configService = app(ConfigurationService::class);
        $this->pricingService = app(PricingService::class);
    }

    /** @test */
    public function shipping_cost_calculation_uses_configuration_values()
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
            ],
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
            ],
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
            ],
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should have no shipping cost when disabled
        $this->assertEquals(0.00, $result['totals']['shipping_cost']);
        $this->assertEquals(true, $result['totals']['shipping_info']['free_shipping']);
        $this->assertNull($result['totals']['shipping_info']['free_shipping_threshold']);
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
            ],
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
            ],
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should apply shipping cost since discounted subtotal (30) < threshold (40)
        $this->assertEquals(5.00, $result['totals']['shipping_cost']);
        $this->assertEquals(false, $result['totals']['shipping_info']['free_shipping']);
    }

    /** @test */
    public function shipping_cost_affects_total_calculation()
    {
        // Set shipping configuration
        $this->configService->setConfig('shipping.enabled', true);
        $this->configService->setConfig('shipping.free_threshold', 100.00);
        $this->configService->setConfig('shipping.default_cost', 10.00);

        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 1,
                'price' => 20.00,
                'base_price' => 20.00,
                'discount_percentage' => 0,
            ],
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Verify total calculation includes shipping cost
        $expectedSubtotal = 20.00;
        $expectedShipping = 10.00;
        $expectedIva = $expectedSubtotal * 0.15; // IVA only on subtotal, not shipping
        $expectedTotal = $expectedSubtotal + $expectedShipping + $expectedIva;

        $this->assertEquals($expectedSubtotal, $result['totals']['subtotal_products']);
        $this->assertEquals($expectedShipping, $result['totals']['shipping_cost']);
        $this->assertEquals($expectedTotal, $result['totals']['final_total']);
    }

    /** @test */
    public function free_shipping_does_not_affect_iva_calculation()
    {
        // Set shipping configuration for free shipping
        $this->configService->setConfig('shipping.enabled', true);
        $this->configService->setConfig('shipping.free_threshold', 10.00);
        $this->configService->setConfig('shipping.default_cost', 5.00);

        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 1,
                'price' => 20.00,
                'base_price' => 20.00,
                'discount_percentage' => 0,
            ],
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Verify free shipping scenario
        $expectedSubtotal = 20.00;
        $expectedShipping = 0.00; // Free shipping
        $expectedIva = $expectedSubtotal * 0.15; // IVA only on subtotal, not shipping
        $expectedTotal = $expectedSubtotal + $expectedShipping + $expectedIva;

        $this->assertEquals($expectedSubtotal, $result['totals']['subtotal_products']);
        $this->assertEquals($expectedShipping, $result['totals']['shipping_cost']);
        $this->assertEquals($expectedIva, $result['totals']['iva_amount']);
        $this->assertEquals($expectedTotal, $result['totals']['final_total']);
        $this->assertEquals(true, $result['totals']['shipping_info']['free_shipping']);
    }

    /** @test */
    public function default_configuration_values_are_used()
    {
        // Don't set any configuration, should use defaults
        // Clear any existing config
        $this->configService->setConfig('shipping.enabled', null);
        $this->configService->setConfig('shipping.free_threshold', null);
        $this->configService->setConfig('shipping.default_cost', null);

        $cartItems = [
            [
                'product_id' => 1,
                'seller_id' => 1,
                'quantity' => 1,
                'price' => 25.00,
                'base_price' => 25.00,
                'discount_percentage' => 0,
            ],
        ];

        $result = $this->pricingService->calculateCheckoutTotals($cartItems);

        // Should use default values: enabled=true, threshold=50.00, cost=5.00
        // Since subtotal (25) < threshold (50), should apply default cost (5.00)
        $this->assertEquals(5.00, $result['totals']['shipping_cost']);
        $this->assertEquals(false, $result['totals']['shipping_info']['free_shipping']);
        $this->assertEquals(50.00, $result['totals']['shipping_info']['free_shipping_threshold']);
    }
}
