<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user to maintain backward compatibility with existing tests
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        // Call other seeders that existing tests may depend on
        $this->call([
            CriticalConfigurationSeeder::class, // ⚠️ CRÍTICO: Configuraciones de respaldo
            CategorySeeder::class,
            ProductSeeder::class,
            // Add other existing seeders as needed
        ]);

        // Create additional categories if needed
        if (Category::count() < 10) {
            $additionalCategories = 10 - Category::count();
            if ($additionalCategories > 0) {
                $categories = Category::factory($additionalCategories)->create();
            } else {
                $categories = Category::all();
            }
        } else {
            $categories = Category::all();
        }

        // Create regular users
        $userCount = User::count();
        if ($userCount < 20) {
            $additionalUsers = 20 - $userCount;
            if ($additionalUsers > 0) {
                $newUsers = User::factory($additionalUsers)->create();
                $users = User::all();
            } else {
                $users = User::all();
            }
        } else {
            $users = User::all();
        }

        // Create a super admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        Admin::firstOrCreate(
            ['user_id' => $superAdmin->id],
            [
                'role' => 'super_admin',
                'status' => 'active',
                'permissions' => json_encode(['all']),
            ]
        );

        // Create a customer support admin
        $supportAdmin = User::firstOrCreate(
            ['email' => 'support@example.com'],
            [
                'name' => 'Support Admin',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        Admin::firstOrCreate(
            ['user_id' => $supportAdmin->id],
            [
                'role' => 'customer_support',
                'status' => 'active',
                'permissions' => json_encode(['manage_orders', 'manage_ratings', 'view_reports']),
            ]
        );

        // Check if we already have some sellers, avoid duplicating if they exist
        $sellerCount = Seller::count();
        if ($sellerCount < 5) {
            // Create business/company sellers
            $additionalSellers = 5 - $sellerCount;
            $companyUsers = collect();

            if ($additionalSellers > 0) {
                // Find users who are not already sellers
                $existingSellerUserIds = Seller::pluck('user_id')->toArray();
                $availableUsers = User::whereNotIn('id', $existingSellerUserIds)->take($additionalSellers)->get();

                // Create new users if we don't have enough
                if ($availableUsers->count() < $additionalSellers) {
                    $neededUsers = $additionalSellers - $availableUsers->count();
                    $newUsers = User::factory($neededUsers)->create([
                        'name' => fn () => 'Company: '.fake()->company(),
                    ]);
                    $companyUsers = $availableUsers->merge($newUsers);
                } else {
                    $companyUsers = $availableUsers->take($additionalSellers);
                }
            }

            foreach ($companyUsers as $user) {
                $seller = Seller::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'store_name' => 'Official Store: '.fake()->company(),
                        'description' => 'This is an official business seller account.',
                        'status' => 'active',
                        'verification_level' => fake()->randomElement(['verified', 'premium']),
                        'commission_rate' => fake()->randomFloat(2, 5, 15),
                        'is_featured' => fake()->boolean(40), // 40% chance of being featured
                    ]
                );

                // Check if user already has products
                $productCount = Product::where('user_id', $user->id)->count();
                if ($productCount < 3) { // Only create if user has less than 3 products
                    $productsToCreate = rand(3, 5) - $productCount;
                    if ($productsToCreate > 0) {
                        // Use insertGetId instead of create to avoid potential ID conflicts
                        for ($i = 0; $i < $productsToCreate; $i++) {
                            try {
                                $product = Product::factory()->make([
                                    'user_id' => $user->id,
                                    'category_id' => $categories->random()->id,
                                    'price' => fake()->randomFloat(2, 50, 500), // Higher price range for B2B
                                ]);

                                // Guardar sin preocuparnos por el ID
                                $product->save();
                            } catch (\Exception $e) {
                                // Log but continue
                                Log::error("Failed to create product for seller {$user->id}: ".$e->getMessage());
                            }
                        }
                    }
                }
            }
        }

        // Create or update sellers
        $sellers = Seller::all();

        // Create orders for testing
        $buyerUsers = $users->filter(function ($user) use ($sellers) {
            return ! $sellers->contains('user_id', $user->id);
        });

        // Verify if we need to create more orders
        $existingOrderCount = Order::count();
        if ($existingOrderCount < 10) {
            $ordersToCreate = 10 - $existingOrderCount;

            foreach ($buyerUsers->random(min($ordersToCreate, $buyerUsers->count())) as $user) {
                $seller = $sellers->random();

                if ($seller) {
                    $product = Product::where('user_id', $seller->user_id)->inRandomOrder()->first();

                    if (! $product) {
                        // If seller has no products, create one
                        $product = Product::factory()->create([
                            'user_id' => $seller->user_id,
                            'category_id' => $categories->random()->id,
                        ]);
                    }

                    // Check if an order already exists between this user and seller
                    $existingOrder = Order::where('user_id', $user->id)
                        ->where('seller_id', $seller->id)
                        ->first();

                    if (! $existingOrder) {
                        // Verificar primero que 'seller_orders' existe en la base de datos
                        $hasSellerOrdersTable = Schema::hasTable('seller_orders');
                        $hasSellerOrderIdColumn = Schema::hasTable('order_items') &&
                            Schema::hasColumn('order_items', 'seller_order_id');

                        $order = Order::create([
                            'user_id' => $user->id,
                            'seller_id' => $seller->id,
                            'order_number' => 'ORD-'.strtoupper(substr(md5(uniqid()), 0, 8)),
                            'status' => 'completed',
                            'total' => $product->price * rand(1, 3),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $sellerOrderId = null;

                        if ($hasSellerOrdersTable) {
                            // Crear seller_order si la tabla existe
                            $sellerOrderId = DB::table('seller_orders')->insertGetId([
                                'order_id' => $order->id,
                                'seller_id' => $seller->id,
                                'total' => $product->price * rand(1, 3),
                                'status' => 'completed',
                                'order_number' => 'SO-'.substr($order->order_number, 4),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        // Add order items
                        if ($hasSellerOrderIdColumn && $sellerOrderId) {
                            // Si existe la columna seller_order_id
                            DB::table('order_items')->insert([
                                'order_id' => $order->id,
                                'seller_order_id' => $sellerOrderId,
                                'product_id' => $product->id,
                                'quantity' => rand(1, 3),
                                'price' => $product->price,
                                'subtotal' => $product->price * rand(1, 3),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        } else {
                            // Si no existe seller_order_id
                            DB::table('order_items')->insert([
                                'order_id' => $order->id,
                                'product_id' => $product->id,
                                'quantity' => rand(1, 3),
                                'price' => $product->price,
                                'subtotal' => $product->price * rand(1, 3),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        // Add ratings (some users rate sellers, some rate products)
                        if (rand(0, 1)) {
                            Rating::firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'seller_id' => $seller->id,
                                    'order_id' => $order->id,
                                    'type' => 'user_to_seller',
                                ],
                                [
                                    'rating' => rand(1, 5),
                                    'title' => 'Seller Review',
                                    'comment' => 'This is a seller review for testing purposes.',
                                    'status' => 'approved',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        } else {
                            Rating::firstOrCreate(
                                [
                                    'user_id' => $user->id,
                                    'product_id' => $product->id,
                                    'order_id' => $order->id,
                                    'type' => 'user_to_product',
                                ],
                                [
                                    'rating' => rand(3, 5),
                                    'title' => 'Product Review',
                                    'comment' => 'This is a product review for testing purposes.',
                                    'status' => 'approved',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }

                        // Some sellers rate users
                        if (rand(0, 1)) {
                            Rating::firstOrCreate(
                                [
                                    'user_id' => $seller->user_id,
                                    'seller_id' => $seller->id,
                                    'order_id' => $order->id,
                                    'type' => 'seller_to_user',
                                ],
                                [
                                    'rating' => rand(3, 5),
                                    'title' => 'Customer Review',
                                    'comment' => 'This is a customer review for testing purposes.',
                                    'status' => 'approved',
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]
                            );
                        }
                    }
                }
            }
        }

        // Create some pending ratings for admin to moderate
        $pendingRatingsCount = Rating::where('status', 'pending')->count();
        if ($pendingRatingsCount < 5) {
            $ratingsToCreate = 5 - $pendingRatingsCount;
            if ($ratingsToCreate > 0) {
                // Find users and products for ratings
                $usersForRating = User::inRandomOrder()->take($ratingsToCreate)->get();
                $productsForRating = Product::inRandomOrder()->take($ratingsToCreate)->get();

                for ($i = 0; $i < $ratingsToCreate; $i++) {
                    try {
                        $user = $usersForRating->get($i % $usersForRating->count());
                        $product = $productsForRating->get($i % $productsForRating->count());

                        Rating::create([
                            'user_id' => $user->id,
                            'product_id' => $product->id,
                            'type' => 'user_to_product',
                            'rating' => rand(1, 5),
                            'title' => 'Pending Review '.($i + 1),
                            'comment' => 'This is a pending review for testing moderation.',
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create pending rating: '.$e->getMessage());
                    }
                }
            }
        }
    }
}
