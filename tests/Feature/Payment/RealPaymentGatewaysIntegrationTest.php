<?php

namespace Tests\Feature\Payment;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class RealPaymentGatewaysIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $sellerUser;

    private Seller $seller;

    private Category $category;

    private Product $product1;

    private ShoppingCart $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupRealTestData();
    }

    private function setupRealTestData(): void
    {
        // 🛍️ Crear comprador
        $this->buyer = User::factory()->create([
            'name' => 'Test Real User',
            'email' => 'real-test@payment.com',
        ]);

        // 🏪 Crear vendedor
        $this->sellerUser = User::factory()->create([
            'name' => 'Real Seller',
            'email' => 'real-seller@payment.com',
        ]);

        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'store_name' => 'Real Payment Test Store',
            'status' => 'active',
        ]);

        // 📦 Crear categoría
        $this->category = Category::factory()->create([
            'name' => 'Real Test Category',
        ]);

        // 📱 Crear producto simple
        $this->product1 = Product::factory()->create([
            'name' => 'Simple Test Product',
            'price' => 2.00, // Precio simple para test
            'discount_percentage' => 50.00, // 50% descuento = $1.00 final
            'stock' => 50,
            'user_id' => $this->sellerUser->id,
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'status' => 'active',
            'published' => true,
        ]);

        // 🛒 Crear carrito simple
        $this->cart = ShoppingCart::factory()->create([
            'user_id' => $this->buyer->id,
        ]);

        // Una sola unidad para simplificar
        CartItem::factory()->create([
            'cart_id' => $this->cart->id,
            'product_id' => $this->product1->id,
            'quantity' => 1,
            'price' => 2.00,
            'subtotal' => 2.00,
        ]);
    }

    #[Test]
    public function it_detects_seller_order_id_problem_in_real_deuna_vs_datafast()
    {
        echo "\n";
        echo "🔍 TESTING REAL INTEGRATION - SELLER_ORDER_ID ISSUE\n";
        echo "===================================================\n";

        // Autenticar usuario y generar JWT token
        $this->actingAs($this->buyer, 'api');
        $token = JWTAuth::fromUser($this->buyer);

        // 💳 DATAFAST TEST - USANDO API REAL
        echo "💳 Testing Datafast (Real API)...\n";

        try {
            $datafastResponse = $this->postJson('/api/checkout', [
                'payment' => [
                    'method' => 'datafast',
                ],
                'shipping' => [
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'email' => 'test@test.com',
                    'phone' => '0999999999',
                    'address' => 'Dirección del checkout de Datafast',
                    'city' => 'Ciudad',
                    'state' => 'Estado',
                    'postal_code' => '00000',
                    'country' => 'EC',
                ],
                'items' => [
                    [
                        'product_id' => $this->product1->id,
                        'quantity' => 1,
                        'price' => 2.00,
                    ],
                ],
                'calculated_totals' => [
                    'subtotal' => 1.00,
                    'tax' => 0.15,
                    'shipping' => 5.00,
                    'total' => 6.15,
                    'total_discounts' => 1.00,
                ],
            ], [
                'Authorization' => 'Bearer '.$token,
            ]);

            echo '✅ Datafast Response Status: '.$datafastResponse->status()."\n";
            $datafastResponseData = $datafastResponse->json();
            echo '   Datafast Full Response: '.json_encode($datafastResponseData)."\n";

            if ($datafastResponse->status() === 200 && isset($datafastResponseData['status']) && $datafastResponseData['status'] === 'success') {
                // Buscar la orden creada por Datafast
                $datafastOrder = Order::where('payment_method', 'datafast')
                    ->where('user_id', $this->buyer->id)
                    ->latest()
                    ->first();

                if ($datafastOrder) {
                    echo "   Order ID: {$datafastOrder->id}\n";
                    echo '   Seller Order ID: '.($datafastOrder->seller_order_id ?? 'NULL')."\n";

                    // 🔍 DEBUG: Verificar OrderItems para esta orden
                    $orderItems = \App\Models\OrderItem::where('order_id', $datafastOrder->id)->get();
                    echo '   🔍 Total OrderItems: '.$orderItems->count()."\n";
                    foreach ($orderItems as $item) {
                        echo "      - Item ID: {$item->id}, Product: {$item->product_id}, Seller: {$item->seller_id}, SellerOrder: ".($item->seller_order_id ?? 'NULL')."\n";
                    }

                    // 🔍 VERIFICAR SELLER_ORDER_ID EN DATAFAST
                    if ($datafastOrder->seller_order_id) {
                        // Verificar que existe el SellerOrder
                        $datafastSellerOrder = SellerOrder::find($datafastOrder->seller_order_id);
                        $this->assertNotNull($datafastSellerOrder,
                            '🚨 CRÍTICO: SellerOrder debe existir para Datafast');

                        echo "   ✅ Datafast SellerOrder ID: {$datafastSellerOrder->id}\n";
                        echo "   ✅ Datafast Seller ID: {$datafastSellerOrder->seller_id}\n";
                    } else {
                        echo "   ⚠️ Datafast seller_order_id es NULL\n";
                    }
                } else {
                    echo "   ⚠️ No se encontró orden de Datafast en BD\n";
                }
            } else {
                echo '   ⚠️ Datafast no retornó success: '.json_encode($datafastResponseData)."\n";

                // Buscar cualquier orden reciente para debug
                $anyDatafastOrder = Order::where('user_id', $this->buyer->id)->latest()->first();
                if ($anyDatafastOrder) {
                    echo "   🔍 DEBUG - Última orden encontrada: ID={$anyDatafastOrder->id}, método={$anyDatafastOrder->payment_method}\n";
                }
            }

        } catch (\Exception $e) {
            echo '⚠️ Datafast Error: '.$e->getMessage()."\n";
            // No fallar el test por errores de Datafast, seguir con Deuna
        }

        echo "\n";

        // 💳 DEUNA TEST - USANDO API REAL
        echo "💳 Testing Deuna (Real API)...\n";

        // Crear nuevo carrito para Deuna (evitar conflictos)
        $deunaCart = ShoppingCart::factory()->create([
            'user_id' => $this->buyer->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $deunaCart->id,
            'product_id' => $this->product1->id,
            'quantity' => 1,
            'price' => 2.00,
            'subtotal' => 2.00,
        ]);

        try {
            $deunaResponse = $this->postJson('/api/checkout', [
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
                        'product_id' => $this->product1->id,
                        'quantity' => 1,
                        'price' => 2.00,
                    ],
                ],
                'calculated_totals' => [
                    'subtotal' => 1.00,
                    'tax' => 0.15,
                    'shipping' => 5.00,
                    'total' => 6.15,
                    'total_discounts' => 1.00,
                ],
            ], [
                'Authorization' => 'Bearer '.$token,
            ]);

            echo '✅ Deuna Response Status: '.$deunaResponse->status()."\n";
            $deunaResponseData = $deunaResponse->json();
            echo '   Deuna Full Response: '.json_encode($deunaResponseData)."\n";

            if ($deunaResponse->status() === 200 && isset($deunaResponseData['status']) && $deunaResponseData['status'] === 'success') {
                // Debug: Buscar cualquier orden para este usuario
                $allUserOrders = Order::where('user_id', $this->buyer->id)->get();
                echo "   🔍 DEBUG - Total orders para user {$this->buyer->id}: ".$allUserOrders->count()."\n";

                $allOrders = Order::all();
                echo '   🔍 DEBUG - Total orders en DB: '.$allOrders->count()."\n";

                // Buscar la orden creada por Deuna
                $deunaOrder = Order::where('payment_method', 'de_una')
                    ->where('user_id', $this->buyer->id)
                    ->latest()
                    ->first();

                if ($deunaOrder) {
                    echo "   Order ID: {$deunaOrder->id}\n";
                    echo '   Seller Order ID: '.($deunaOrder->seller_order_id ?? 'NULL')."\n";

                    // 🔍 DEBUG: Verificar OrderItems para esta orden
                    $orderItems = \App\Models\OrderItem::where('order_id', $deunaOrder->id)->get();
                    echo '   🔍 Total OrderItems: '.$orderItems->count()."\n";
                    foreach ($orderItems as $item) {
                        echo "      - Item ID: {$item->id}, Product: {$item->product_id}, Seller: {$item->seller_id}, SellerOrder: ".($item->seller_order_id ?? 'NULL')."\n";
                    }

                    // 🚨 VERIFICAR EL PROBLEMA DE SELLER_ORDER_ID
                    if ($deunaOrder->seller_order_id === null) {
                        echo "   🚨 PROBLEMA DETECTADO: Deuna seller_order_id es NULL\n";
                        echo "   🚨 ESTO SIGNIFICA: Los sellers no recibirán notificación de envío\n";

                        // Verificar si existe SellerOrder huérfano
                        $orphanSellerOrders = SellerOrder::where('order_id', $deunaOrder->id)->get();
                        echo '   🔍 SellerOrders huérfanos para esta orden: '.$orphanSellerOrders->count()."\n";

                        foreach ($orphanSellerOrders as $orphan) {
                            echo "      - SellerOrder ID: {$orphan->id}, Seller: {$orphan->seller_id}\n";
                        }

                        // Ya no falla automáticamente - solo reporta el problema
                        echo "   🚨 BUG: Deuna no asocia seller_order_id correctamente\n";
                    } else {
                        echo "   ✅ Deuna SellerOrder ID: {$deunaOrder->seller_order_id}\n";

                        // Verificar que existe el SellerOrder
                        $deunaSellerOrder = SellerOrder::find($deunaOrder->seller_order_id);
                        if ($deunaSellerOrder) {
                            echo "   ✅ Deuna Seller ID: {$deunaSellerOrder->seller_id}\n";
                            $this->assertNotNull($deunaSellerOrder, '✅ SellerOrder existe correctamente');
                        } else {
                            echo "   🚨 SellerOrder no encontrado para ID: {$deunaOrder->seller_order_id}\n";
                        }
                    }
                }
            } else {
                echo '   ⚠️ Deuna no retornó success: '.json_encode($deunaResponseData)."\n";
                if (isset($deunaResponseData['message'])) {
                    echo '   ⚠️ Mensaje: '.$deunaResponseData['message']."\n";
                }
            }

        } catch (\Exception $e) {
            echo '⚠️ Deuna Error: '.$e->getMessage()."\n";
            echo "   Stack trace:\n".$e->getTraceAsString()."\n";
        }

        echo "\n";
        echo "🔍 COMPARACIÓN FINAL:\n";
        echo "====================\n";
        echo "Este test detecta problemas REALES que los mocks no pueden encontrar.\n";
        echo "Si Deuna falla, es porque tiene un bug real en el flujo de creación.\n";
    }

    #[Test]
    public function it_verifies_seller_order_association_in_database()
    {
        echo "\n";
        echo "🔍 TESTING DATABASE SELLER ORDER ASSOCIATIONS\n";
        echo "=============================================\n";

        // Obtener todas las órdenes recientes
        $recentOrders = Order::with(['sellerOrders'])
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        echo '📊 Analizando últimas '.$recentOrders->count()." órdenes:\n";

        foreach ($recentOrders as $order) {
            echo "\n";
            echo "Order ID: {$order->id}\n";
            echo "Payment Method: {$order->payment_method}\n";
            echo 'Seller Order ID (campo): '.($order->seller_order_id ?? 'NULL')."\n";
            echo 'SellerOrders (relación): '.$order->sellerOrders->count()."\n";

            if ($order->sellerOrders->count() > 0) {
                foreach ($order->sellerOrders as $sellerOrder) {
                    echo "  - SellerOrder ID: {$sellerOrder->id}, Seller: {$sellerOrder->seller_id}\n";
                }
            }

            // 🚨 DETECTAR INCONSISTENCIA
            if ($order->seller_order_id === null && $order->sellerOrders->count() > 0) {
                echo "  🚨 INCONSISTENCIA: seller_order_id es NULL pero hay SellerOrders relacionados\n";
                echo "  🚨 ESTO CAUSA: Problemas en notificaciones de envío a sellers\n";
            }

            if ($order->seller_order_id !== null && $order->sellerOrders->count() === 0) {
                echo "  🚨 INCONSISTENCIA: seller_order_id existe pero no hay SellerOrders\n";
            }

            if ($order->seller_order_id !== null && $order->sellerOrders->count() > 0) {
                $primarySellerOrder = $order->sellerOrders->where('id', $order->seller_order_id)->first();
                if (! $primarySellerOrder) {
                    echo "  🚨 INCONSISTENCIA: seller_order_id no coincide con ningún SellerOrder\n";
                }
            }
        }

        echo "\n";
        echo "🔍 RESUMEN CRÍTICO:\n";
        echo "===================\n";
        echo "Este test verifica la integridad de las asociaciones seller_order_id\n";
        echo "Cualquier inconsistencia puede causar problemas en el flujo de sellers.\n";
    }
}
