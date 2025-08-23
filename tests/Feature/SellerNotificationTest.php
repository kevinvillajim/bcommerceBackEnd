<?php

namespace Tests\Feature;

use App\Events\FeedbackReviewed;
use App\Events\MessageSent;
use App\Events\OrderPaid;
use App\Events\ProductStockUpdated;
use App\Events\RatingCreated;
use App\Events\SellerAccountBlocked;
use App\Events\SellerStrikeAdded;
use App\Events\ShippingDelayed;
use App\Infrastructure\Services\NotificationService;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Chat;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
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

class SellerNotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @var User */
    protected $user;

    /** @var Seller */
    protected $seller;

    /** @var User */
    protected $sellerUser;

    /** @var Admin */
    protected $admin;

    /** @var Product */
    protected $product;

    /** @var NotificationService */
    protected $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario de prueba
        $this->user = User::factory()->create();

        // Crear usuario vendedor
        $this->sellerUser = User::factory()->create(['email' => 'seller@test.com']);
        $this->seller = Seller::factory()->create([
            'user_id' => $this->sellerUser->id,
            'store_name' => 'Test Store',
            'status' => 'active',
        ]);

        // Crear administrador
        $adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin = Admin::factory()->create(['user_id' => $adminUser->id]);

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

        // Obtener el servicio de notificaciones
        $this->notificationService = app(NotificationService::class);
    }

    private function generateTokenForUser(User $user)
    {
        return \Tymon\JWTAuth\Facades\JWTAuth::fromUser($user);
    }

    #[Test]
    public function it_notifies_seller_of_new_message()
    {
        // Crear una chat
        $chat = Chat::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
        ]);

        // Crear un mensaje
        $message = Message::factory()->create([
            'chat_id' => $chat->id,
            'sender_id' => $this->user->id,
            'content' => 'Hello, I am interested in your product',
        ]);

        // Disparar evento de mensaje enviado
        event(new MessageSent($message->id, $chat->id, $this->user->id));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_NEW_MESSAGE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_NEW_MESSAGE)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($chat->id, $data['chat_id']);
        $this->assertEquals($message->id, $data['message_id']);
        $this->assertEquals($this->user->id, $data['sender_id']);
    }

    #[Test]
    public function it_notifies_seller_of_new_order()
    {
        // Crear una orden
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'status' => 'paid',
            'total' => 199.99,
        ]);

        // Añadir items a la orden
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 99.99,
            'subtotal' => 199.98,
        ]);

        // Disparar evento de orden pagada
        event(new OrderPaid($order->id, $this->seller->id, $order->total));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_NEW_ORDER,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_NEW_ORDER)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($order->id, $data['order_id']);
        $this->assertEquals($order->order_number, $data['order_number']);
        $this->assertEquals($order->total, $data['total']);
    }

    #[Test]
    public function it_notifies_seller_of_feedback_response()
    {
        // Crear un feedback del vendedor
        $feedback = Feedback::factory()->create([
            'user_id' => $this->sellerUser->id,
            'title' => 'Test Feedback',
            'description' => 'This is a test feedback',
            'status' => 'pending',
        ]);

        // Actualizar el feedback (simulando la revisión por un admin)
        $feedback->status = 'approved';
        $feedback->admin_notes = 'Thank you for your feedback';
        $feedback->reviewed_by = $this->admin->id;
        $feedback->reviewed_at = now();
        $feedback->save();

        // Disparar evento de feedback revisado
        event(new FeedbackReviewed($feedback->id, $this->admin->id, 'approved'));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_FEEDBACK_RESPONSE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_FEEDBACK_RESPONSE)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($feedback->id, $data['feedback_id']);
        $this->assertEquals('approved', $data['status']);
    }

    #[Test]
    public function it_notifies_seller_of_low_stock()
    {
        // Guardar el stock antiguo y actualizar el producto
        $oldStock = $this->product->stock;
        $this->product->stock = 4; // Por debajo del umbral de 5
        $this->product->save();

        // Disparar evento de actualización de stock
        event(new ProductStockUpdated($this->product->id, $oldStock, 4));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_LOW_STOCK,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_LOW_STOCK)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($this->product->id, $data['product_id']);
        $this->assertEquals(4, $data['stock']);
    }

    #[Test]
    public function it_notifies_seller_of_shipping_delay()
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
            'last_updated' => now()->subDays(3), // 3 días sin actualizar (por encima del umbral de 2)
        ]);

        // Disparar evento de retraso en el envío
        event(new ShippingDelayed($shipping->id, $this->seller->id, 3));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_SHIPPING_DELAY,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_SHIPPING_DELAY)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($shipping->id, $data['shipping_id']);
        $this->assertEquals($order->id, $data['order_id']);
        $this->assertEquals($shipping->tracking_number, $data['tracking_number']);
    }

    #[Test]
    public function it_notifies_seller_of_strike()
    {
        // Crear un strike para el vendedor
        $strike = UserStrike::create([
            'user_id' => $this->sellerUser->id,
            'reason' => 'Inappropriate message',
        ]);

        // Disparar evento de strike añadido
        event(new SellerStrikeAdded($strike->id, $this->sellerUser->id));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_SELLER_STRIKE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_SELLER_STRIKE)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($strike->id, $data['strike_id']);
        $this->assertEquals('Inappropriate message', $data['reason']);
        $this->assertEquals(1, $data['strike_count']); // El primer strike
    }

    #[Test]
    public function it_notifies_seller_of_account_block()
    {
        // Disparar evento de cuenta bloqueada
        event(new SellerAccountBlocked($this->sellerUser->id, 'Three strikes reached'));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_ACCOUNT_BLOCKED,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_ACCOUNT_BLOCKED)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals('Three strikes reached', $data['reason']);
        $this->assertEquals($this->seller->id, $data['seller_id']);

        // Verificar que el usuario fue bloqueado
        $this->sellerUser->refresh();
        $this->assertTrue($this->sellerUser->is_blocked);

        // Verificar que el vendedor está inactivo
        $this->seller->refresh();
        $this->assertEquals('inactive', $this->seller->status);
    }

    #[Test]
    public function it_notifies_seller_when_rated_by_customer()
    {
        // Crear una orden
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'status' => 'completed',
        ]);

        // Crear una valoración de usuario a vendedor
        $rating = Rating::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $this->seller->id,
            'order_id' => $order->id,
            'rating' => 4.5,
            'title' => 'Great seller',
            'comment' => 'It was a great experience shopping with this seller',
            'type' => 'user_to_seller',
            'status' => 'approved',
        ]);

        // Disparar evento de valoración creada
        event(new RatingCreated($rating->id, $rating->type));

        // Verificar que se creó la notificación para el vendedor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_SELLER_RATED,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->sellerUser->id)
            ->where('type', Notification::TYPE_SELLER_RATED)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($rating->id, $data['rating_id']);
        $this->assertEquals($rating->rating, $data['rating_value']);
        $this->assertEquals($this->seller->id, $data['seller_id']);
    }

    #[Test]
    public function it_blocks_seller_after_three_strikes()
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

        // Verificar que se creó la notificación de cuenta bloqueada
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->sellerUser->id,
            'type' => Notification::TYPE_ACCOUNT_BLOCKED,
        ]);
    }

    #[Test]
    public function it_can_test_api_endpoints()
    {
        // Crear notificaciones para el vendedor
        for ($i = 0; $i < 3; $i++) {
            $this->notificationService->createNotification(
                $this->sellerUser->id,
                Notification::TYPE_NEW_ORDER,
                "API Test Notification {$i}",
                "This is API test notification {$i}"
            );
        }

        // Actuar como el vendedor
        $this->actingAs($this->sellerUser);

        // Probar endpoint para obtener notificaciones
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->sellerUser),
        ])->getJson('/api/notifications');
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
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->sellerUser),
        ])->getJson('/api/notifications/count');
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
        $notification = Notification::where('user_id', $this->sellerUser->id)->first();

        // Probar endpoint para marcar como leída
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->sellerUser),
        ])->postJson("/api/notifications/{$notification->id}/read");
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
    }
}
