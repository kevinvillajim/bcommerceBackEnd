<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    /**
     * Dashboard statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard()
    {
        try {
            // Get platform statistics
            $stats = [
                'total_users' => User::count(),
                'total_sellers' => Seller::count(),
                'pending_sellers' => Seller::where('status', 'pending')->count(),
                'pending_ratings' => Rating::where('status', 'pending')->count(),
                'admins' => Admin::with('user')->get()->map(function ($admin) {
                    return [
                        'id' => $admin->id,
                        'name' => $admin->user->name,
                        'email' => $admin->user->email,
                        'role' => $admin->role,
                        'status' => $admin->status,
                        'last_login_at' => $admin->last_login_at,
                    ];
                }),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching dashboard statistics: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching dashboard statistics',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all sellers with filtering options
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listSellers(Request $request)
    {
        try {
            $query = Seller::with('user');

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by verification level if provided
            if ($request->has('verification_level')) {
                $query->where('verification_level', $request->input('verification_level'));
            }

            // Add sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = $request->input('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $sellers = $query->paginate($perPage);

            // Transform the data with actual calculations
            $sellers->getCollection()->transform(function ($seller) {
                // Calculate total sales from orders table
                $totalSales = DB::table('orders')
                    ->where('seller_id', $seller->id)
                    ->where('status', 'completed')
                    ->sum('total');

                // Calculate ratings from ratings table
                $ratingsData = DB::table('ratings')
                    ->where('seller_id', $seller->id)
                    ->where('status', 'approved')
                    ->select(
                        DB::raw('AVG(rating) as average_rating'),
                        DB::raw('COUNT(*) as total_ratings')
                    )
                    ->first();

                return [
                    'id' => $seller->id,
                    'user_id' => $seller->user_id,
                    'store_name' => $seller->store_name,
                    'user_name' => $seller->user ? $seller->user->name : null,
                    'email' => $seller->user ? $seller->user->email : null,
                    'display_name' => $seller->store_name.' ('.($seller->user ? $seller->user->name : 'N/A').')',
                    'status' => $seller->status,
                    'verification_level' => $seller->verification_level,
                    'commission_rate' => $seller->commission_rate,
                    'is_featured' => $seller->is_featured,
                    'total_sales' => $totalSales ?: 0,
                    'average_rating' => $ratingsData ? round($ratingsData->average_rating, 1) : null,
                    'total_ratings' => $ratingsData ? $ratingsData->total_ratings : 0,
                    'created_at' => $seller->created_at,
                    'updated_at' => $seller->updated_at,
                ];
            });

            return response()->json($sellers);
        } catch (\Exception $e) {
            Log::error('Error fetching sellers: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching sellers',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update seller status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSellerStatus(Request $request, int $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,active,suspended,inactive',
                'reason' => 'nullable|string|max:500',
            ]);

            /** @phpstan-ignore-next-line */
            $seller = Seller::findOrFail($id);
            $oldStatus = $seller->status;
            $newStatus = $request->input('status');

            $seller->status = $newStatus;
            $seller->save();

            // Log the status change - safely handle potential missing table/column
            try {
                if (Schema::hasTable('admin_logs')) {
                    DB::table('admin_logs')->insert([
                        'admin_id' => Auth::check() && Auth::user() && Auth::user()->admin ? Auth::user()->admin->id : null,
                        'action' => 'seller_status_update',
                        'target_id' => $seller->id,
                        'details' => json_encode([
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'reason' => $request->input('reason', 'No reason provided'),
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $logEx) {
                // Just log the error but don't fail the request
                Log::warning('Failed to log admin action: '.$logEx->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Seller status updated successfully',
                'data' => $seller,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Seller not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating seller status: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error updating seller status',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new seller account (convert user to seller)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createSeller(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'store_name' => 'required|string|min:3|max:100',
                'description' => 'nullable|string|max:500',
                'status' => 'required|in:pending,active',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'is_featured' => 'nullable|boolean',
            ]);

            // Check if user is already a seller
            $existingSeller = Seller::where('user_id', $request->input('user_id'))->first();
            if ($existingSeller) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is already registered as a seller',
                ], 400);
            }

            // Check store name uniqueness separately to provide better error message
            $storeNameExists = Seller::where('store_name', $request->input('store_name'))->exists();
            if ($storeNameExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Store name is already taken',
                ], 400);
            }

            // Create new seller account
            $seller = new Seller;
            $seller->user_id = $request->input('user_id');
            $seller->store_name = $request->input('store_name');
            $seller->description = $request->input('description');
            $seller->status = $request->input('status');
            $seller->verification_level = 'none'; // Default value - will be replaced by is_featured logic
            $seller->commission_rate = $request->input('commission_rate', 10.00);
            $seller->total_sales = 0;
            $seller->is_featured = $request->input('is_featured', false);
            $seller->save();

            // Log the seller creation - safely handle potential missing table/column
            try {
                if (Schema::hasTable('admin_logs')) {
                    DB::table('admin_logs')->insert([
                        'admin_id' => Auth::check() && Auth::user() && Auth::user()->admin ? Auth::user()->admin->id : null,
                        'action' => 'seller_create',
                        'target_id' => $seller->id,
                        'details' => json_encode([
                            'user_id' => $request->input('user_id'),
                            'store_name' => $request->input('store_name'),
                            'status' => $request->input('status'),
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $logEx) {
                // Just log the error but don't fail the request
                Log::warning('Failed to log admin action: '.$logEx->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Seller account created successfully',
                'data' => $seller,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating seller account: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error creating seller account',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update seller details
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateSeller(Request $request, int $id)
    {
        try {
            $request->validate([
                'store_name' => 'nullable|string|min:3|max:100',
                'description' => 'nullable|string|max:500',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'is_featured' => 'nullable|boolean',
            ]);

            /** @phpstan-ignore-next-line */
            $seller = Seller::findOrFail($id);

            // Update only provided fields
            if ($request->has('store_name')) {
                // Check for uniqueness but ignore current seller
                $existingWithSameName = Seller::where('store_name', $request->input('store_name'))
                    ->where('id', '!=', $id)
                    ->exists();

                if ($existingWithSameName) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Store name is already taken',
                    ], 400);
                }

                $seller->store_name = $request->input('store_name');
            }

            if ($request->has('description')) {
                $seller->description = $request->input('description');
            }

            // verification_level is now handled by is_featured logic

            if ($request->has('commission_rate')) {
                $seller->commission_rate = $request->input('commission_rate');
            }

            if ($request->has('is_featured')) {
                $seller->is_featured = $request->input('is_featured');
            }

            $seller->save();

            // Log the seller update - safely handle potential missing table/column
            try {
                if (Schema::hasTable('admin_logs')) {
                    DB::table('admin_logs')->insert([
                        'admin_id' => Auth::check() && Auth::user() && Auth::user()->admin ? Auth::user()->admin->id : null,
                        'action' => 'seller_update',
                        'target_id' => $seller->id,
                        'details' => json_encode($request->only([
                            'store_name',
                            'description',
                            'verification_level',
                            'commission_rate',
                            'is_featured',
                        ])),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $logEx) {
                // Just log the error but don't fail the request
                Log::warning('Failed to log admin action: '.$logEx->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Seller updated successfully',
                'data' => $seller,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Seller not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating seller: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error updating seller',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve or reject a pending rating
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function moderateRating(Request $request, int $id)
    {
        try {
            $request->validate([
                'status' => 'required|in:approved,rejected,flagged',
                'reason' => 'nullable|string|max:500',
            ]);

            /** @phpstan-ignore-next-line */
            $rating = Rating::findOrFail($id);
            $oldStatus = $rating->status;
            $newStatus = $request->input('status');

            $rating->status = $newStatus;
            $rating->save();

            // Log the moderation action - safely handle potential missing table/column
            try {
                if (Schema::hasTable('admin_logs')) {
                    DB::table('admin_logs')->insert([
                        'admin_id' => Auth::check() && Auth::user() && Auth::user()->admin ? Auth::user()->admin->id : null,
                        'action' => 'rating_moderation',
                        'target_id' => $rating->id,
                        'details' => json_encode([
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'reason' => $request->input('reason', 'No reason provided'),
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $logEx) {
                // Just log the error but don't fail the request
                Log::warning('Failed to log admin action: '.$logEx->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Rating moderated successfully',
                'data' => $rating,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rating not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error moderating rating: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error moderating rating',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List pending ratings
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listPendingRatings(Request $request)
    {
        try {
            $query = Rating::with(['user', 'seller', 'product'])
                ->where('status', 'pending');

            // Filter by type if provided
            if ($request->has('type')) {
                $query->where('type', $request->input('type'));
            }

            // Add sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = $request->input('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $ratings = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $ratings,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching pending ratings: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching pending ratings',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add or modify an admin
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function manageAdmin(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'role' => 'required|in:super_admin,content_manager,customer_support,analytics',
                'permissions' => 'nullable|array',
                'status' => 'required|in:active,inactive',
            ]);

            // Check if user is already an admin
            /** @phpstan-ignore-next-line */
            $admin = Admin::firstOrNew(['user_id' => $request->input('user_id')]);
            $isNew = ! $admin->exists;

            // Set or update fields
            $admin->role = $request->input('role');
            $admin->permissions = $request->input('permissions', []);
            $admin->status = $request->input('status');
            $admin->save();

            return response()->json([
                'status' => 'success',
                'message' => $isNew ? 'Admin created successfully' : 'Admin updated successfully',
                'data' => $admin,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error managing admin: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error managing admin',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove admin privileges from a user
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeAdmin(int $userId)
    {
        try {
            $admin = Admin::where('user_id', $userId)->first();

            if (! $admin) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is not an admin',
                ], 404);
            }

            // Can't remove a super admin unless done by another super admin
            if ($admin->role === 'super_admin' && Auth::check() && Auth::user()->admin->role !== 'super_admin') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have permission to remove a super admin',
                ], 403);
            }

            $admin->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Admin removed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing admin: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error removing admin',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all admins
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listAdmins(Request $request)
    {
        try {
            $query = Admin::with('user');

            // Filter by role if provided
            if ($request->has('role')) {
                $query->where('role', $request->input('role'));
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Add sorting
            $sortBy = $request->input('sort_by', 'created_at');
            $sortDir = $request->input('sort_dir', 'desc');
            $query->orderBy($sortBy, $sortDir);

            // Paginate results
            $perPage = $request->input('per_page', 15);
            $admins = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $admins,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching admins: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error fetching admins',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de valoraciones
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRatingStats()
    {
        try {
            // Obtener estadísticas de la base de datos
            $totalCount = Rating::count();
            $approvedCount = Rating::where('status', Rating::STATUS_APPROVED)->count();
            $pendingCount = Rating::where('status', Rating::STATUS_PENDING)->count();
            $rejectedCount = Rating::where('status', Rating::STATUS_REJECTED)->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'totalCount' => $totalCount,
                    'approvedCount' => $approvedCount,
                    'pendingCount' => $pendingCount,
                    'rejectedCount' => $rejectedCount,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener estadísticas de valoraciones: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Aprobar todas las valoraciones pendientes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveAllPendingRatings()
    {
        try {
            // Actualizar todas las valoraciones pendientes a aprobadas
            $count = Rating::where('status', Rating::STATUS_PENDING)
                ->update(['status' => Rating::STATUS_APPROVED]);

            return response()->json([
                'status' => 'success',
                'message' => "Se han aprobado $count valoraciones pendientes",
                'data' => [
                    'count' => $count,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al aprobar valoraciones pendientes: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar todos los productos de un vendedor como destacados
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function featureAllSellerProducts($id)
    {
        try {
            // Verificar que el vendedor existe
            /** @phpstan-ignore-next-line */
            $seller = Seller::findOrFail($id);

            // Marcar todos los productos del vendedor como destacados
            $updatedCount = DB::table('products')
                ->where('seller_id', $id)
                ->update(['is_featured' => true]);

            Log::info('Admin featured all products for seller', [
                'seller_id' => $id,
                'store_name' => $seller->store_name,
                'products_updated' => $updatedCount,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => "Se han marcado $updatedCount productos como destacados para el vendedor {$seller->store_name}",
                'data' => [
                    'seller_id' => $id,
                    'products_updated' => $updatedCount,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error featuring seller products', [
                'seller_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al marcar productos como destacados: '.$e->getMessage(),
            ], 500);
        }
    }
}
