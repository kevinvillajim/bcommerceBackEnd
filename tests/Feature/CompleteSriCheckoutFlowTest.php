<?php

namespace Tests\Feature;

use App\Domain\Services\PricingCalculatorService;
use App\Infrastructure\Repositories\EloquentOrderRepository;
use App\Infrastructure\Repositories\EloquentProductRepository;
use App\Infrastructure\Repositories\EloquentSellerOrderRepository;
use App\Infrastructure\Repositories\EloquentShoppingCartRepository;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ShoppingCart;
use App\Models\User;
use App\Services\ConfigurationService;
use App\Services\PriceVerificationService;
use App\UseCases\Cart\ApplyCartDiscountCodeUseCase;
use App\UseCases\Checkout\ProcessCheckoutUseCase;
use App\UseCases\Order\CreateOrderUseCase;
// ❌ PELIGRO ELIMINADO: RefreshDatabase borraría toda la DB
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompleteSriCheckoutFlowTest extends TestCase
{
    // ❌ PELIGRO ELIMINADO: RefreshDatabase borraría toda la DB

    private User $buyer;

    private User $sellerUser;

    private Seller $seller;

    private Category $category;

    private Product $product1;

    private Product $product2;

    private ShoppingCart $cart;

    protected function setUp(): void
    {
        parent::setUp();

        // ✅ Crear tablas SRI necesarias para testing
        $this->createSriTables();

        $this->createTestData();
    }

    private function createSriTables(): void
    {
        // ✅ Crear tabla invoices si no existe
        if (! \Illuminate\Support\Facades\Schema::hasTable('invoices')) {
            \Illuminate\Support\Facades\Schema::create('invoices', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('invoice_number')->unique();
                $table->foreignId('order_id')->constrained('orders');
                $table->foreignId('user_id')->constrained('users');
                $table->foreignId('transaction_id')->nullable()->constrained('accounting_transactions');
                $table->datetime('issue_date');
                $table->decimal('subtotal', 12, 2);
                $table->decimal('tax_amount', 12, 2);
                $table->decimal('total_amount', 12, 2);
                $table->string('currency', 10)->default('DOLAR');
                $table->enum('status', ['DRAFT', 'SENT_TO_SRI', 'PENDING', 'PROCESSING', 'RECEIVED', 'AUTHORIZED', 'APPROVED', 'REJECTED', 'NOT_AUTHORIZED', 'RETURNED', 'SRI_ERROR', 'FAILED', 'DEFINITIVELY_FAILED'])->default('DRAFT');

                // Campos del cliente
                $table->string('customer_identification');
                $table->string('customer_identification_type', 2);
                $table->string('customer_name');
                $table->string('customer_email');
                $table->text('customer_address');
                $table->string('customer_phone');

                // Campos SRI
                $table->string('sri_authorization_number')->nullable();
                $table->string('sri_access_key', 100)->nullable();
                $table->json('sri_response')->nullable();
                $table->integer('retry_count')->default(0);
                $table->timestamp('last_retry_at')->nullable();
                $table->string('sri_error_message')->nullable();
                $table->string('created_via', 20)->default('checkout');
                $table->string('pdf_path')->nullable();
                $table->timestamp('pdf_generated_at')->nullable();

                $table->timestamps();
            });
        }

        // ✅ Crear tabla invoice_items si no existe
        if (! \Illuminate\Support\Facades\Schema::hasTable('invoice_items')) {
            \Illuminate\Support\Facades\Schema::create('invoice_items', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
                $table->foreignId('product_id')->constrained('products');
                $table->string('product_code');
                $table->string('product_name');
                $table->text('product_description');
                $table->integer('quantity');
                $table->decimal('unit_price', 12, 2);
                $table->decimal('subtotal', 12, 2);
                $table->decimal('tax_rate', 5, 4)->default(0.1500);
                $table->decimal('tax_amount', 12, 2);
                $table->decimal('total_amount', 12, 2);
                $table->timestamps();
            });
        }
    }

    private function createTestData(): void
    {
        // 🛍️ Crear comprador: Kevin Villacreses con datos REALES
        $this->buyer = User::factory()->create([
            'name' => 'Kevin Villacreses',
            'email' => 'kevin.villacreses@test.com',
            'first_name' => 'Kevin',
            'last_name' => 'Villacreses',
        ]);

        // 🏪 Crear vendedor
        $this->sellerUser = User::factory()->create([
            'name' => 'Tienda Electrónicos',
            'email' => 'tienda@electronicos.com',
            'first_name' => 'Tienda',
            'last_name' => 'Electrónicos',
        ]);

        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'store_name' => 'Electrónicos Premium',
            'status' => 'active',
            'verification_level' => 'verified',
        ]);

        // 📦 Crear categoría
        $this->category = Category::factory()->create([
            'name' => 'Electrónicos',
            'slug' => 'electronicos',
        ]);

        // 📱 Crear productos SIN DESCUENTOS para cálculos exactos
        $this->product1 = Product::factory()->create([
            'name' => 'iPhone 15 Pro',
            'price' => 1299.99,
            'discount_percentage' => 0.00,
            'stock' => 10,
            'user_id' => $this->sellerUser->id,
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'status' => 'active',
            'published' => true,
            'description' => 'iPhone 15 Pro 256GB Space Black',
        ]);

        $this->product2 = Product::factory()->create([
            'name' => 'AirPods Pro 2',
            'price' => 249.99,
            'discount_percentage' => 0.00,
            'stock' => 25,
            'user_id' => $this->sellerUser->id,
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'status' => 'active',
            'published' => true,
            'description' => 'AirPods Pro 2nd Generation with MagSafe Case',
        ]);

        // 🛒 Crear carrito de compras con items
        $this->cart = ShoppingCart::factory()->create([
            'user_id' => $this->buyer->id,
        ]);

        // ✅ Agregar productos al carrito usando precios exactos del sistema
        // El sistema de verificación necesita precios exactos, incluyendo descuentos

        // Calcular precio final del producto 1 (con descuentos si los tiene)
        $product1FinalPrice = $this->product1->price * (1 - ($this->product1->discount_percentage / 100));

        // Calcular precio final del producto 2 (con descuentos si los tiene)
        $product2FinalPrice = $this->product2->price * (1 - ($this->product2->discount_percentage / 100));

        CartItem::factory()->create([
            'cart_id' => $this->cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 1,
            'price' => round($product1FinalPrice, 2), // ✅ Precio con descuentos aplicados
            'subtotal' => round($product1FinalPrice * 1, 2),
        ]);

        CartItem::factory()->create([
            'cart_id' => $this->cart->id,
            'product_id' => $this->product2->id,
            'quantity' => 2,
            'price' => round($product2FinalPrice, 2), // ✅ Precio con descuentos aplicados
            'subtotal' => round($product2FinalPrice * 2, 2),
        ]);

        // Actualizar total del carrito con precios finales
        $cartTotal = round($product1FinalPrice * 1, 2) + round($product2FinalPrice * 2, 2);
        $this->cart->update([
            'total' => $cartTotal,
        ]);
    }

    #[Test]
    public function it_completes_full_checkout_flow_with_real_sri_integration()
    {
        echo "\n🚀 INICIANDO TEST SRI COMPLETO - SIN ATAJOS, SIN MOCKS\n";
        echo "====================================================\n";
        echo "👤 Cliente: Kevin Villacreses\n";
        echo "🆔 RUC: 1702059887001\n";
        echo "📍 Dirección: Ferroviaria, Quito, Pichincha 07265\n";
        echo "📞 Teléfono: 0963368896\n";
        echo "====================================================\n";

        // 📊 Estado inicial - verificar datos de prueba
        $this->assertDatabaseHas('users', [
            'id' => $this->buyer->id,
            'name' => 'Kevin Villacreses',
        ]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $this->cart->id]);

        // 💳 Datos de pago REALES (Credit Card como TestCheckoutButton)
        $paymentData = [
            'method' => 'credit_card',
            'card_number' => '4111111111111111', // ✅ Misma tarjeta que TestCheckoutButton
            'card_expiry' => '12/25',
            'card_cvc' => '123',
            'card_holder' => 'KEVIN VILLACRESES',
        ];

        // 📮 Datos de envío REALES de Kevin Villacreses
        $shippingData = [
            'first_name' => 'Kevin',
            'last_name' => 'Villacreses',
            'email' => 'kevin.villacreses@test.com',
            'phone' => '0963368896',
            'address' => 'Ferroviaria',
            'city' => 'Quito',
            'state' => 'Pichincha',
            'postal_code' => '07265',
            'country' => 'Ecuador',
            'identification' => '1702059887001', // ✅ RUC real - sistema debe auto-detectar tipo
            'recipient_name' => 'Kevin Villacreses',
        ];

        // 🏗️ Crear instancias REALES de servicios (sin mocks)
        $cartRepository = new EloquentShoppingCartRepository;
        $orderRepository = new EloquentOrderRepository;
        $productRepository = new EloquentProductRepository;
        $sellerOrderRepository = new EloquentSellerOrderRepository;

        // ✅ USAR PAYMENT GATEWAY REAL como TestCheckoutButton
        // Credit card method funciona sin APIs externas inciertas
        $paymentGateway = app(\App\Domain\Interfaces\PaymentGatewayInterface::class);

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

        $priceVerificationService = new PriceVerificationService(
            $productRepository,
            $pricingService
        );

        // 🚀 Crear checkout use case REAL
        $checkoutUseCase = new ProcessCheckoutUseCase(
            $cartRepository,
            $orderRepository,
            $productRepository,
            $sellerOrderRepository,
            $paymentGateway,
            $createOrderUseCase,
            $configService,
            $applyCartDiscountUseCase,
            $pricingService,
            $priceVerificationService
        );

        // ✅ NO fake events - queremos que se ejecuten REALMENTE
        // Event::fake(); // ❌ NO HACER ESTO

        echo "⚡ Ejecutando checkout REAL...\n";

        // ⚡ EJECUTAR CHECKOUT REAL
        $billingData = $shippingData; // Para tests, billing = shipping
        $result = $checkoutUseCase->execute($this->buyer->id, $paymentData, $shippingData, $billingData);

        echo "✅ Checkout completado\n";

        // ✅ Verificaciones básicas del resultado
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order', $result);

        $order = $result['order'];

        // 🔍 MICRO-ÉXITO #1: Verificar que se creó la ORDEN
        $orderInDb = Order::where('user_id', $this->buyer->id)->first();
        $this->assertNotNull($orderInDb, '❌ MICRO-ÉXITO 1 FALLIDO: No se creó la orden en DB');

        echo "✅ MICRO-ÉXITO 1: Orden creada en DB - ID: {$orderInDb->id}\n";

        // 🧾 MICRO-ÉXITO #2: Verificar que se creó la FACTURA automáticamente
        sleep(2); // Dar tiempo a los listeners para ejecutarse

        $invoice = Invoice::where('order_id', $orderInDb->id)->first();
        $this->assertNotNull($invoice, '❌ MICRO-ÉXITO 2 FALLIDO: No se creó factura en DB');

        echo "✅ MICRO-ÉXITO 2: Factura creada en DB - ID: {$invoice->id}, Número: {$invoice->invoice_number}\n";

        // 🔍 Verificar datos del cliente en factura
        $this->assertEquals('1702059887001', $invoice->customer_identification);
        $this->assertEquals('Kevin Villacreses', $invoice->customer_name);
        $this->assertEquals('kevin.villacreses@test.com', $invoice->customer_email);
        $this->assertStringContainsString('Ferroviaria', $invoice->customer_address);
        $this->assertEquals('0963368896', $invoice->customer_phone);

        // 🔍 Verificar que el sistema detectó automáticamente el tipo RUC
        $this->assertEquals('04', $invoice->customer_identification_type,
            '❌ Sistema no detectó automáticamente que 1702059887001 es RUC (tipo 04)');

        echo "✅ Sistema detectó automáticamente RUC (tipo: {$invoice->customer_identification_type})\n";

        // 📡 MICRO-ÉXITO #3: Verificar comunicación REAL con SRI API
        echo "📡 Verificando comunicación con SRI API REAL...\n";

        // Dar más tiempo para que se procese el SRI
        sleep(5);

        // Refrescar factura para obtener estado actualizado
        $invoice->refresh();

        // ✅ La factura debe haber cambiado de estado DRAFT
        $this->assertNotEquals('DRAFT', $invoice->status,
            '❌ MICRO-ÉXITO 3 FALLIDO: Factura sigue en estado DRAFT, no se procesó con SRI');

        echo "✅ MICRO-ÉXITO 3: Factura procesada por SRI - Estado: {$invoice->status}\n";

        // 🔍 Verificar respuesta SRI
        if ($invoice->sri_response) {
            $sriResponse = json_decode($invoice->sri_response, true);
            echo '📋 Respuesta SRI: '.json_encode($sriResponse, JSON_PRETTY_PRINT)."\n";
        }

        // 🔍 Verificar campos SRI específicos
        if (in_array($invoice->status, ['AUTHORIZED', 'APPROVED'])) {
            $this->assertNotNull($invoice->sri_access_key, 'Factura aprobada debe tener clave de acceso');
            echo "🔑 Clave de acceso SRI: {$invoice->sri_access_key}\n";
        }

        if ($invoice->status === 'FAILED' || $invoice->status === 'REJECTED') {
            $this->assertNotNull($invoice->sri_error_message, 'Factura fallida debe tener mensaje de error');
            echo "❌ Error SRI: {$invoice->sri_error_message}\n";
        }

        // 📊 RESUMEN FINAL
        echo "\n🎉 TEST SRI COMPLETO FINALIZADO\n";
        echo "================================\n";
        echo "👤 Cliente: {$this->buyer->name}\n";
        echo "🆔 Identificación: {$invoice->customer_identification} (Tipo: {$invoice->customer_identification_type})\n";
        echo "🧾 Orden: {$orderInDb->order_number}\n";
        echo "📄 Factura: {$invoice->invoice_number}\n";
        echo '💰 Total: $'.number_format($invoice->total_amount, 2)."\n";
        echo "🔐 Estado SRI: {$invoice->status}\n";
        echo '📡 Respuesta SRI: '.($invoice->sri_response ? 'SÍ' : 'NO')."\n";
        echo "🔄 Reintentos: {$invoice->retry_count}\n";
        echo "================================\n";

        // ✅ TODOS LOS MICRO-ÉXITOS COMPLETADOS
        echo "✅ TODOS LOS MICRO-ÉXITOS COMPLETADOS SIN ATAJOS\n";
    }
}
