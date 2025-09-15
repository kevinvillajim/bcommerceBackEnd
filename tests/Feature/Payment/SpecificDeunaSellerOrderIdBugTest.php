<?php

namespace Tests\Feature\Payment;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpecificDeunaSellerOrderIdBugTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $sellerUser;

    private Seller $seller;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRealProductionData();
    }

    private function setupRealProductionData(): void
    {
        // Crear data exactamente como en producción
        $this->buyer = User::factory()->create([
            'id' => 25, // ID real de producción
            'name' => 'Test Production User',
            'email' => 'test@production.com',
        ]);

        $this->sellerUser = User::factory()->create([
            'name' => 'Production Seller',
            'email' => 'seller@production.com',
        ]);

        $this->seller = Seller::factory()->create([
            'id' => 6, // ID real de producción
            'user_id' => $this->sellerUser->id,
            'store_name' => 'Production Store',
            'status' => 'active',
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Production Category',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Production Product',
            'price' => 2.00,
            'discount_percentage' => 50.00, // 50% descuento = $1.00 final
            'stock' => 50,
            'user_id' => $this->sellerUser->id,
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'status' => 'active',
            'published' => true,
        ]);
    }

    #[Test]
    public function it_reproduces_exact_deuna_seller_order_id_null_bug()
    {
        echo "\n";
        echo "🔍 REPRODUCIENDO EL BUG EXACTO DE DEUNA - seller_order_id NULL\n";
        echo "================================================================\n";

        // 🚨 CRITICAL: Simular condiciones de producción - carrito con datos previos
        $cart = ShoppingCart::factory()->create([
            'user_id' => $this->buyer->id,
            'total' => 15.50, // Datos previos en el carrito como en producción
        ]);

        // Agregar items al carrito como en producción
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 2.00,
            'subtotal' => 2.00,
        ]);

        // 🚨 SIMULAR: Múltiples productos/sellers como en producción real
        $anotherProduct = Product::factory()->create([
            'user_id' => $this->sellerUser->id,
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'price' => 10.00,
            'stock' => 20,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $anotherProduct->id,
            'quantity' => 1,
            'price' => 10.00,
            'subtotal' => 10.00,
        ]);

        // Usar los IDs exactos de producción
        echo "🔍 Datos de producción:\n";
        echo "   - User ID: {$this->buyer->id}\n";
        echo "   - Seller ID: {$this->seller->id}\n";
        echo "   - Product ID: {$this->product->id}\n";

        $this->actingAs($this->buyer, 'api');

        echo "\n🔍 SIMULANDO CONDICIONES ADVERSAS DE PRODUCCIÓN:\n";
        echo '   - Carrito con items previos: '.$cart->items->count()."\n";
        echo '   - Total carrito: '.$cart->total."\n";

        // 🚨 CRITICAL: Simular estado de transacción como en producción
        // En producción podría haber transacciones en curso
        \DB::beginTransaction();

        try {
            // Hacer la llamada exacta que hace Deuna en producción
            $response = $this->postJson('/api/checkout', [
                'payment' => [
                    'method' => 'de_una',
                    'qr_type' => 'dynamic',
                ],
                'shipping' => [
                    'first_name' => 'Juan',
                    'last_name' => 'Perez',
                    'email' => 'test@test.com',
                    'phone' => '0987654321',
                    'address' => 'Dirección del checkout de DeUna',
                    'city' => 'Ciudad',
                    'state' => 'Estado',
                    'postal_code' => '00000',
                    'country' => 'EC',
                ],
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 1,
                        'price' => 2.00,
                    ],
                ],
                'calculated_totals' => [
                    'subtotal' => 1.0,
                    'tax' => 0.8999999999999999,
                    'shipping' => 5.0,
                    'total' => 6.9,
                    'total_discounts' => 1.0,
                ],
            ], [
                'Authorization' => 'Bearer '.auth()->guard('api')->attempt(['email' => $this->buyer->email, 'password' => 'password']),
            ]);

            \DB::commit();

        } catch (\Exception $e) {
            \DB::rollback();
            echo '🚨 EXCEPCIÓN DURANTE CHECKOUT: '.$e->getMessage()."\n";
            throw $e;
        }

        echo '✅ Deuna Response Status: '.$response->status()."\n";
        $responseData = $response->json();
        echo '✅ Deuna Response: '.json_encode($responseData)."\n";

        // Buscar la orden creada
        $order = Order::where('user_id', $this->buyer->id)
            ->where('payment_method', 'de_una')
            ->latest()
            ->first();

        echo "\n🔍 ANÁLISIS DETALLADO DE LA ORDEN CREADA:\n";
        if ($order) {
            echo "   Order ID: {$order->id}\n";
            echo "   User ID: {$order->user_id}\n";
            echo "   Seller ID: {$order->seller_id}\n";
            echo '   🚨 SELLER_ORDER_ID: '.($order->seller_order_id ?? 'NULL')."\n";
            echo "   Payment Method: {$order->payment_method}\n";
            echo "   Status: {$order->status}\n";
            echo "   Total: {$order->total}\n";

            // Verificar OrderItems
            $orderItems = OrderItem::where('order_id', $order->id)->get();
            echo "\n🔍 ORDER ITEMS:\n";
            echo '   Total OrderItems: '.$orderItems->count()."\n";
            foreach ($orderItems as $item) {
                echo "   - Item ID: {$item->id}, Product: {$item->product_id}, Seller: {$item->seller_id}, SellerOrder: ".($item->seller_order_id ?? 'NULL')."\n";
            }

            // Verificar SellerOrders
            $sellerOrders = SellerOrder::where('order_id', $order->id)->get();
            echo "\n🔍 SELLER ORDERS:\n";
            echo '   Total SellerOrders: '.$sellerOrders->count()."\n";
            foreach ($sellerOrders as $sellerOrder) {
                echo "   - SellerOrder ID: {$sellerOrder->id}, Seller: {$sellerOrder->seller_id}, Order: {$sellerOrder->order_id}\n";
                echo '   - Original Total: '.($sellerOrder->original_total ?? 'NULL')."\n";
                echo "   - Volume Discount Savings: {$sellerOrder->volume_discount_savings}\n";
                echo '   - Volume Discounts Applied: '.($sellerOrder->volume_discounts_applied ? 'true' : 'false')."\n";
                echo "   - Shipping Cost: {$sellerOrder->shipping_cost}\n";
                echo "   - Payment Method: {$sellerOrder->payment_method}\n";
            }

            // 🚨 AQUÍ ESTÁ EL PROBLEMA REAL
            if ($order->seller_order_id === null) {
                echo "\n🚨 BUG CONFIRMADO: DEUNA NO ASIGNA seller_order_id\n";
                echo "🚨 ESTO CAUSA:\n";
                echo "   - Los sellers no reciben notificación de envío\n";
                echo "   - Los productos no se pueden marcar como enviados\n";
                echo "   - El flujo de fulfillment se rompe\n";

                if ($sellerOrders->count() > 0) {
                    echo "\n🔍 INCONSISTENCIA DETECTADA:\n";
                    echo '   - Existen SellerOrders: '.$sellerOrders->count()."\n";
                    echo "   - Pero Order.seller_order_id es NULL\n";
                    echo "   - ProcessCheckoutUseCase FALLÓ en actualizar el campo\n";
                }

                $this->fail("🚨 BUG REAL: Deuna no asigna seller_order_id. Order ID: {$order->id}");
            } else {
                echo "\n✅ seller_order_id asignado correctamente: {$order->seller_order_id}\n";
            }
        } else {
            $this->fail('❌ No se creó ninguna orden - el endpoint falló');
        }
    }

    #[Test]
    public function it_compares_datafast_vs_deuna_seller_order_id_assignment()
    {
        echo "\n";
        echo "🔍 COMPARACIÓN DIRECTA: DATAFAST vs DEUNA\n";
        echo "=========================================\n";

        $this->actingAs($this->buyer, 'api');

        // Test Datafast primero
        echo "💳 Testing Datafast...\n";
        $datafastResponse = $this->postJson('/api/checkout', [
            'payment' => ['method' => 'datafast'],
            'shipping' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@test.com',
                'phone' => '0999999999',
                'address' => 'Test Address',
                'city' => 'Test City',
                'state' => 'Test State',
                'postal_code' => '00000',
                'country' => 'EC',
            ],
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'price' => 2.00,
                ],
            ],
            'calculated_totals' => [
                'subtotal' => 1.0,
                'tax' => 0.8999999999999999,
                'shipping' => 5.0,
                'total' => 6.9,
                'total_discounts' => 1.0,
            ],
        ], [
            'Authorization' => 'Bearer '.auth()->guard('api')->attempt(['email' => $this->buyer->email, 'password' => 'password']),
        ]);

        // Crear un nuevo usuario para evitar conflictos de carrito
        $buyer2 = User::factory()->create([
            'name' => 'Test User 2',
            'email' => 'test2@production.com',
        ]);

        $this->actingAs($buyer2, 'api');

        // Test Deuna después
        echo "💳 Testing Deuna...\n";
        $deunaResponse = $this->postJson('/api/checkout', [
            'payment' => ['method' => 'de_una', 'qr_type' => 'dynamic'],
            'shipping' => [
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'email' => 'test2@test.com',
                'phone' => '0987654321',
                'address' => 'Test Address 2',
                'city' => 'Test City 2',
                'state' => 'Test State 2',
                'postal_code' => '11111',
                'country' => 'EC',
            ],
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'price' => 2.00,
                ],
            ],
            'calculated_totals' => [
                'subtotal' => 1.0,
                'tax' => 0.8999999999999999,
                'shipping' => 5.0,
                'total' => 6.9,
                'total_discounts' => 1.0,
            ],
        ], [
            'Authorization' => 'Bearer '.auth()->guard('api')->attempt(['email' => $buyer2->email, 'password' => 'password']),
        ]);

        // Analizar resultados
        echo "\n📊 RESULTADOS COMPARATIVOS:\n";

        $datafastOrder = Order::where('user_id', $this->buyer->id)->where('payment_method', 'datafast')->first();
        $deunaOrder = Order::where('user_id', $buyer2->id)->where('payment_method', 'de_una')->first();

        echo 'Datafast - seller_order_id: '.($datafastOrder->seller_order_id ?? 'NULL')."\n";
        echo 'Deuna - seller_order_id: '.($deunaOrder->seller_order_id ?? 'NULL')."\n";

        if ($datafastOrder && $datafastOrder->seller_order_id && $deunaOrder && ! $deunaOrder->seller_order_id) {
            echo "\n🚨 BUG CONFIRMADO: DATAFAST funciona, DEUNA falla\n";
            $this->fail('Deuna no asigna seller_order_id mientras que Datafast sí lo hace');
        }
    }
}
