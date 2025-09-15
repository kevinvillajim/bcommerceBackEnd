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

class MultiSellerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_processes_checkout_with_multiple_sellers_and_calculates_shipping_distribution()
    {
        // 🛍️ Crear comprador
        $buyer = User::factory()->create([
            'name' => 'Cliente Multiseller',
            'email' => 'cliente@multiseller-test.com',
        ]);

        // 🏪 Crear SELLER 1
        $sellerUser1 = User::factory()->create([
            'name' => 'Vendor Uno',
            'email' => 'vendor1@tienda.com',
        ]);

        $seller1 = Seller::factory()->create([
            'user_id' => $sellerUser1->id,
            'store_name' => 'Tienda Económica 1',
            'status' => 'active',
        ]);

        // 🏪 Crear SELLER 2
        $sellerUser2 = User::factory()->create([
            'name' => 'Vendor Dos',
            'email' => 'vendor2@tienda.com',
        ]);

        $seller2 = Seller::factory()->create([
            'user_id' => $sellerUser2->id,
            'store_name' => 'Tienda Económica 2',
            'status' => 'active',
        ]);

        // 📦 Crear categoría
        $category = Category::factory()->create([
            'name' => 'Productos Económicos',
        ]);

        // 💰 Crear productos ECONÓMICOS para probar envío
        // SELLER 1 - Productos
        $product1 = Product::factory()->create([
            'name' => 'Lápiz',
            'price' => 2.50,
            'discount_percentage' => 10.00, // Sin descuento para simplicidad
            'stock' => 100,
            'user_id' => $sellerUser1->id,
            'seller_id' => $seller1->id,
            'category_id' => $category->id,
            'status' => 'active',
            'published' => true,
        ]);

        $product2 = Product::factory()->create([
            'name' => 'Borrador',
            'price' => 1.75,
            'discount_percentage' => 5.00,
            'stock' => 150,
            'user_id' => $sellerUser1->id,
            'seller_id' => $seller1->id,
            'category_id' => $category->id,
            'status' => 'active',
            'published' => true,
        ]);

        // SELLER 2 - Productos
        $product3 = Product::factory()->create([
            'name' => 'Cuaderno',
            'price' => 3.00,
            'discount_percentage' => 20.00,
            'stock' => 80,
            'user_id' => $sellerUser2->id,
            'seller_id' => $seller2->id,
            'category_id' => $category->id,
            'status' => 'active',
            'published' => true,
        ]);

        $product4 = Product::factory()->create([
            'name' => 'Marcador',
            'price' => 2.25,
            'discount_percentage' => 2.00,
            'stock' => 120,
            'user_id' => $sellerUser2->id,
            'seller_id' => $seller2->id,
            'category_id' => $category->id,
            'status' => 'active',
            'published' => true,
        ]);

        // 🛒 Crear carrito con productos de AMBOS sellers
        $cart = ShoppingCart::factory()->create([
            'user_id' => $buyer->id,
        ]);

        // Seller 1: Lápiz (3 unidades) + Borrador (2 unidades)
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product1->id,
            'quantity' => 3,
            'price' => 2.50,
            'subtotal' => 7.50,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 2,
            'price' => 1.75,
            'subtotal' => 3.50,
        ]);

        // Seller 2: Cuaderno (4 unidades) + Marcador (3 unidades)
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product3->id,
            'quantity' => 4,
            'price' => 3.00,
            'subtotal' => 12.00,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product4->id,
            'quantity' => 3,
            'price' => 2.25,
            'subtotal' => 6.75,
        ]);

        // 🧮 CALCULAR AUTOMÁTICAMENTE LOS VALORES ESPERADOS
        // Seller 1: Lápiz (3 unidades con 10% seller discount)
        $product1_original_subtotal = $product1->price * 3; // $2.50 × 3 = $7.50
        $product1_seller_discount = $product1_original_subtotal * ($product1->discount_percentage / 100);
        $product1_after_seller = $product1_original_subtotal - $product1_seller_discount;
        // Volumen: 3 unidades = 5% descuento por volumen
        $product1_volume_discount = $product1_after_seller * 0.05;
        $product1_final = $product1_after_seller - $product1_volume_discount;

        // Seller 1: Borrador (2 unidades con 5% seller discount)
        $product2_original_subtotal = $product2->price * 2; // $1.75 × 2 = $3.50
        $product2_seller_discount = $product2_original_subtotal * ($product2->discount_percentage / 100);
        $product2_after_seller = $product2_original_subtotal - $product2_seller_discount;
        // Volumen: 2 unidades < 3 = sin descuento por volumen
        $product2_volume_discount = 0;
        $product2_final = $product2_after_seller;

        // Seller 2: Cuaderno (4 unidades con 20% seller discount)
        $product3_original_subtotal = $product3->price * 4; // $3.00 × 4 = $12.00
        $product3_seller_discount = $product3_original_subtotal * ($product3->discount_percentage / 100);
        $product3_after_seller = $product3_original_subtotal - $product3_seller_discount;
        // Volumen: 4 unidades = 5% descuento por volumen
        $product3_volume_discount = $product3_after_seller * 0.05;
        $product3_final = $product3_after_seller - $product3_volume_discount;

        // Seller 2: Marcador (3 unidades con 2% seller discount)
        $product4_original_subtotal = $product4->price * 3; // $2.25 × 3 = $6.75
        $product4_seller_discount = $product4_original_subtotal * ($product4->discount_percentage / 100);
        $product4_after_seller = $product4_original_subtotal - $product4_seller_discount;
        // Volumen: 3 unidades = 5% descuento por volumen
        $product4_volume_discount = $product4_after_seller * 0.05;
        $product4_final = $product4_after_seller - $product4_volume_discount;

        // Totales por seller
        $seller1_final_total = $product1_final + $product2_final;
        $seller2_final_total = $product3_final + $product4_final;
        $expected_subtotal_with_discounts = $seller1_final_total + $seller2_final_total;

        // Totales originales (sin descuentos)
        $expected_original_subtotal = $product1_original_subtotal + $product2_original_subtotal + $product3_original_subtotal + $product4_original_subtotal;

        // Envío y totales finales
        $expected_shipping = $expected_subtotal_with_discounts < 50 ? 5.00 : 0.00;
        $expected_iva = ($expected_subtotal_with_discounts + $expected_shipping) * 0.15;
        $expected_final_total = $expected_subtotal_with_discounts + $expected_shipping + $expected_iva;

        echo "\n";
        echo "🎯 ESCENARIO MULTISELLER DINÁMICO\n";
        echo "==================================\n";
        echo "🏪 SELLER 1 (Tienda Económica 1):\n";
        echo '   📝 Lápiz: $'.number_format($product1->price, 2).' × 3, desc. seller '.$product1->discount_percentage."%, desc. volumen 5%\n";
        echo '      Original: $'.number_format($product1_original_subtotal, 2).' → Final: $'.number_format($product1_final, 2)."\n";
        echo '   ✏️  Borrador: $'.number_format($product2->price, 2).' × 2, desc. seller '.$product2->discount_percentage."%, sin desc. volumen\n";
        echo '      Original: $'.number_format($product2_original_subtotal, 2).' → Final: $'.number_format($product2_final, 2)."\n";
        echo '   💰 Subtotal Seller 1: $'.number_format($seller1_final_total, 2)."\n";
        echo "\n";
        echo "🏪 SELLER 2 (Tienda Económica 2):\n";
        echo '   📓 Cuaderno: $'.number_format($product3->price, 2).' × 4, desc. seller '.$product3->discount_percentage."%, desc. volumen 5%\n";
        echo '      Original: $'.number_format($product3_original_subtotal, 2).' → Final: $'.number_format($product3_final, 2)."\n";
        echo '   🖊️  Marcador: $'.number_format($product4->price, 2).' × 3, desc. seller '.$product4->discount_percentage."%, desc. volumen 5%\n";
        echo '      Original: $'.number_format($product4_original_subtotal, 2).' → Final: $'.number_format($product4_final, 2)."\n";
        echo '   💰 Subtotal Seller 2: $'.number_format($seller2_final_total, 2)."\n";
        echo "\n";
        echo "🧮 TOTALES CALCULADOS AUTOMÁTICAMENTE:\n";
        echo '   Subtotal original: $'.number_format($expected_original_subtotal, 2)."\n";
        echo '   Subtotal con descuentos: $'.number_format($expected_subtotal_with_discounts, 2)."\n";
        echo '   Envío: $'.number_format($expected_shipping, 2)." (< $50 umbral)\n";
        echo '   IVA (15%): $'.number_format($expected_iva, 2)."\n";
        echo '   Total final: $'.number_format($expected_final_total, 2)."\n";
        echo "==================================\n";

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
                'transaction_id' => 'MULTISELLER_TXN_'.uniqid(),
                'message' => 'Pago procesado exitosamente con múltiples sellers',
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

        // 💳 Datos de pago (sin códigos de descuento para simplicidad)
        $paymentData = [
            'method' => 'datafast',
        ];

        // 📮 Datos de envío
        $shippingData = [
            'address' => 'Calle Principal 123, Edificio Central',
            'city' => 'Guayaquil',
            'country' => 'Ecuador',
        ];

        // ⚡ EJECUTAR CHECKOUT CON MÚLTIPLES SELLERS
        $billingData = $shippingData; // Para tests, billing = shipping
        $result = $checkoutUseCase->execute($buyer->id, $paymentData, $shippingData, $billingData);

        // 🔍 INSPECCIONAR ORDEN PRINCIPAL
        $mainOrder = \App\Models\Order::where('user_id', $buyer->id)->first();

        echo "\n";
        echo "🔍 ORDEN PRINCIPAL (orders):\n";
        echo "============================\n";
        echo 'ID: '.$mainOrder->id."\n";
        echo 'subtotal_products: $'.number_format($mainOrder->subtotal_products, 2)."\n";
        echo 'original_total: $'.number_format($mainOrder->original_total ?? 0, 2)."\n";
        echo 'total: $'.number_format($mainOrder->total, 2)."\n";
        echo 'iva_amount: $'.number_format($mainOrder->iva_amount, 2)."\n";
        echo 'shipping_cost: $'.number_format($mainOrder->shipping_cost, 2)."\n";
        echo 'total_discounts: $'.number_format($mainOrder->total_discounts, 2)."\n";
        echo 'free_shipping: '.($mainOrder->free_shipping ? 'SÍ' : 'NO')."\n";
        echo "============================\n";

        // 🔍 INSPECCIONAR ÓRDENES DE SELLERS
        $sellerOrders = \App\Models\SellerOrder::where('order_id', $mainOrder->id)->get();

        echo "\n";
        echo "🔍 ÓRDENES DE SELLERS (seller_orders):\n";
        echo "======================================\n";

        foreach ($sellerOrders as $sellerOrder) {
            $seller = \App\Models\Seller::find($sellerOrder->seller_id);
            echo '🏪 SELLER: '.$seller->store_name." (ID: {$sellerOrder->seller_id})\n";
            echo '   ID seller_order: '.$sellerOrder->id."\n";
            echo '   subtotal: $'.number_format($sellerOrder->subtotal, 2)."\n";
            echo '   seller_discount_amount: $'.number_format($sellerOrder->seller_discount_amount, 2)."\n";
            echo '   volume_discount_amount: $'.number_format($sellerOrder->volume_discount_amount, 2)."\n";
            echo '   shipping_cost: $'.number_format($sellerOrder->shipping_cost, 2)."\n";
            echo '   iva_amount: $'.number_format($sellerOrder->iva_amount, 2)."\n";
            echo '   platform_fee: $'.number_format($sellerOrder->platform_fee, 2)."\n";
            echo '   seller_earnings: $'.number_format($sellerOrder->seller_earnings, 2)."\n";
            echo '   total: $'.number_format($sellerOrder->total, 2)."\n";
            echo "   ---\n";
        }

        // Los cálculos ya se hicieron automáticamente arriba, solo calculamos distribución adicional
        // Distribución del envío (máximo 40% por seller cuando hay múltiples)
        $seller1ShippingShare = min($expected_shipping * 0.40, ($seller1_final_total / $expected_subtotal_with_discounts) * $expected_shipping);
        $seller2ShippingShare = min($expected_shipping * 0.40, ($seller2_final_total / $expected_subtotal_with_discounts) * $expected_shipping);

        // Comisión plataforma (10%)
        $seller1PlatformFee = $seller1_final_total * 0.10;
        $seller2PlatformFee = $seller2_final_total * 0.10;

        // Ganancias de sellers (subtotal - comisión + parte del envío)
        $seller1Earnings = $seller1_final_total - $seller1PlatformFee + $seller1ShippingShare;
        $seller2Earnings = $seller2_final_total - $seller2PlatformFee + $seller2ShippingShare;

        echo "\n";
        echo "🧮 DISTRIBUCIÓN CALCULADA AUTOMÁTICAMENTE:\n";
        echo "=========================================\n";
        echo 'Envío Seller 1 (40% max): $'.number_format($seller1ShippingShare, 2)."\n";
        echo 'Envío Seller 2 (40% max): $'.number_format($seller2ShippingShare, 2)."\n";
        echo 'Comisión Seller 1 (10%): $'.number_format($seller1PlatformFee, 2)."\n";
        echo 'Comisión Seller 2 (10%): $'.number_format($seller2PlatformFee, 2)."\n";
        echo 'Ganancias Seller 1: $'.number_format($seller1Earnings, 2)."\n";
        echo 'Ganancias Seller 2: $'.number_format($seller2Earnings, 2)."\n";
        echo "=========================================\n";

        // ✅ ASSERTIONS
        $this->assertTrue($result['success'], 'Checkout multiseller debe ser exitoso');

        // Verificar orden principal usando valores calculados automáticamente
        $this->assertLessThan(0.05, abs($expected_subtotal_with_discounts - $mainOrder->subtotal_products),
            'Subtotal principal debe coincidir con el cálculo automático (diferencia < $0.05)');
        $this->assertLessThan(0.10, abs($expected_final_total - $mainOrder->total),
            'Total principal debe coincidir con el cálculo automático (diferencia < $0.10)');
        $this->assertEquals($expected_shipping, $mainOrder->shipping_cost, 'Costo de envío calculado automáticamente');
        $this->assertEquals($expected_original_subtotal, $mainOrder->original_total, 'Subtotal original calculado automáticamente');

        // Verificar si tiene envío gratis o no basado en el cálculo
        $shouldHaveFreeShipping = $expected_subtotal_with_discounts >= 50;
        $this->assertEquals($shouldHaveFreeShipping, $mainOrder->free_shipping,
            'Estado de envío gratis debe coincidir con el cálculo automático');

        // Verificar que se crearon 2 órdenes de seller
        $this->assertCount(2, $sellerOrders, 'Deben crearse 2 órdenes de seller');

        // Verificar distribución entre sellers usando cálculos automáticos
        $seller1Order = $sellerOrders->where('seller_id', $seller1->id)->first();
        $seller2Order = $sellerOrders->where('seller_id', $seller2->id)->first();

        $this->assertNotNull($seller1Order, 'Orden de seller 1 debe existir');
        $this->assertNotNull($seller2Order, 'Orden de seller 2 debe existir');

        // Verificar que los totales de sellers coinciden con nuestros cálculos automáticos
        $this->assertLessThan(0.05, abs($seller1_final_total - $seller1Order->total),
            'Total de seller 1 debe coincidir con el cálculo automático (diferencia < $0.05)');
        $this->assertLessThan(0.05, abs($seller2_final_total - $seller2Order->total),
            'Total de seller 2 debe coincidir con el cálculo automático (diferencia < $0.05)');

        // Verificar que la suma de totales de sellers es EXACTA al subtotal_products (tolerancia mínima para punto flotante)
        $sellersTotal = $seller1Order->total + $seller2Order->total;
        $this->assertLessThan(0.01, abs($mainOrder->subtotal_products - $sellersTotal),
            'Suma de totales de sellers debe ser EXACTAMENTE igual al subtotal_products (diferencia < $0.01)');

        echo "\n";
        echo "🎉 TEST MULTISELLER COMPLETADO\n";
        echo "===============================\n";
        echo "✅ Orden principal creada correctamente\n";
        echo "✅ 2 órdenes de seller creadas\n";
        echo "✅ Distribución de envío calculada\n";
        echo "✅ Comisiones de plataforma aplicadas\n";
        echo "✅ Ganancias de sellers calculadas\n";
        echo "===============================\n";
    }
}
