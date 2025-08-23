<?php

namespace Tests\Feature;

use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Events\FeedbackCreated;
use App\Events\RatingCreated;
use App\Events\SellerRankChanged;
use App\Events\SellerStrikeAdded;
use App\Events\ShippingDelayed;
use App\Infrastructure\Services\NotificationService;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\Shipping;
use App\Models\User;
use App\Models\UserStrike;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @var User */
    protected $adminUser;

    /** @var Admin */
    protected $admin;

    /** @var User */
    protected $user;

    /** @var User */
    protected $sellerUser;

    /** @var Seller */
    protected $seller;

    /** @var Product */
    protected $product;

    /** @var NotificationService */
    protected $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario administrador
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin = Admin::factory()->create([
            'user_id' => $this->adminUser->id,
            'status' => 'active',
        ]);

        // Crear usuario normal
        $this->user = User::factory()->create();

        // Crear vendedor
        $this->sellerUser = User::factory()->create(['email' => 'seller@test.com']);
        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'store_name' => 'Test Store',
            'status' => 'active',
        ]);

        // Crear categoría
        $category = Category::factory()->create(['name' => 'Electronics']);

        // Crear un producto
        $this->product = Product::factory()->create([
            'user_id' => $this->sellerUser->id,
            'category_id' => $category->id,
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Obtener servicio de notificaciones
        $this->notificationService = app(NotificationService::class);
    }

    private function generateTokenForUser(User $user)
    {
        return \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
    }

    #[Test]
    public function it_notifies_admin_of_new_feedback()
    {
        // Crear feedback
        $feedback = Feedback::factory()->create([
            'user_id' => $this->user->id,
            'title' => 'Test Feedback',
            'description' => 'Admin should receive notification',
        ]);

        // Disparar evento
        event(new FeedbackCreated($feedback->id));

        // Verificar notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_ADMIN_FEEDBACK,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_ADMIN_FEEDBACK)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($feedback->id, $data['feedback_id']);
        $this->assertEquals($this->user->id, $data['user_id']);
    }

    #[Test]
    public function it_notifies_admin_of_seller_strike()
    {
        // Crear strike
        $strike = UserStrike::create([
            'user_id' => $this->sellerUser->id,
            'reason' => 'Test Strike',
        ]);

        // Disparar evento
        event(new SellerStrikeAdded($strike->id, $this->sellerUser->id));

        // Verificar notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_ADMIN_SELLER_STRIKE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_ADMIN_SELLER_STRIKE)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($strike->id, $data['strike_id']);
        $this->assertEquals($this->sellerUser->id, $data['user_id']);
        $this->assertEquals($this->seller->id, $data['seller_id']);
    }

    #[Test]
    public function it_notifies_admin_of_low_rating()
    {
        // Crear calificación baja
        $rating = Rating::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'rating' => 0.5,
            'comment' => 'Very bad experience',
            'type' => 'user_to_seller',
            'status' => 'approved',
        ]);

        // Disparar evento
        event(new RatingCreated($rating->id, $rating->type));

        // Verificar notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_LOW_RATING,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_LOW_RATING)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($rating->id, $data['rating_id']);
        $this->assertEquals($this->seller->id, $data['seller_id']);
        $this->assertEquals($this->user->id, $data['user_id']);
        $this->assertEquals(0.5, $data['rating']);
    }

    #[Test]
    public function it_notifies_admin_of_seller_rank_change()
    {
        // Disparar evento
        event(new SellerRankChanged($this->seller->id, 'bronze', 'silver'));

        // Verificar notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_SELLER_RANK_UP,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_SELLER_RANK_UP)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($this->seller->id, $data['seller_id']);
        $this->assertEquals('bronze', $data['old_level']);
        $this->assertEquals('silver', $data['new_level']);
    }

    #[Test]
    public function it_notifies_admin_of_shipping_delay()
    {
        // Crear una orden
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'status' => 'processing',
        ]);

        // Crear un envío
        $shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'status' => 'processing',
            'tracking_number' => 'TR12345678',
            'last_updated' => now()->subDays(5), // 5 días sin actualizar
        ]);

        // Disparar evento
        event(new ShippingDelayed($shipping->id, $this->seller->id, 5));

        // Verificar notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_SHIPPING_DELAY_ADMIN,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_SHIPPING_DELAY_ADMIN)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($shipping->id, $data['shipping_id']);
        $this->assertEquals($order->id, $data['order_id']);
        $this->assertEquals($this->seller->id, $data['seller_id']);
        $this->assertEquals($shipping->tracking_number, $data['tracking_number']);
    }

    #[Test]
    public function it_notifies_admin_of_out_of_stock_product()
    {
        // Cambiar stock del producto a 0
        $this->product->stock = 0;
        $this->product->save();

        // Notificar directamente usando el servicio
        $notifications = $this->notificationService->notifyAdminOutOfStock($this->product);

        // Verificar que se crearon notificaciones
        $this->assertGreaterThan(0, count($notifications));

        // Verificar notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_PRODUCT_OUT_OF_STOCK,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_PRODUCT_OUT_OF_STOCK)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($this->product->id, $data['product_id']);
        $this->assertEquals($this->sellerUser->id, $data['user_id']);
    }

    #[Test]
    public function it_blocks_seller_and_notifies_admin_after_three_strikes()
    {
        // Crear tres strikes para el vendedor
        for ($i = 0; $i < 3; $i++) {
            $strike = UserStrike::create([
                'user_id' => $this->sellerUser->id,
                'reason' => 'Strike #'.($i + 1),
            ]);

            // Disparar evento de strike añadido para cada uno
            event(new SellerStrikeAdded($strike->id, $this->sellerUser->id));
        }

        // Verificar que el usuario fue bloqueado
        $this->sellerUser->refresh();
        $this->assertTrue($this->sellerUser->is_blocked);

        // Verificar que el vendedor está inactivo
        $this->seller->refresh();
        $this->assertEquals('inactive', $this->seller->status);

        // Verificar que se creó la notificación de cuenta bloqueada para admin
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_SELLER_BLOCKED,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_SELLER_BLOCKED)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($this->sellerUser->id, $data['user_id']);
        $this->assertEquals($this->seller->id, $data['seller_id']);
        $this->assertTrue($data['is_blocked']);
    }

    #[Test]
    public function it_can_mark_admin_notifications_as_read()
    {
        // Crear notificaciones para el admin
        for ($i = 0; $i < 3; $i++) {
            $this->notificationService->createNotification(
                $this->adminUser->id,
                Notification::TYPE_ADMIN_FEEDBACK,
                "Test Admin Notification {$i}",
                "This is admin test notification {$i}"
            );
        }

        // Verificar que hay 3 notificaciones sin leer
        $this->assertEquals(3, Notification::where('user_id', $this->adminUser->id)->where('read', 0)->count());

        // Obtener una notificación para marcarla como leída
        $notification = Notification::where('user_id', $this->adminUser->id)->first();

        // Obtener el repositorio
        $repository = app(NotificationRepositoryInterface::class);

        // Marcar como leída
        $result = $repository->markAsRead($notification->id);

        // Verificar que se marcó como leída
        $this->assertTrue($result);
        $this->assertEquals(2, Notification::where('user_id', $this->adminUser->id)->where('read', 0)->count());
        $this->assertEquals(1, Notification::where('user_id', $this->adminUser->id)->where('read', 1)->count());
    }

    #[Test]
    public function it_can_test_admin_api_endpoints()
    {
        // Crear notificaciones para el admin
        for ($i = 0; $i < 3; $i++) {
            $this->notificationService->createNotification(
                $this->adminUser->id,
                Notification::TYPE_ADMIN_FEEDBACK,
                "API Test Admin Notification {$i}",
                "This is API test admin notification {$i}"
            );
        }

        // Actuar como el admin
        $this->actingAs($this->adminUser);

        // Probar endpoint para obtener notificaciones
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->adminUser),
        ])->getJson('/api/admin/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'notifications',
                    'unread_count',
                    'total',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'total' => 3,
                ],
            ]);

        // Probar endpoint para obtener conteo de no leídas
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->adminUser),
        ])->getJson('/api/admin/notifications/count');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'unread_count',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'unread_count' => 3,
                ],
            ]);

        // Obtener una notificación para marcarla como leída
        $notification = Notification::where('user_id', $this->adminUser->id)->first();

        // Probar endpoint para marcar como leída
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->adminUser),
        ])->postJson("/api/admin/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'unread_count',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'unread_count' => 2,
                ],
            ]);

        // Probar endpoint para marcar todas como leídas
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->adminUser),
        ])->postJson('/api/admin/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'unread_count',
                ],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'unread_count' => 0,
                ],
            ]);
    }

    #[Test]
    public function it_sends_daily_sales_notifications_to_admins()
    {
        // Simular datos de ventas diarias
        $salesData = [
            'date' => now()->toDateString(),
            'total' => 1250.75,
            'count' => 5,
            'by_seller' => [
                $this->seller->id => [
                    'count' => 5,
                    'total' => 1250.75,
                ],
            ],
        ];

        // Enviar notificación a todos los administradores
        $notifications = $this->notificationService->notifyAdminDailySales($salesData);

        // Verificar que se crearon notificaciones
        $this->assertGreaterThan(0, count($notifications));

        // Verificar notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->adminUser->id,
            'type' => Notification::TYPE_DAILY_SALES,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->adminUser->id)
            ->where('type', Notification::TYPE_DAILY_SALES)
            ->first();

        // Verificar datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals(now()->toDateString(), $data['date']);
        $this->assertEquals(1250.75, $data['total']);
        $this->assertEquals(5, $data['count']);
    }
}
