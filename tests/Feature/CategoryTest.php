<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class CategoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $admin;

    protected User $regularUser;

    protected string $adminToken;

    protected string $regularUserToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear un usuario normal
        $this->regularUser = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
        ]);

        // Crear un usuario para admin
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Crear registro en la tabla de admins
        Admin::create([
            'user_id' => $this->admin->id,
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        // Generar tokens JWT
        $this->adminToken = JWTAuth::fromUser($this->admin);
        $this->regularUserToken = JWTAuth::fromUser($this->regularUser);
    }

    #[Test]
    public function admin_can_create_a_category()
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'This is a test category',
            'is_active' => true,
            'featured' => false,
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Test Category',
                'slug' => 'test-category',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'slug' => 'test-category',
        ]);
    }

    #[Test]
    public function regular_user_cannot_create_category()
    {
        $categoryData = [
            'name' => 'Test Category',
            'description' => 'This is a test category',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->regularUserToken)
            ->postJson('/api/admin/categories', $categoryData);

        $response->assertStatus(403);
    }

    #[Test]
    public function admin_can_update_a_category()
    {
        $category = Category::factory()->create([
            'name' => 'Original Category',
            'slug' => 'original-category',
        ]);

        $updatedData = [
            'name' => 'Updated Category',
            'description' => 'This category has been updated',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->putJson('/api/admin/categories/'.$category->id, $updatedData);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Category',
                'slug' => 'updated-category',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'slug' => 'updated-category',
        ]);
    }

    #[Test]
    public function admin_can_delete_a_category_without_products_or_subcategories()
    {
        $category = Category::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/admin/categories/'.$category->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function cannot_delete_category_with_products()
    {
        $category = Category::factory()->create();

        // Crear un producto asociado a esta categoría
        $product = Product::factory()->create([
            'category_id' => $category->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->deleteJson('/api/admin/categories/'.$category->id);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'No se puede eliminar. Tiene 1 productos asignados.',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function guests_can_view_categories()
    {
        // Crear algunas categorías
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                    ],
                ],
                'meta',
            ]);
    }

    #[Test]
    public function guests_can_view_category_details()
    {
        $category = Category::factory()->create();

        $response = $this->getJson('/api/categories/'.$category->id);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);
    }

    #[Test]
    public function guests_can_view_category_by_slug()
    {
        $category = Category::factory()->create([
            'name' => 'Slug Test',
            'slug' => 'slug-test',
        ]);

        $response = $this->getJson('/api/categories/slug/slug-test');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $category->id,
                'name' => 'Slug Test',
                'slug' => 'slug-test',
            ]);
    }

    #[Test]
    public function guests_can_view_main_categories()
    {
        // Crear categorías principales
        Category::factory()->count(3)->create([
            'parent_id' => null,
        ]);

        // Crear subcategorías
        Category::factory()->count(2)->create([
            'parent_id' => 1,
        ]);

        $response = $this->getJson('/api/categories/main');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'subcategories',
                    ],
                ],
                'meta',
            ]);

        // Verificar que hay exactamente 3 categorías principales
        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function guests_can_view_subcategories()
    {
        $parentCategory = Category::factory()->create();

        // Crear subcategorías
        Category::factory()->count(3)->create([
            'parent_id' => $parentCategory->id,
        ]);

        $response = $this->getJson("/api/categories/{$parentCategory->id}/subcategories");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'parent_id',
                    ],
                ],
                'meta',
            ]);

        // Verificar que hay exactamente 3 subcategorías
        $this->assertCount(3, $response->json('data'));
    }
}
