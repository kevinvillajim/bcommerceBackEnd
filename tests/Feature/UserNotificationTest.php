<?php

namespace Tests\Feature;

use App\Domain\Entities\NotificationEntity;
use App\Domain\Repositories\NotificationRepositoryInterface;
use App\Events\FeedbackReviewed;
use App\Events\MessageSent;
use App\Events\OrderStatusChanged;
use App\Events\ProductUpdated;
use App\Events\RatingCreated;
use App\Events\ShippingStatusUpdated;
use App\Infrastructure\Services\NotificationService;
use App\Models\Admin;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Chat;
use App\Models\Feedback;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\Shipping;
use App\Models\ShoppingCart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserNotificationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @var User */
    protected $user;

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

        // Crear administrador
        $adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin = Admin::factory()->create(['user_id' => $adminUser->id]);

        // Crear categoría
        $category = Category::factory()->create(['name' => 'Electronics']);

        // Crear un vendedor
        $sellerUser = User::factory()->create(['email' => 'seller@test.com']);
        $seller = Seller::factory()->create(['user_id' => $sellerUser->id]);

        // Crear un producto
        $this->product = Product::factory()->create([
            'user_id' => $sellerUser->id,
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
    public function it_can_create_a_notification()
    {
        // Crear una notificación manualmente
        $notification = $this->notificationService->createNotification(
            $this->user->id,
            Notification::TYPE_ORDER_STATUS,
            'Prueba de notificación',
            'Este es un mensaje de prueba',
            ['test' => 'data']
        );

        $this->assertInstanceOf(NotificationEntity::class, $notification);
        $this->assertEquals($this->user->id, $notification->getUserId());
        $this->assertEquals('Prueba de notificación', $notification->getTitle());
        $this->assertEquals('Este es un mensaje de prueba', $notification->getMessage());
        $this->assertEquals(['test' => 'data'], $notification->getData());
        $this->assertFalse($notification->isRead());

        // Verificar que la notificación está en la base de datos
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_ORDER_STATUS,
            'title' => 'Prueba de notificación',
            'message' => 'Este es un mensaje de prueba',
            'read' => 0,
        ]);
    }

    #[Test]
    public function it_creates_notification_when_order_status_changes()
    {
        // Crear una orden
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // Disparar evento de cambio de estado
        event(new OrderStatusChanged($order->id, 'pending', 'processing'));

        // Verificar que se creó la notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_ORDER_STATUS,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_ORDER_STATUS)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($order->id, $data['order_id']);
        $this->assertEquals('pending', $data['current_status']);
    }

    #[Test]
    public function it_creates_notification_when_feedback_is_reviewed()
    {
        // Crear un feedback
        $feedback = Feedback::factory()->create([
            'user_id' => $this->user->id,
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

        // Verificar que se creó la notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_FEEDBACK_RESPONSE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_FEEDBACK_RESPONSE)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($feedback->id, $data['feedback_id']);
        $this->assertEquals('approved', $data['status']);
    }

    #[Test]
    public function it_creates_notification_when_new_message_is_received()
    {
        // Crear un vendedor si no existe
        $sellerUser = User::where('email', 'seller@test.com')->first()->id;
        $seller = Seller::where('user_id', $sellerUser)->first();

        // Crear una chat
        $chat = Chat::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $seller->id,
            'product_id' => $this->product->id,
        ]);

        // Crear un mensaje
        $message = Message::factory()->create([
            'chat_id' => $chat->id,
            'sender_id' => $sellerUser,  // El vendedor envía el mensaje
            'content' => 'Hello, I am interested in your inquiry',
        ]);

        // Disparar evento de mensaje enviado
        event(new MessageSent($message->id, $chat->id, $sellerUser));

        // Verificar que se creó la notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_NEW_MESSAGE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_NEW_MESSAGE)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($chat->id, $data['chat_id']);
        $this->assertEquals($message->id, $data['message_id']);
        $this->assertEquals($sellerUser, $data['sender_id']);
    }

    #[Test]
    public function it_creates_notification_when_product_price_changes()
    {
        // Crear un carrito para el usuario con el producto
        $cart = ShoppingCart::factory()->create([
            'user_id' => $this->user->id,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 99.99,
            'subtotal' => 99.99,
        ]);

        // Guardar el precio antiguo y actualizar el producto
        $oldPrice = $this->product->price;
        $this->product->price = 79.99;  // Bajar el precio
        $this->product->save();

        // Preparar los cambios
        $changes = [
            'price' => [
                'old' => $oldPrice,
                'new' => 79.99,
            ],
        ];

        // Disparar evento de actualización de producto
        event(new ProductUpdated($this->product->id, $changes));

        // Verificar que se creó la notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_PRODUCT_UPDATE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_PRODUCT_UPDATE)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($this->product->id, $data['product_id']);
        $this->assertEquals('cart', $data['reason']);  // Notificado porque está en el carrito
        $this->assertTrue($data['price_decreased']);
    }

    #[Test]
    public function it_creates_notification_when_shipping_status_changes()
    {
        // Crear una orden
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'processing',
        ]);

        // Crear un envío
        $shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'status' => 'processing',
            'tracking_number' => 'TR12345678',
        ]);

        // Actualizar estado de envío
        $previousStatus = $shipping->status;
        $shipping->status = 'in_transit';
        $shipping->save();

        // Disparar evento de actualización de envío
        event(new ShippingStatusUpdated($shipping->id, $previousStatus, $shipping->status));

        // Verificar que se creó la notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_SHIPPING_UPDATE,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_SHIPPING_UPDATE)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($shipping->order_id, $data['order_id']);
        $this->assertEquals($shipping->tracking_number, $data['tracking_number']);
        $this->assertEquals('processing', $data['previous_status']);
        $this->assertEquals('in_transit', $data['status']);
    }

    #[Test]
    public function it_creates_notification_when_user_receives_rating()
    {
        // Crear un vendedor si no existe
        $sellerUser = User::where('email', 'seller@test.com')->first()->id;
        $seller = Seller::where('user_id', $sellerUser)->first();

        // Crear una orden
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
        ]);

        // Crear una valoración de vendedor a usuario
        $rating = Rating::factory()->create([
            'user_id' => $this->user->id,
            'seller_id' => $seller->id,
            'order_id' => $order->id,
            'rating' => 4.5,
            'title' => 'Great customer',
            'comment' => 'It was a pleasure doing business with you',
            'type' => 'seller_to_user',
            'status' => 'approved',
        ]);

        // Disparar evento de valoración creada
        event(new RatingCreated($rating->id, $rating->type));

        // Verificar que se creó la notificación
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_RATING_RECEIVED,
        ]);

        // Obtener la notificación
        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_RATING_RECEIVED)
            ->first();

        // Verificar los datos de la notificación
        $data = json_decode($notification->data, true);
        $this->assertEquals($rating->id, $data['rating_id']);
        $this->assertEquals($rating->rating, $data['rating_value']);
        $this->assertEquals($rating->seller_id, $data['seller_id']);
    }

    #[Test]
    public function it_can_mark_notification_as_read()
    {
        // Crear una notificación
        $notification = $this->notificationService->createNotification(
            $this->user->id,
            Notification::TYPE_ORDER_STATUS,
            'Test Notification',
            'This is a test notification'
        );

        // Verificar que está sin leer
        $this->assertFalse($notification->isRead());

        // Obtener el repositorio
        $repository = app(NotificationRepositoryInterface::class);

        // Marcar como leída
        $result = $repository->markAsRead($notification->getId());

        // Verificar que se marcó como leída
        $this->assertTrue($result);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->getId(),
            'read' => 1,
        ]);
    }

    #[Test]
    public function it_can_mark_all_notifications_as_read()
    {
        // Crear varias notificaciones
        for ($i = 0; $i < 3; $i++) {
            $this->notificationService->createNotification(
                $this->user->id,
                Notification::TYPE_ORDER_STATUS,
                "Test Notification {$i}",
                "This is test notification {$i}"
            );
        }

        // Verificar que hay 3 notificaciones sin leer
        $this->assertEquals(3, Notification::where('user_id', $this->user->id)->where('read', 0)->count());

        // Obtener el repositorio
        $repository = app(NotificationRepositoryInterface::class);

        // Marcar todas como leídas
        $result = $repository->markAllAsRead($this->user->id);

        // Verificar que se marcaron como leídas
        $this->assertTrue($result);
        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->where('read', 0)->count());
        $this->assertEquals(3, Notification::where('user_id', $this->user->id)->where('read', 1)->count());
    }

    #[Test]
    public function it_can_count_unread_notifications()
    {
        // Crear notificaciones (2 no leídas, 1 leída)
        for ($i = 0; $i < 2; $i++) {
            $this->notificationService->createNotification(
                $this->user->id,
                Notification::TYPE_ORDER_STATUS,
                "Unread Notification {$i}",
                "This is unread notification {$i}"
            );
        }

        $notification = $this->notificationService->createNotification(
            $this->user->id,
            Notification::TYPE_ORDER_STATUS,
            'Read Notification',
            'This is a read notification'
        );

        // Marcar una como leída
        $repository = app(NotificationRepositoryInterface::class);
        $repository->markAsRead($notification->getId());

        // Contar notificaciones no leídas
        $count = $repository->countUnreadByUserId($this->user->id);

        // Verificar el conteo
        $this->assertEquals(2, $count);
    }

    #[Test]
    public function it_can_delete_a_notification()
    {
        // Crear una notificación
        $notification = $this->notificationService->createNotification(
            $this->user->id,
            Notification::TYPE_ORDER_STATUS,
            'Notification to Delete',
            'This notification will be deleted'
        );

        // Eliminar la notificación
        $repository = app(NotificationRepositoryInterface::class);
        $result = $repository->delete($notification->getId());

        // Verificar que se eliminó
        $this->assertTrue($result);
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->getId(),
        ]);
    }

    #[Test]
    public function it_can_test_api_endpoints()
    {
        // Crear notificaciones para el usuario
        for ($i = 0; $i < 3; $i++) {
            $this->notificationService->createNotification(
                $this->user->id,
                Notification::TYPE_ORDER_STATUS,
                "API Test Notification {$i}",
                "This is API test notification {$i}"
            );
        }

        // Actuar como el usuario
        $this->actingAs($this->user);

        // Probar endpoint para obtener notificaciones
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->user),
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
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->user),
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
        $notification = Notification::where('user_id', $this->user->id)->first();

        // Probar endpoint para marcar como leída
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->user),
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

        // Probar endpoint para marcar todas como leídas
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->user),
        ])->postJson('/api/notifications/read-all');
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

        // Probar endpoint para eliminar una notificación
        $notification = Notification::where('user_id', $this->user->id)->first();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->generateTokenForUser($this->user),
        ])->deleteJson("/api/notifications/{$notification->id}");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
            ])
            ->assertJson([
                'status' => 'success',
            ]);

        // Verificar que se eliminó
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }
}
