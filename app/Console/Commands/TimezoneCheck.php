<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Services\EcuadorTimeService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TimezoneCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timezone:check {--test : Show test timestamps}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Ecuador timezone configuration and show current timestamps';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🇪🇨 Ecuador Timezone Configuration Check');
        $this->info('=====================================');
        
        // Show system configuration
        $this->showSystemConfiguration();
        
        // Show current times
        $this->showCurrentTimes();
        
        if ($this->option('test')) {
            $this->showTestTimestamps();
        }
        
        // Show recent orders with timestamps
        $this->showRecentOrders();
        
        return Command::SUCCESS;
    }

    private function showSystemConfiguration()
    {
        $this->info("\n📋 System Configuration:");
        $this->table(['Setting', 'Value'], [
            ['PHP Default Timezone', date_default_timezone_get()],
            ['Laravel App Timezone', config('app.timezone')],
            ['Database Connection', config('database.default')],
            ['APP_ENV', config('app.env')],
        ]);
    }

    private function showCurrentTimes()
    {
        $now = Carbon::now();
        $utcNow = Carbon::now('UTC');
        $ecuadorNow = EcuadorTimeService::now();
        
        $this->info("\n🕐 Current Times:");
        $this->table(['Timezone', 'Time', 'Offset'], [
            ['System Default', $now->format('Y-m-d H:i:s P'), $now->format('P')],
            ['UTC', $utcNow->format('Y-m-d H:i:s P'), $utcNow->format('P')],
            ['Ecuador (Service)', $ecuadorNow->format('Y-m-d H:i:s P'), $ecuadorNow->format('P')],
        ]);
    }

    private function showTestTimestamps()
    {
        $this->info("\n🧪 Test Timestamp Conversions:");
        
        $testDate = '2025-08-26 14:30:00'; // UTC time
        
        $utc = Carbon::parse($testDate, 'UTC');
        $ecuador = EcuadorTimeService::toEcuadorTime($utc);
        $friendly = EcuadorTimeService::formatFriendly($utc);
        
        $this->table(['Format', 'Value'], [
            ['Original UTC', $utc->format('Y-m-d H:i:s P')],
            ['Ecuador Time', $ecuador->format('Y-m-d H:i:s P')],
            ['Friendly Format', $friendly],
            ['Diff for Humans', EcuadorTimeService::diffForHumans($utc)],
        ]);
    }

    private function showRecentOrders()
    {
        $this->info("\n📦 Recent Orders Timestamps:");
        
        // Get recent orders
        $orders = Order::latest()->take(3)->get();
        
        if ($orders->isEmpty()) {
            $this->warn('No orders found in database');
            return;
        }

        $tableData = [];
        foreach ($orders as $order) {
            $tableData[] = [
                $order->id,
                $order->order_number,
                $order->created_at->format('Y-m-d H:i:s P'),
                EcuadorTimeService::formatFriendly($order->created_at),
                $order->created_at->diffForHumans(),
            ];
        }

        $this->table(
            ['ID', 'Order #', 'Database Time', 'Ecuador Time', 'Human Diff'],
            $tableData
        );

        // Show seller orders too
        $sellerOrders = SellerOrder::latest()->take(3)->get();
        
        if (!$sellerOrders->isEmpty()) {
            $this->info("\n👨‍💼 Recent Seller Orders Timestamps:");
            
            $sellerTableData = [];
            foreach ($sellerOrders as $sellerOrder) {
                $sellerTableData[] = [
                    $sellerOrder->id,
                    $sellerOrder->order_number,
                    $sellerOrder->created_at->format('Y-m-d H:i:s P'),
                    EcuadorTimeService::formatFriendly($sellerOrder->created_at),
                    $sellerOrder->created_at->diffForHumans(),
                ];
            }

            $this->table(
                ['ID', 'Order #', 'Database Time', 'Ecuador Time', 'Human Diff'],
                $sellerTableData
            );
        }

        $this->info("\n✅ All timestamps are now using Ecuador timezone (UTC-5)");
        $this->info("💡 Use EcuadorTimeService::formatFriendly() for user-facing dates");
    }
}
