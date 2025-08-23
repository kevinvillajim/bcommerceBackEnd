<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics for admin
     */
    public function getStats(): JsonResponse
    {
        try {
            $data = [
                'main_stats' => $this->getMainStatistics(),
                'pending_items' => $this->getPendingModerationItems(),
                'system_alerts' => $this->getSystemAlerts(),
                'recent_orders' => $this->getRecentOrders(),
                'top_sellers' => $this->getTopSellers(),
                'performance_metrics' => $this->getPerformanceMetrics(),
                'last_updated' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dashboard statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get main dashboard statistics
     */
    private function getMainStatistics(): array
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $previousMonth = Carbon::now()->subMonth()->startOfMonth();

        // Total sales (revenue)
        $totalSales = Order::where('status', 'completed')
            ->sum('total');

        $currentMonthSales = Order::where('status', 'completed')
            ->where('created_at', '>=', $currentMonth)
            ->sum('total');

        $previousMonthSales = Order::where('status', 'completed')
            ->whereBetween('created_at', [$previousMonth, $currentMonth])
            ->sum('total');

        $salesGrowth = $previousMonthSales > 0
            ? (($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100
            : 0;

        // Total users (customers + sellers)
        $totalUsers = User::count();
        $currentMonthUsers = User::where('created_at', '>=', $currentMonth)->count();
        $previousMonthUsers = User::whereBetween('created_at', [$previousMonth, $currentMonth])->count();

        $usersGrowth = $previousMonthUsers > 0
            ? (($currentMonthUsers - $previousMonthUsers) / $previousMonthUsers) * 100
            : 0;

        // Total orders
        $totalOrders = Order::count();
        $currentMonthOrders = Order::where('created_at', '>=', $currentMonth)->count();
        $previousMonthOrders = Order::whereBetween('created_at', [$previousMonth, $currentMonth])->count();

        $ordersGrowth = $previousMonthOrders > 0
            ? (($currentMonthOrders - $previousMonthOrders) / $previousMonthOrders) * 100
            : 0;

        // Active products vs total
        $totalProducts = Product::count();
        $activeProducts = Product::where('published', true)->count();
        $productActivityRate = $totalProducts > 0 ? ($activeProducts / $totalProducts) * 100 : 0;

        return [
            'total_sales' => [
                'value' => $totalSales,
                'change' => round($salesGrowth, 1),
                'formatted_value' => number_format($totalSales, 2),
            ],
            'total_users' => [
                'value' => $totalUsers,
                'change' => round($usersGrowth, 1),
                'formatted_value' => number_format($totalUsers),
            ],
            'total_orders' => [
                'value' => $totalOrders,
                'change' => round($ordersGrowth, 1),
                'formatted_value' => number_format($totalOrders),
            ],
            'active_products' => [
                'value' => "{$activeProducts} / {$totalProducts}",
                'change' => round($productActivityRate, 1),
                'active_count' => $activeProducts,
                'total_count' => $totalProducts,
                'formatted_value' => "{$activeProducts} / {$totalProducts}",
            ],
        ];
    }

    /**
     * Get pending moderation items
     */
    private function getPendingModerationItems(): array
    {
        // Pending ratings
        $pendingRatings = Rating::where('status', 'pending')->count();

        // Pending seller applications (sellers with pending status)
        $pendingSellers = User::whereHas('seller', function ($query) {
            $query->where('status', 'pending');
        })->count();

        // Pending feedback (assuming we have a feedback system)
        $pendingFeedback = Feedback::where('status', 'pending')->count();

        return [
            'pending_ratings' => [
                'count' => $pendingRatings,
                'message' => $pendingRatings.' reseñas pendientes requieren aprobación',
            ],
            'pending_sellers' => [
                'count' => $pendingSellers,
                'message' => $pendingSellers.' solicitudes de verificación de vendedores',
            ],
            'pending_feedback' => [
                'count' => $pendingFeedback,
                'message' => $pendingFeedback.' comentarios para revisar',
            ],
        ];
    }

    /**
     * Get system alerts
     */
    private function getSystemAlerts(): array
    {
        $alerts = [];

        // Low stock products (using a fixed threshold of 10 since we don't have minimum_stock field)
        $lowStockProducts = Product::where('stock', '<=', 10)
            ->where('published', true)
            ->count();

        if ($lowStockProducts > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Alerta de Inventario Bajo',
                'description' => $lowStockProducts.' productos tienen el inventario por debajo del umbral mínimo.',
                'count' => $lowStockProducts,
                'link_to' => '/admin/products?lowStock=true',
            ];
        }

        // Recent critical errors (last 24 hours)
        $criticalErrors = AdminLog::where('level', 'critical')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->count();

        if ($criticalErrors > 0) {
            $alerts[] = [
                'type' => 'error',
                'title' => 'Errores Críticos Recientes',
                'description' => $criticalErrors.' errores críticos en las últimas 24 horas.',
                'count' => $criticalErrors,
                'link_to' => '/admin/logs?level=critical',
            ];
        }

        // High error rate (more than 50 errors in last hour)
        $recentErrors = AdminLog::where('level', 'error')
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();

        if ($recentErrors > 50) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Alta Tasa de Errores',
                'description' => $recentErrors.' errores en la última hora. Revisar sistema.',
                'count' => $recentErrors,
                'link_to' => '/admin/logs?recent=1hour',
            ];
        }

        return $alerts;
    }

    /**
     * Get recent orders
     */
    private function getRecentOrders(): array
    {
        return Order::with(['user:id,name,email'])
            ->select(['id', 'user_id', 'total', 'status', 'created_at'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => (string) $order->id,
                    'date' => $order->created_at->format('Y-m-d'),
                    'customer' => $order->user->name ?? 'Usuario desconocido',
                    'total' => $order->total,
                    'status' => $this->translateOrderStatus($order->status),
                    'formatted_total' => number_format($order->total, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get top sellers by revenue
     */
    private function getTopSellers(): array
    {
        return User::whereHas('seller')
            ->select([
                'users.id',
                'users.name',
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('COALESCE(SUM(orders.total), 0) as total_revenue'),
            ])
            ->leftJoin('orders', function ($join) {
                $join->on('users.id', '=', 'orders.seller_id')
                    ->where('orders.status', '=', 'completed');
            })
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_revenue', 'desc')
            ->take(5)
            ->get()
            ->map(function ($seller) {
                return [
                    'id' => $seller->id,
                    'name' => $seller->name,
                    'order_count' => (int) $seller->order_count,
                    'revenue' => (float) $seller->total_revenue,
                    'formatted_revenue' => number_format($seller->total_revenue, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        $last7Days = Carbon::now()->subDays(7);
        $last30Days = Carbon::now()->subDays(30);

        return [
            'conversion_rate' => $this->calculateConversionRate(),
            'average_order_value' => $this->calculateAverageOrderValue(),
            'order_fulfillment_rate' => $this->calculateOrderFulfillmentRate(),
            'customer_retention_rate' => $this->calculateCustomerRetentionRate(),
            'daily_stats' => $this->getDailyStats(7), // Last 7 days
            'system_health' => [
                'error_rate' => $this->calculateErrorRate(),
                'average_response_time' => $this->calculateAverageResponseTime(),
                'uptime' => '99.9%', // This would come from monitoring system
            ],
        ];
    }

    /**
     * Calculate conversion rate (orders/users ratio)
     */
    private function calculateConversionRate(): float
    {
        // Customers are users without admin or seller relations
        $totalCustomers = User::whereDoesntHave('admin')
            ->whereDoesntHave('seller')
            ->count();

        $customersWithOrders = User::whereDoesntHave('admin')
            ->whereDoesntHave('seller')
            ->whereHas('orders')
            ->count();

        return $totalCustomers > 0 ? ($customersWithOrders / $totalCustomers) * 100 : 0;
    }

    /**
     * Calculate average order value
     */
    private function calculateAverageOrderValue(): float
    {
        return Order::where('status', 'completed')
            ->avg('total') ?? 0;
    }

    /**
     * Calculate order fulfillment rate
     */
    private function calculateOrderFulfillmentRate(): float
    {
        $totalOrders = Order::count();
        $completedOrders = Order::where('status', 'completed')->count();

        return $totalOrders > 0 ? ($completedOrders / $totalOrders) * 100 : 0;
    }

    /**
     * Calculate customer retention rate
     */
    private function calculateCustomerRetentionRate(): float
    {
        // Customers who made more than one order
        $repeatCustomers = User::whereDoesntHave('admin')
            ->whereDoesntHave('seller')
            ->whereHas('orders', function ($query) {
                $query->select(DB::raw('COUNT(*)'))
                    ->havingRaw('COUNT(*) > 1');
            })
            ->count();

        $totalCustomers = User::whereDoesntHave('admin')
            ->whereDoesntHave('seller')
            ->whereHas('orders')
            ->count();

        return $totalCustomers > 0 ? ($repeatCustomers / $totalCustomers) * 100 : 0;
    }

    /**
     * Get daily statistics for the last N days
     */
    private function getDailyStats(int $days): array
    {
        $startDate = Carbon::now()->subDays($days);

        return Order::select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as order_count'),
            DB::raw('SUM(total) as daily_revenue'),
            DB::raw('COUNT(DISTINCT user_id) as unique_customers'),
        ])
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Calculate error rate from AdminLog
     */
    private function calculateErrorRate(): float
    {
        $last24Hours = Carbon::now()->subDay();

        $totalRequests = AdminLog::where('created_at', '>=', $last24Hours)->count();
        $errorRequests = AdminLog::where('created_at', '>=', $last24Hours)
            ->whereIn('level', ['error', 'critical'])
            ->count();

        return $totalRequests > 0 ? ($errorRequests / $totalRequests) * 100 : 0;
    }

    /**
     * Calculate average response time from AdminLog context
     */
    private function calculateAverageResponseTime(): float
    {
        $logs = AdminLog::where('created_at', '>=', Carbon::now()->subHour())
            ->get();

        $durations = [];
        foreach ($logs as $log) {
            if (isset($log->context['duration_ms']) && is_numeric($log->context['duration_ms'])) {
                $durations[] = $log->context['duration_ms'];
            }
        }

        return count($durations) > 0 ? array_sum($durations) / count($durations) : 0;
    }

    /**
     * Translate order status to Spanish
     */
    private function translateOrderStatus(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'shipped' => 'Enviado',
            'delivered' => 'Entregado',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            default => $status
        };
    }
}
