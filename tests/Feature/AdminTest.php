<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $superAdminUser;

    protected User $regularUser;

    protected Admin $admin;

    protected Admin $superAdmin;

    protected string $adminToken;

    protected string $superAdminToken;

    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create super admin user
        $this->superAdminUser = User::factory()->create([
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create admin profiles
        $this->admin = Admin::factory()->create([
            'user_id' => $this->adminUser->id,
            'role' => 'customer_support',
            'status' => 'active',
        ]);

        $this->superAdmin = Admin::factory()->create([
            'user_id' => $this->superAdminUser->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Generate tokens
        $this->adminToken = JWTAuth::fromUser($this->adminUser);
        $this->superAdminToken = JWTAuth::fromUser($this->superAdminUser);
        $this->userToken = JWTAuth::fromUser($this->regularUser);
    }

    #[Test]
    public function it_allows_admins_to_view_dashboard()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'total_users',
                    'total_sellers',
                    'pending_sellers',
                    'pending_ratings',
                    'admins',
                ],
            ]);
    }

    #[Test]
    public function it_prevents_regular_users_from_accessing_admin_routes()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->userToken,
        ])->getJson('/api/admin/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Access denied. Admin privileges required.',
            ]);
    }

    #[Test]
    public function it_allows_admins_to_list_sellers()
    {
        // Create some sellers
        Seller::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->getJson('/api/admin/sellers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'store_name',
                            'status',
                            'verification_level',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
            ]);
    }

    #[Test]
    public function it_allows_admins_to_update_seller_status()
    {
        $seller = Seller::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->putJson('/api/admin/sellers/'.$seller->id.'/status', [
            'status' => 'active',
            'reason' => 'Seller has been verified',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Seller status updated successfully',
            ]);

        $this->assertDatabaseHas('sellers', [
            'id' => $seller->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_allows_admins_to_create_sellers()
    {
        // Create a new user who will become a seller
        $newUser = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->postJson('/api/admin/sellers', [
            'user_id' => $newUser->id,
            'store_name' => 'Admin Created Store',
            'description' => 'This is a business seller',
            'status' => 'active',
            'verification_level' => 'verified',
            'commission_rate' => 10.0,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Seller account created successfully',
            ]);

        $this->assertDatabaseHas('sellers', [
            'user_id' => $newUser->id,
            'store_name' => 'Admin Created Store',
            'status' => 'active',
            'verification_level' => 'verified',
        ]);
    }

    #[Test]
    public function it_allows_admins_to_update_seller_details()
    {
        $seller = Seller::factory()->create([
            'store_name' => 'Original Store Name',
            'verification_level' => 'basic',
            'is_featured' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->putJson('/api/admin/sellers/'.$seller->id, [
            'store_name' => 'Updated Store Name',
            'verification_level' => 'premium',
            'is_featured' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Seller updated successfully',
            ]);

        $this->assertDatabaseHas('sellers', [
            'id' => $seller->id,
            'store_name' => 'Updated Store Name',
            'verification_level' => 'premium',
            'is_featured' => 1,
        ]);
    }

    #[Test]
    public function it_allows_admins_to_moderate_ratings()
    {
        $rating = Rating::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->putJson('/api/admin/ratings/'.$rating->id.'/moderate', [
            'status' => 'approved',
            'reason' => 'Legitimate review',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Rating moderated successfully',
            ]);

        $this->assertDatabaseHas('ratings', [
            'id' => $rating->id,
            'status' => 'approved',
        ]);
    }

    #[Test]
    public function it_allows_admins_to_list_pending_ratings()
    {
        // Create some pending ratings
        Rating::factory()->count(3)->create([
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->getJson('/api/admin/ratings/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'rating',
                            'title',
                            'comment',
                            'status',
                            'type',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
            ]);
    }

    #[Test]
    public function it_allows_super_admin_to_add_new_admin()
    {
        $newAdminUser = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->superAdminToken,
        ])->postJson('/api/admin/admins', [
            'user_id' => $newAdminUser->id,
            'role' => 'content_manager',
            'permissions' => ['manage_products', 'manage_categories'],
            'status' => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Admin created successfully',
            ]);

        $this->assertDatabaseHas('admins', [
            'user_id' => $newAdminUser->id,
            'role' => 'content_manager',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_prevents_non_super_admin_from_managing_admins()
    {
        $newAdminUser = User::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->postJson('/api/admin/admins', [
            'user_id' => $newAdminUser->id,
            'role' => 'content_manager',
            'permissions' => ['manage_products', 'manage_categories'],
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_allows_super_admin_to_remove_admin()
    {
        $regularAdmin = Admin::factory()->create([
            'role' => 'customer_support',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->superAdminToken,
        ])->deleteJson('/api/admin/admins/'.$regularAdmin->user_id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Admin removed successfully',
            ]);

        $this->assertDatabaseMissing('admins', [
            'id' => $regularAdmin->id,
        ]);
    }

    #[Test]
    public function it_prevents_removing_super_admin_by_regular_admin()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
        ])->deleteJson('/api/admin/admins/'.$this->superAdmin->user_id);

        $response->assertStatus(403);

        $this->assertDatabaseHas('admins', [
            'id' => $this->superAdmin->id,
        ]);
    }

    #[Test]
    public function it_allows_super_admin_to_list_all_admins()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->superAdminToken,
        ])->getJson('/api/admin/admins');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'role',
                            'status',
                            'last_login_at',
                        ],
                    ],
                    'current_page',
                    'total',
                ],
            ]);
    }
}
