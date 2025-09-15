<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSellerController extends Controller
{
    /**
     * Get list of all sellers
     */
    public function index(Request $request): JsonResponse
    {
        \Log::info('AdminSellerController@index called'); // Debug
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search');
            $status = $request->input('status'); // active, pending, blocked

            $query = User::with(['seller'])
                ->whereHas('seller')
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.created_at',
                    'users.is_blocked',
                ]);

            // Search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            // Status filter
            if ($status) {
                switch ($status) {
                    case 'pending':
                        $query->whereHas('seller', function ($q) {
                            $q->where('status', 'pending');
                        });
                        break;
                    case 'active':
                        $query->whereHas('seller', function ($q) {
                            $q->where('status', 'active');
                        })
                            ->where('is_blocked', false);
                        break;
                    case 'blocked':
                        $query->where('is_blocked', true);
                        break;
                }
            }

            // Get sellers with stats
            $sellers = $query->paginate($perPage);

            // Add statistics for each seller
            $sellers->getCollection()->transform(function ($seller) {
                // Use the actual seller ID, not user ID
                $sellerId = $seller->seller->id;
                $stats = $this->getSellerStats($sellerId);

                return [
                    'id' => $sellerId, // Return seller ID, not user ID
                    'user_id' => $seller->id, // Include user ID for reference
                    'name' => $seller->name,
                    'email' => $seller->email,
                    'status' => $seller->seller->status ?? 'unknown',
                    'is_blocked' => $seller->is_blocked,
                    'store_name' => $seller->seller->store_name ?? null,
                    'userName' => $seller->name, // Para compatibilidad con frontend
                    'isFeatured' => $seller->seller->is_featured ?? false,
                    'created_at' => $seller->seller->created_at ? $seller->seller->created_at->format('Y-m-d') : $seller->created_at->format('Y-m-d'),
                    'joined_date' => $seller->created_at->format('Y-m-d H:i:s'),
                    'store_created_at' => $seller->seller->created_at ? $seller->seller->created_at->format('Y-m-d H:i:s') : null,
                    'total_orders' => $stats['total_orders'],
                    'total_revenue' => $stats['total_revenue'],
                    'products_count' => $stats['products_count'],
                    'average_rating' => $stats['average_rating'],
                    'last_order_date' => $stats['last_order_date'],
                ];
            });

            \Log::info('AdminSellerController@index returning data:', ['sellers_count' => $sellers->count()]); // Debug

            return response()->json([
                'success' => true,
                'data' => $sellers,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving sellers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific seller details
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Find by seller ID, not user ID
            $seller = User::with(['seller'])
                ->whereHas('seller', function ($query) use ($id) {
                    $query->where('id', $id);
                })
                ->first();

            if (! $seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller not found',
                ], 404);
            }

            // Get detailed statistics using the actual seller ID
            $sellerId = $seller->seller->id;
            $stats = $this->getSellerStats($sellerId);
            $monthlyStats = $this->getSellerMonthlyStats($sellerId);
            $recentOrders = $this->getSellerRecentOrders($sellerId);
            $topProducts = $this->getSellerTopProducts($sellerId);

            $sellerDetails = [
                'id' => $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
                'status' => $seller->seller->status ?? 'unknown',
                'is_blocked' => $seller->is_blocked,
                'created_at' => $seller->created_at->format('Y-m-d H:i:s'),
                'joined_date' => $seller->created_at->format('Y-m-d'),

                // Main statistics
                'total_orders' => $stats['total_orders'],
                'total_revenue' => $stats['total_revenue'],
                'products_count' => $stats['products_count'],
                'average_rating' => $stats['average_rating'],
                'last_order_date' => $stats['last_order_date'],

                // Additional details
                'seller_info' => [
                    'business_name' => $seller->seller->business_name ?? null,
                    'description' => $seller->seller->description ?? null,
                    'phone' => $seller->seller->phone ?? null,
                    'address' => $seller->seller->address ?? null,
                    'website' => $seller->seller->website ?? null,
                ],

                // Performance metrics
                'monthly_stats' => $monthlyStats,
                'recent_orders' => $recentOrders,
                'top_products' => $topProducts,

                // Additional stats
                'pending_orders' => Order::where('seller_id', $sellerId)
                    ->where('status', 'pending')
                    ->count(),
                'completed_orders' => Order::where('seller_id', $sellerId)
                    ->where('status', 'completed')
                    ->count(),
                'customer_count' => Order::where('seller_id', $sellerId)
                    ->distinct('user_id')
                    ->count('user_id'),
            ];

            return response()->json([
                'success' => true,
                'data' => $sellerDetails,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving seller details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update seller status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,active,suspended,inactive',
                'reason' => 'nullable|string|max:255',
            ]);

            // Find by seller ID, not user ID
            $seller = User::with('seller')
                ->whereHas('seller', function ($query) use ($id) {
                    $query->where('id', $id);
                })
                ->first();

            if (! $seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Seller not found',
                ], 404);
            }

            // Update seller status
            $seller->seller->update([
                'status' => $request->status,
                'status_reason' => $request->reason,
                'status_updated_at' => now(),
            ]);

            // NOTA: El estado del seller (tienda) es independiente del bloqueo del usuario
            // - seller->status: Controla el acceso a funciones de venta y visibilidad de productos
            // - user->is_blocked: Controla el acceso completo del usuario a la plataforma
            // Los admins pueden gestionar estos dos estados por separado

            return response()->json([
                'success' => true,
                'message' => 'Seller status updated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating seller status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get seller statistics
     */
    private function getSellerStats(int $sellerId): array
    {
        // Total orders
        $totalOrders = Order::where('seller_id', $sellerId)->count();

        // Total revenue from completed orders
        $totalRevenue = Order::where('seller_id', $sellerId)
            ->where('status', 'completed')
            ->sum('total');

        // Products count
        $productsCount = Product::where('seller_id', $sellerId)->count();

        // Average rating
        $averageRating = Rating::whereHas('product', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })->avg('rating') ?? 0;

        // Last order date
        $lastOrder = Order::where('seller_id', $sellerId)
            ->latest()
            ->first();

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => (float) $totalRevenue,
            'products_count' => $productsCount,
            'average_rating' => round($averageRating, 1),
            'last_order_date' => $lastOrder ? $lastOrder->created_at->format('Y-m-d') : null,
        ];
    }

    /**
     * Get seller monthly statistics for the last 6 months
     */
    private function getSellerMonthlyStats(int $sellerId): array
    {
        $monthlyData = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $orders = Order::where('seller_id', $sellerId)
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();

            $revenue = Order::where('seller_id', $sellerId)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->sum('total');

            $monthlyData[] = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('F Y'),
                'orders' => $orders,
                'revenue' => (float) $revenue,
            ];
        }

        return $monthlyData;
    }

    /**
     * Get seller recent orders
     */
    private function getSellerRecentOrders(int $sellerId): array
    {
        return Order::with(['user:id,name'])
            ->where('seller_id', $sellerId)
            ->select(['id', 'user_id', 'total', 'status', 'created_at'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'customer_name' => $order->user->name ?? 'Unknown',
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'date' => $order->created_at->format('Y-m-d'),
                    'formatted_total' => number_format($order->total, 2),
                ];
            })
            ->toArray();
    }

    /**
     * Get seller top products
     */
    private function getSellerTopProducts(int $sellerId): array
    {
        return Product::where('seller_id', $sellerId)
            ->select(['id', 'name', 'price', 'sales_count', 'view_count'])
            ->orderBy('sales_count', 'desc')
            ->take(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'sales_count' => $product->sales_count ?? 0,
                    'view_count' => $product->view_count ?? 0,
                    'revenue' => (float) ($product->price * ($product->sales_count ?? 0)),
                ];
            })
            ->toArray();
    }
}
