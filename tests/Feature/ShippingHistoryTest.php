<?php

namespace Tests\Feature;

use App\Domain\ValueObjects\ShippingStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shipping;
use App\Models\ShippingHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Shipping $shipping;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create product
        $product = Product::factory()->create([
            'name' => 'Shipping Test Product',
            'price' => 150,
            'stock' => 10,
        ]);

        // Create order
        $order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total' => 150,
            'status' => 'paid',
        ]);

        // Create order item
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 150,
            'subtotal' => 150,
        ]);

        // Create shipping
        $this->shipping = Shipping::factory()->create([
            'order_id' => $order->id,
            'tracking_number' => Shipping::generateTrackingNumber(),
            'status' => ShippingStatus::PENDING,
        ]);

        // Manually create shipping history entries
        $statuses = [
            ShippingStatus::PICKED_UP,
            ShippingStatus::IN_TRANSIT,
            ShippingStatus::OUT_FOR_DELIVERY,
            ShippingStatus::DELIVERED,
        ];

        foreach ($statuses as $index => $status) {
            ShippingHistory::create([
                'shipping_id' => $this->shipping->id,
                'status' => $status,
                'status_description' => ShippingStatus::getDescription($status),
                'location' => "Location {$index}",
                'details' => "Details for {$status}",
                'timestamp' => now()->addHours($index * 5),
            ]);
        }

        // Update final shipping status
        $this->shipping->update(['status' => ShippingStatus::DELIVERED]);

        // Authenticate
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $this->token = $response->json('access_token');
    }

    #[Test]
    public function it_debugs_shipping_history_entries()
    {
        // Fetch shipping history manually
        $historyEntries = ShippingHistory::where('shipping_id', $this->shipping->id)
            ->orderBy('timestamp', 'asc')
            ->get();

        // Debug: Print out the history entries
        Log::info(
            'Shipping History Entries:',
            $historyEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'shipping_id' => $entry->shipping_id,
                    'status' => $entry->status,
                    'timestamp' => $entry->timestamp,
                ];
            })->toArray()
        );

        // Fetch shipping history via API
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/shipping/'.$this->shipping->tracking_number.'/history');

        $response->assertStatus(200);

        // Debug: Print out the API response
        Log::info(
            'Shipping History API Response:',
            $response->json('data.history')
        );

        // Verify the number of history entries
        $response->assertJsonCount(4, 'data.history');

        // Verify the exact sequence of statuses
        $expectedStatuses = [
            ShippingStatus::PICKED_UP,
            ShippingStatus::IN_TRANSIT,
            ShippingStatus::OUT_FOR_DELIVERY,
            ShippingStatus::DELIVERED,
        ];

        // Check each status in the history
        foreach ($expectedStatuses as $index => $expectedStatus) {
            $response->assertJsonPath("data.history.{$index}.status", $expectedStatus);
        }
    }

    #[Test]
    public function it_checks_shipping_history_model_relationship()
    {
        // Verify shipping history entries exist
        $historyCount = ShippingHistory::where('shipping_id', $this->shipping->id)->count();
        $this->assertEquals(4, $historyCount, "Expected 4 history entries, found {$historyCount}");

        // Verify relationship works
        $shipping = Shipping::find($this->shipping->id);
        $historyViaRelation = $shipping->history()->orderBy('timestamp', 'asc')->get();

        $this->assertCount(4, $historyViaRelation, 'Relationship should return 4 history entries');

        // Debug: Print out the history via relationship
        Log::info(
            'Shipping History via Relationship:',
            $historyViaRelation->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'status' => $entry->status,
                    'timestamp' => $entry->timestamp,
                ];
            })->toArray()
        );
    }
}
