<?php

namespace Tests\Feature\Checkout;

use App\Domain\Services\PricingCalculatorService;
use App\Infrastructure\Repositories\EloquentOrderRepository;
use App\Infrastructure\Repositories\EloquentProductRepository;
use App\Infrastructure\Repositories\EloquentSellerOrderRepository;
use App\Infrastructure\Repositories\EloquentShoppingCartRepository;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Services\ConfigurationService;
use App\UseCases\Cart\ApplyCartDiscountCodeUseCase;
use App\UseCases\Checkout\ProcessCheckoutUseCase;
use App\UseCases\Order\CreateOrderUseCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InspectPricingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_inspects_exact_pricing_calculations()
    {
        // 🛍️ Crear comprador
        $buyer = User::factory()->create([
            'name' => 'Juan Pérez',
            'email' => 'juan@example.com',
        ]);

        // 🏪 Crear vendedor
        $sellerUser = User::factory()->create([
            'name' => 'María García',
            'email' => 'maria@tienda.com',
        ]);

        $seller = Seller::factory()->create([
            'user_id' => $sellerUser->id,
            'store_name' => 'Tienda María',
            'status' => 'active',
        ]);

        // 📦 Crear categoría
        $category = Category::factory()->create([
            'name' => 'Electrónicos',
        ]);

        // 📱 Crear productos EXACTOS SIN DESCUENTOS
        $product1 = Product::factory()->create([
            'name' => 'Smartphone Premium',
            'price' => 599.99,
            'discount_percentage' => 0.00, // ⭐ SIN DESCUENTO
            'stock' => 15,
            'user_id' => $sellerUser->id,
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'status' => 'active',
            'published' => true,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Auriculares Bluetooth',
            'price' => 79.99,
            'discount_percentage' => 0.00, // ⭐ SIN DESCUENTO
            'stock' => 25,
            'user_id' => $sellerUser->id,
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'status' => 'active',
            'published' => true,
        ]);

        // 🛒 Crear carrito
        $cart = ShoppingCart::factory()->create([
            'user_id' => $buyer->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product1->id,
            'quantity' => 1,
            'price' => 599.99,
            'subtotal' => 599.99,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 2,
            'price' => 79.99,
            'subtotal' => 159.98,  // 79.99 * 2
        ]);

        // 🧮 CÁLCULO MANUAL ESPERADO:
        $expectedP1 = 599.99;
        $expectedP2 = 79.99 * 2; // 159.98
        $expectedSubtotal = $expectedP1 + $expectedP2; // 759.97

        echo "\n";
        echo "🧮 CÁLCULOS ESPERADOS (MANUALES):\n";
        echo "================================\n";
        echo "Producto 1: $599.99 × 1 = $599.99\n";
        echo "Producto 2: $79.99 × 2 = $159.98\n";
        echo "Subtotal esperado: $759.97\n";
        echo "================================\n";

        // 🏗️ Crear servicios reales
        $cartRepository = new EloquentShoppingCartRepository;
        $orderRepository = new EloquentOrderRepository;
        $productRepository = new EloquentProductRepository;
        $sellerOrderRepository = new EloquentSellerOrderRepository;

        $paymentGateway = $this->createMock(\App\Domain\Interfaces\PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('processPayment')
            ->willReturn([
                'success' => true,
                'transaction_id' => 'TEST_TXN_123',
                'message' => 'Pago procesado exitosamente',
            ]);

        $createOrderUseCase = new CreateOrderUseCase(
            $orderRepository,
            $productRepository
        );

        $configService = new ConfigurationService;
        $applyCartDiscountUseCase = new ApplyCartDiscountCodeUseCase(
            new \App\Services\PricingService($configService)
        );

        $pricingService = new PricingCalculatorService(
            $productRepository,
            $configService,
            $applyCartDiscountUseCase
        );

        // ⚡ EJECUTAR CHECKOUT
        $checkoutUseCase = new ProcessCheckoutUseCase(
            $cartRepository,
            $orderRepository,
            $productRepository,
            $sellerOrderRepository,
            $paymentGateway,
            $createOrderUseCase,
            $configService,
            $applyCartDiscountUseCase,
            $pricingService
        );

        $paymentData = ['method' => 'datafast'];
        $shippingData = [
            'address' => 'Test Address',
            'city' => 'Test City',
            'country' => 'Ecuador',
        ];

        $billingData = $shippingData; // Para tests, billing = shipping
        $result = $checkoutUseCase->execute($buyer->id, $paymentData, $shippingData, $billingData);

        // 🔍 INSPECCIONAR RESULTADOS REALES
        $order = \App\Models\Order::where('user_id', $buyer->id)->first();

        echo "\n";
        echo "🔍 RESULTADOS REALES DEL SISTEMA:\n";
        echo "=================================\n";
        echo 'subtotal_products: $'.$order->subtotal_products."\n";
        echo 'original_total: $'.($order->original_total ?? 'NULL')."\n";
        echo 'total: $'.$order->total."\n";
        echo 'iva_amount: $'.$order->iva_amount."\n";
        echo 'shipping_cost: $'.$order->shipping_cost."\n";
        echo 'total_discounts: $'.$order->total_discounts."\n";
        echo 'volume_discount_savings: $'.$order->volume_discount_savings."\n";
        echo 'seller_discount_savings: $'.$order->seller_discount_savings."\n";
        echo 'volume_discounts_applied: '.($order->volume_discounts_applied ? 'YES' : 'NO')."\n";
        echo 'free_shipping: '.($order->free_shipping ? 'YES' : 'NO')."\n";
        echo 'feedback_discount_amount: $'.$order->feedback_discount_amount."\n";
        echo "=================================\n";

        // 🔍 INSPECCIONAR PRICING INFO
        echo "\n";
        echo "🔍 PRICING INFO DETALLADO:\n";
        echo "==========================\n";
        if (isset($result['pricing_info'])) {
            foreach ($result['pricing_info'] as $key => $value) {
                if (is_numeric($value)) {
                    echo "$key: $".number_format($value, 2)."\n";
                } elseif (is_bool($value)) {
                    echo "$key: ".($value ? 'YES' : 'NO')."\n";
                } elseif (is_array($value)) {
                    echo "$key: [ARRAY]\n";
                } else {
                    echo "$key: ".(string) $value."\n";
                }
            }
        }
        echo "==========================\n";

        // 🚨 VERIFICAR DIFERENCIA
        $actualSubtotal = $order->subtotal_products;
        $difference = $expectedSubtotal - $actualSubtotal;

        echo "\n";
        echo "🚨 ANÁLISIS DE DIFERENCIA:\n";
        echo "=========================\n";
        echo 'Subtotal esperado: $'.number_format($expectedSubtotal, 2)."\n";
        echo 'Subtotal real: $'.number_format($actualSubtotal, 2)."\n";
        echo 'Diferencia: $'.number_format($difference, 2)."\n";

        if ($difference > 0.01) {
            echo "❌ ERROR: El sistema está aplicando descuentos no esperados\n";
        } elseif ($difference < -0.01) {
            echo "❌ ERROR: El sistema está cobrando de más\n";
        } else {
            echo "✅ CORRECTO: Los cálculos coinciden\n";
        }
        echo "=========================\n";

        // 🔍 VERIFICAR CONFIGURACIÓN DE TAX RATE
        $configuredTaxRate = $configService->getConfig('payment.taxRate');
        $taxRateAsDecimal = $configuredTaxRate / 100; // Convertir de porcentaje a decimal

        echo "\n";
        echo "🔧 CONFIGURACIÓN DE TAX RATE:\n";
        echo "=============================\n";
        echo 'Tax rate configurado: '.$configuredTaxRate."%\n";
        echo 'Tax rate como decimal: '.$taxRateAsDecimal."\n";
        echo "=============================\n";

        // 🧮 VERIFICAR CÁLCULO DE IVA CON LA CONFIGURACIÓN REAL
        $expectedIVA = ($actualSubtotal + $order->shipping_cost) * $taxRateAsDecimal;
        echo "\n";
        echo "🧮 VERIFICACIÓN DE IVA:\n";
        echo "======================\n";
        echo 'Base para IVA: $'.number_format(($actualSubtotal + $order->shipping_cost), 2)."\n";
        echo "IVA esperado ({$configuredTaxRate}%): $".number_format($expectedIVA, 2)."\n";
        echo 'IVA calculado: $'.number_format($order->iva_amount, 2)."\n";
        echo 'Diferencia IVA: $'.number_format($expectedIVA - $order->iva_amount, 2)."\n";
        echo "======================\n";

        // ✅ ASSERTIONS EXACTAS
        $this->assertTrue($result['success'], 'Checkout debe ser exitoso');

        // Verificar que NO haya errores de cálculo mayores a 1 centavo
        $this->assertLessThanOrEqual(0.01, abs($difference),
            'FALLA CRÍTICA: Diferencia en subtotal es $'.number_format($difference, 2));

        $this->assertLessThanOrEqual(0.01, abs($expectedIVA - $order->iva_amount),
            'FALLA CRÍTICA: IVA mal calculado. Esperado: $'.number_format($expectedIVA, 2).', Calculado: $'.number_format($order->iva_amount, 2));
    }
}
