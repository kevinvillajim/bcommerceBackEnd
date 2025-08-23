<?php

namespace Tests\Feature;

use App\Events\ProductLowStock;
use App\Events\ProductPriceChanged;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Product $product;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create a test product
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'price' => 99.99,
            'stock' => 10,
        ]);

        // Generate JWT token
        $this->token = JWTAuth::fromUser($this->user);
    }

    #[Test]
    public function user_can_toggle_favorite_status()
    {
        // 1. Add product to favorites
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->postJson('/api/favorites/toggle', [
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_favorite' => true,
                ],
            ]);

        // Verify it was added to the database
        $this->assertDatabaseHas('favorites', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        // 2. Remove product from favorites
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->postJson('/api/favorites/toggle', [
            'product_id' => $this->product->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_favorite' => false,
                ],
            ]);

        // Verify it was removed from the database
        $this->assertDatabaseMissing('favorites', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    #[Test]
    public function user_can_retrieve_favorites()
    {
        // Add two products to favorites
        Favorite::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        $product2 = Product::factory()->create(['name' => 'Second Product']);
        Favorite::create([
            'user_id' => $this->user->id,
            'product_id' => $product2->id,
        ]);

        // Get favorites list
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/favorites');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'total' => 2,
                ],
            ]);

        // Check if both products are in the response
        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);
    }

    #[Test]
    public function user_can_check_if_product_is_favorited()
    {
        // First check when it's not favorited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/favorites/product/'.$this->product->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_favorite' => false,
                ],
            ]);

        // Add to favorites
        Favorite::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);

        // Check again when it is favorited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/favorites/product/'.$this->product->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_favorite' => true,
                ],
            ]);
    }

    #[Test]
    public function user_can_update_notification_preferences()
    {
        // First add to favorites
        $favorite = Favorite::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'notify_price_change' => true,
            'notify_promotion' => true,
            'notify_low_stock' => true,
        ]);

        // Update notification preferences
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/json',
        ])->putJson('/api/favorites/'.$favorite->id.'/notifications', [
            'notify_price_change' => false,
            'notify_promotion' => true,
            'notify_low_stock' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'notify_price_change' => false,
                    'notify_promotion' => true,
                    'notify_low_stock' => false,
                ],
            ]);

        // Verify it was updated in the database
        $this->assertDatabaseHas('favorites', [
            'id' => $favorite->id,
            'notify_price_change' => false,
            'notify_promotion' => true,
            'notify_low_stock' => false,
        ]);
    }

    #[Test]
    public function user_receives_notification_when_favorited_product_price_changes()
    {
        // Add product to favorites
        Favorite::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'notify_price_change' => true,
        ]);

        // Trigger price change event
        event(new ProductPriceChanged($this->product->id, 99.99, 79.99));

        // Check if notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_PRODUCT_UPDATE,
        ]);

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_PRODUCT_UPDATE)
            ->first();

        $this->assertNotNull($notification);
        $notificationData = json_decode($notification->data, true);
        $this->assertEquals($this->product->id, $notificationData['product_id']);
        $this->assertEquals('decreased', $notificationData['price_action']);
    }

    #[Test]
    public function user_receives_notification_when_favorited_product_stock_is_low()
    {
        // Add product to favorites
        Favorite::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'notify_low_stock' => true,
        ]);

        // Update product stock to low level
        $this->product->stock = 5;
        $this->product->save();

        // Trigger low stock event
        event(new ProductLowStock($this->product->id, 5));

        // Check if notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => Notification::TYPE_LOW_STOCK,
        ]);

        $notification = Notification::where('user_id', $this->user->id)
            ->where('type', Notification::TYPE_LOW_STOCK)
            ->first();

        $this->assertNotNull($notification);
        $notificationData = json_decode($notification->data, true);
        $this->assertEquals($this->product->id, $notificationData['product_id']);
        $this->assertEquals(5, $notificationData['stock']);
    }
}
