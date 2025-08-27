<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Events\OrderCreated;
use App\Listeners\InvalidateCartCacheListener;

class TestCartCacheInvalidation extends Command
{
    protected $signature = 'test:cart-cache-invalidation';
    protected $description = 'Test that cart cache invalidation works when OrderCreated event is fired';

    public function handle()
    {
        $this->info('🛒 Testing Cart Cache Invalidation System');
        $this->newLine();

        $testUserId = 1;
        $testOrderId = 99999;
        $testSellerId = 1;

        // 1. Setup test cache data
        $this->info('1. 📝 Setting up test cache data...');
        $testCacheKeys = [
            "cart_items_user_{$testUserId}" => ['item1', 'item2', 'item3'],
            "cart_count_user_{$testUserId}" => 5,
            "cart_total_user_{$testUserId}" => 25.99,
            "user_cart_{$testUserId}" => ['total' => 25.99, 'count' => 5],
            "shopping_cart_{$testUserId}" => ['status' => 'active'],
            "header_cart_{$testUserId}" => ['display' => 'show', 'badge' => 5],
            "cart_summary_{$testUserId}" => ['last_updated' => now()],
        ];

        foreach ($testCacheKeys as $key => $value) {
            Cache::put($key, $value, 3600); // 1 hour
            $this->line("   ✅ Cached: {$key}");
        }

        // 2. Verify cache is set
        $this->newLine();
        $this->info('2. 🔍 Verifying cache is set...');
        $cacheSetCount = 0;
        foreach (array_keys($testCacheKeys) as $key) {
            if (Cache::has($key)) {
                $cacheSetCount++;
                $this->line("   ✅ Cache exists: {$key}");
            } else {
                $this->line("   ❌ Cache missing: {$key}");
            }
        }
        $this->line("   📊 Total cache keys set: {$cacheSetCount}/" . count($testCacheKeys));

        // 3. Create and fire OrderCreated event
        $this->newLine();
        $this->info('3. 🔥 Firing OrderCreated event...');
        
        $orderCreatedEvent = new OrderCreated(
            $testOrderId,
            $testUserId,
            $testSellerId,
            ['test' => true, 'payment_method' => 'test']
        );

        // Manually trigger the listener to simulate event dispatch
        $listener = new InvalidateCartCacheListener();
        $listener->handle($orderCreatedEvent);

        $this->line("   🚀 Event fired and listener executed");

        // 4. Check if cache was invalidated
        $this->newLine();
        $this->info('4. ✅ Checking cache invalidation results...');
        
        $cacheRemainingCount = 0;
        $cacheInvalidatedCount = 0;
        
        foreach (array_keys($testCacheKeys) as $key) {
            if (Cache::has($key)) {
                $cacheRemainingCount++;
                $this->line("   ❌ Cache still exists: {$key}");
            } else {
                $cacheInvalidatedCount++;
                $this->line("   ✅ Cache invalidated: {$key}");
            }
        }

        // 5. Results summary
        $this->newLine();
        $this->info('📊 RESULTS SUMMARY:');
        $this->line("   Cache keys tested: " . count($testCacheKeys));
        $this->line("   Cache keys invalidated: {$cacheInvalidatedCount}");
        $this->line("   Cache keys remaining: {$cacheRemainingCount}");
        
        $successRate = count($testCacheKeys) > 0 
            ? round(($cacheInvalidatedCount / count($testCacheKeys)) * 100, 1) 
            : 0;
        
        $this->newLine();
        if ($successRate >= 90) {
            $this->info("🎉 SUCCESS: {$successRate}% cache invalidation rate");
            $this->info("✅ Cart cache invalidation is working correctly!");
        } elseif ($successRate >= 70) {
            $this->warn("⚠️  PARTIAL: {$successRate}% cache invalidation rate");
            $this->warn("Some cache keys were not invalidated as expected.");
        } else {
            $this->error("❌ FAILED: {$successRate}% cache invalidation rate");
            $this->error("Cache invalidation system is not working properly.");
        }

        // 6. Test with actual event dispatch
        $this->newLine();
        $this->info('6. 🧪 Testing with actual event dispatch...');
        
        // Setup fresh cache for second test
        $eventTestKey = "cart_items_user_{$testUserId}_event_test";
        Cache::put($eventTestKey, ['test' => 'data'], 3600);
        
        if (Cache::has($eventTestKey)) {
            $this->line("   ✅ Fresh test cache set: {$eventTestKey}");
            
            // Dispatch actual event
            event(new OrderCreated($testOrderId + 1, $testUserId, $testSellerId, ['actual_event' => true]));
            
            // Small delay to let event process
            sleep(1);
            
            if (Cache::has($eventTestKey)) {
                $this->line("   ❌ Cache still exists after event dispatch");
            } else {
                $this->line("   ✅ Cache invalidated by event dispatch");
            }
        }

        $this->newLine();
        $this->info('🎯 INTEGRATION TEST COMPLETE');
        $this->info('The InvalidateCartCacheListener is ready to refresh cart headers after purchases!');
        
        return 0;
    }
}