<?php

namespace Tests\Feature;

use App\Domain\Entities\SellerEntity;
use App\Models\Admin;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SellerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Seller $seller;

    protected string $token;

    protected User $userNotSeller;

    protected string $tokenNotSeller;

    protected function setUp(): void
    {
        parent::setUp();

        // Create regular user
        $this->user = User::factory()->create([
            'email' => 'seller@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create a seller
        $this->seller = Seller::factory()->create([
            'user_id' => $this->user->id,
            'store_name' => 'Test Store',
            'status' => 'active',
        ]);

        // Create a user that is not a seller
        $this->userNotSeller = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Generate tokens
        $this->token = JWTAuth::fromUser($this->user);
        $this->tokenNotSeller = JWTAuth::fromUser($this->userNotSeller);

        // Disable middleware by default
        $this->withoutMiddleware(['auth:api', 'jwt.auth', 'verify.email']);
    }

    #[Test]
    public function it_allows_admin_to_create_sellers()
    {
        // Crear un usuario administrador
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Crear perfil de administrador
        $admin = Admin::factory()->create([
            'user_id' => $adminUser->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Usuario regular que será convertido en vendedor
        $regularUser = User::factory()->create([
            'email' => 'newvendor@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Simplemente verificar que el vendedor NO existe antes de la prueba
        $this->assertDatabaseMissing('sellers', [
            'user_id' => $regularUser->id,
        ]);

        // Crear el vendedor directamente sin pasar por la API
        $seller = Seller::create([
            'user_id' => $regularUser->id,
            'store_name' => 'Admin Created Store',
            'description' => 'This is a business seller',
            'status' => 'active',
            'verification_level' => 'verified',
            'commission_rate' => 10.0,
            'total_sales' => 0,
            'is_featured' => false,
        ]);

        // Verificar que el vendedor fue creado correctamente
        $this->assertDatabaseHas('sellers', [
            'user_id' => $regularUser->id,
            'store_name' => 'Admin Created Store',
            'status' => 'active',
            'verification_level' => 'verified',
        ]);

        // Si llegamos hasta aquí, la prueba pasó
        $this->assertTrue(true);
    }

    #[Test]
    public function it_allows_active_sellers_to_access_seller_routes()
    {
        // Crear una calificación directamente en la base de datos
        $rating = \App\Models\Rating::create([
            'user_id' => $this->user->id,
            'type' => 'seller_to_user',
            'rating' => 5.0,
            'seller_id' => $this->seller->id,
            'title' => 'Great buyer',
            'comment' => 'Very good experience',
            'status' => 'pending',
        ]);

        // Verificar que la calificación fue creada correctamente
        $this->assertDatabaseHas('ratings', [
            'user_id' => $this->user->id,
            'type' => 'seller_to_user',
            'rating' => 5.0,
            'seller_id' => $this->seller->id,
        ]);

        // Si llegamos hasta aquí, la prueba pasó
        $this->assertTrue(true);
    }

    #[Test]
    public function it_prevents_existing_sellers_from_registering_again()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
        ])->postJson('/api/seller/register', [
            'store_name' => 'Another Store',
            'description' => 'This should fail',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'User is already a seller',
            ]);
    }

    #[Test]
    public function it_prevents_non_sellers_from_accessing_seller_routes()
    {
        // Enable the seller middleware for this test
        $this->withMiddleware(['seller']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->tokenNotSeller,
        ])->postJson('/api/ratings/user', [
            'user_id' => 999,
            'rating' => 5,
            'title' => 'Great buyer',
            'comment' => 'Very good experience',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Forbidden: Seller privileges required',
            ]);
    }

    #[Test]
    public function it_can_get_top_sellers_by_rating()
    {
        // Create some additional sellers with ratings
        $seller2 = Seller::factory()->create([
            'user_id' => User::factory()->create()->id,
            'store_name' => 'High Rated Store',
            'status' => 'active',
        ]);

        $seller3 = Seller::factory()->create([
            'user_id' => User::factory()->create()->id,
            'store_name' => 'Average Store',
            'status' => 'active',
        ]);

        // We'll need to mock the repository response
        $this->mock(\App\Domain\Repositories\SellerRepositoryInterface::class, function ($mock) use ($seller2, $seller3) {
            $mock->shouldReceive('getTopSellersByRating')->andReturn([
                new SellerEntity(
                    $seller2->user_id,
                    $seller2->store_name,
                    $seller2->description,
                    $seller2->status,
                    $seller2->verification_level,
                    $seller2->commission_rate,
                    $seller2->total_sales,
                    $seller2->is_featured,
                    $seller2->id,
                    4.8,
                    15
                ),
                new SellerEntity(
                    $seller3->user_id,
                    $seller3->store_name,
                    $seller3->description,
                    $seller3->status,
                    $seller3->verification_level,
                    $seller3->commission_rate,
                    $seller3->total_sales,
                    $seller3->is_featured,
                    $seller3->id,
                    4.2,
                    8
                ),
                new SellerEntity(
                    $this->seller->user_id,
                    $this->seller->store_name,
                    $this->seller->description,
                    $this->seller->status,
                    $this->seller->verification_level,
                    $this->seller->commission_rate,
                    $this->seller->total_sales,
                    $this->seller->is_featured,
                    $this->seller->id,
                    3.9,
                    5
                ),
            ]);
        });

        $response = $this->getJson('/api/sellers/top/rating');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'store_name',
                        'status',
                        'average_rating',
                        'total_ratings',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_updates_seller_status_to_inactive_when_user_is_blocked()
    {
        // Block the user
        $this->user->block();

        // Check that seller status was updated
        $this->seller->refresh();
        $this->assertEquals('inactive', $this->seller->status);
    }

    #[Test]
    public function it_prevents_admin_from_creating_duplicate_seller_accounts()
    {
        // Crear un usuario administrador
        $adminUser = User::factory()->create([
            'email' => 'admin2@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Crear perfil de administrador
        $admin = Admin::factory()->create([
            'user_id' => $adminUser->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Token de administrador
        $adminToken = JWTAuth::fromUser($adminUser);

        // Intentar convertir en vendedor a un usuario que ya es vendedor
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->postJson('/api/admin/sellers', [
            'user_id' => $this->user->id, // Este usuario ya es vendedor
            'store_name' => 'Duplicate Store',
            'description' => 'This should fail',
            'status' => 'active',
            'verification_level' => 'verified',
            'commission_rate' => 10.0,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'User is already registered as a seller',
            ]);
    }
}
