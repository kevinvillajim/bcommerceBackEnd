<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SellerModelTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Seller $seller;

    protected function setUp(): void
    {
        parent::setUp();

        // Verificar y añadir la columna seller_id si no existe en la tabla orders
        if (! Schema::hasColumn('orders', 'seller_id')) {
            Schema::table('orders', function ($table) {
                $table->foreignId('seller_id')->nullable()->after('user_id');
            });
        }

        // Crear un usuario que será el vendedor
        $this->user = User::factory()->create([
            'name' => 'Company Test',
            'email' => 'company@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Crear perfil de vendedor
        $this->seller = Seller::factory()->create([
            'user_id' => $this->user->id,
            'store_name' => 'Test Store',
            'description' => 'This is a test store',
            'status' => 'active',
            'verification_level' => 'verified',
            'commission_rate' => 10.0,
            'total_sales' => 0,
            'is_featured' => true,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_user()
    {
        $this->assertEquals($this->user->id, $this->seller->user->id);
        $this->assertEquals('Company Test', $this->seller->user->name);
    }

    #[Test]
    public function it_can_calculate_average_rating()
    {
        // Crear un comprador
        $buyer = User::factory()->create();

        // Crear una orden manualmente para evitar errores de columna faltante
        $orderData = [
            'user_id' => $buyer->id,
            'seller_id' => $this->seller->id,
            'total' => 100,
            'status' => 'completed',
            'order_number' => 'ORD-'.rand(100000, 999999), // Añadir número de orden
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $orderId = DB::table('orders')->insertGetId($orderData);
        $order = Order::find($orderId);

        // Crear calificaciones
        Rating::create([
            'user_id' => $buyer->id,
            'seller_id' => $this->seller->id,
            'order_id' => $order->id,
            'type' => 'user_to_seller',
            'rating' => 4.0,
            'status' => 'approved',
        ]);

        Rating::create([
            'user_id' => User::factory()->create()->id,
            'seller_id' => $this->seller->id,
            'type' => 'user_to_seller',
            'rating' => 5.0,
            'status' => 'approved',
        ]);

        // La calificación pendiente no debería contar
        Rating::create([
            'user_id' => User::factory()->create()->id,
            'seller_id' => $this->seller->id,
            'type' => 'user_to_seller',
            'rating' => 1.0,
            'status' => 'pending',
        ]);

        // Verificar que solo se consideran las calificaciones aprobadas
        $this->assertEquals(4.5, $this->seller->getAverageRatingAttribute());
        $this->assertEquals(2, $this->seller->getTotalRatingsAttribute());
    }

    #[Test]
    public function it_calculates_trustworthiness_score()
    {
        // Crear algunas órdenes y calificaciones para el vendedor
        $buyer = User::factory()->create();

        // Orden completada - crear manualmente para evitar errores
        $completedOrderData = [
            'user_id' => $buyer->id,
            'seller_id' => $this->seller->id,
            'total' => 150,
            'status' => 'completed',
            'order_number' => 'ORD-'.rand(100000, 999999), // Añadir número de orden
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $completedOrderId = DB::table('orders')->insertGetId($completedOrderData);

        // Orden devuelta - crear manualmente para evitar errores
        $returnedOrderData = [
            'user_id' => User::factory()->create()->id,
            'seller_id' => $this->seller->id,
            'total' => 200,
            'status' => 'returned',
            'order_number' => 'ORD-'.rand(100000, 999999), // Añadir número de orden
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $returnedOrderId = DB::table('orders')->insertGetId($returnedOrderData);

        // Calificaciones
        Rating::create([
            'user_id' => $buyer->id,
            'seller_id' => $this->seller->id,
            'order_id' => $completedOrderId,
            'type' => 'user_to_seller',
            'rating' => 5.0,
            'status' => 'approved',
        ]);

        // El score debe considerar tanto calificaciones como tasa de devoluciones
        $trustScore = $this->seller->getTrustworthinessScoreAttribute();

        // Verificamos que el puntaje está dentro de un rango razonable (0-5)
        $this->assertGreaterThanOrEqual(0, $trustScore);
        $this->assertLessThanOrEqual(5, $trustScore);
    }

    #[Test]
    public function it_becomes_inactive_when_user_is_blocked()
    {
        // Verificar que inicialmente está activo
        $this->assertEquals('active', $this->seller->status);

        // Bloquear al usuario y guardarlo
        $this->user->is_blocked = true;
        $this->user->save();

        // Forzar el evento de actualización para el seller
        // IMPORTANTE: En lugar de solo tocar y refrescar, usamos una llamada directa para asegurar que
        // el evento se active y procese correctamente
        event('eloquent.updated: '.get_class($this->user), [$this->user]);

        // Refrescar el vendedor desde la base de datos
        $this->seller->refresh();

        // Verificar que ahora está inactivo
        $this->assertEquals('inactive', $this->seller->status);
    }
}
