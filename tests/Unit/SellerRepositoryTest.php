<?php

namespace Tests\Unit;

use App\Domain\Entities\SellerEntity;
use App\Domain\Repositories\SellerRepositoryInterface;
use App\Infrastructure\Repositories\EloquentSellerRepository;
use App\Models\Rating;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SellerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected SellerRepositoryInterface $repository;

    protected User $user;

    protected Seller $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentSellerRepository;

        // Crear un usuario que será el vendedor
        $this->user = User::factory()->create();

        // Crear perfil de vendedor
        $this->seller = Seller::factory()->create([
            'user_id' => $this->user->id,
            'store_name' => 'Test Store',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_can_find_seller_by_id()
    {
        $sellerEntity = $this->repository->findById($this->seller->id);

        $this->assertNotNull($sellerEntity);
        $this->assertEquals($this->seller->id, $sellerEntity->getId());
        $this->assertEquals($this->user->id, $sellerEntity->getUserId());
        $this->assertEquals('Test Store', $sellerEntity->getStoreName());
        $this->assertEquals('active', $sellerEntity->getStatus());
    }

    #[Test]
    public function it_can_find_seller_by_user_id()
    {
        $sellerEntity = $this->repository->findByUserId($this->user->id);

        $this->assertNotNull($sellerEntity);
        $this->assertEquals($this->seller->id, $sellerEntity->getId());
        $this->assertEquals($this->user->id, $sellerEntity->getUserId());
    }

    #[Test]
    public function it_returns_null_for_nonexistent_seller()
    {
        $sellerEntity = $this->repository->findById(999);
        $this->assertNull($sellerEntity);

        $sellerEntity = $this->repository->findByUserId(999);
        $this->assertNull($sellerEntity);
    }

    #[Test]
    public function it_can_create_a_seller()
    {
        $newUser = User::factory()->create();

        $sellerEntity = new SellerEntity(
            $newUser->id,
            'New Store',
            'This is a new store description',
            'pending',
            'none',
            10.0,
            0,
            false
        );

        $result = $this->repository->create($sellerEntity);

        $this->assertNotNull($result->getId());
        $this->assertEquals($newUser->id, $result->getUserId());
        $this->assertEquals('New Store', $result->getStoreName());
        $this->assertEquals('pending', $result->getStatus());

        // Verificar en la base de datos
        $this->assertDatabaseHas('sellers', [
            'user_id' => $newUser->id,
            'store_name' => 'New Store',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_can_update_a_seller()
    {
        $sellerEntity = $this->repository->findById($this->seller->id);

        // Modificar datos
        $sellerEntity->setStoreName('Updated Store Name');
        $sellerEntity->setDescription('Updated description');
        $sellerEntity->setStatus('suspended');
        $sellerEntity->setIsFeatured(true);

        $result = $this->repository->update($sellerEntity);

        // Verificar que los cambios se reflejaron
        $this->assertEquals('Updated Store Name', $result->getStoreName());
        $this->assertEquals('Updated description', $result->getDescription());
        $this->assertEquals('suspended', $result->getStatus());
        $this->assertTrue($result->isFeatured());

        // Verificar en la base de datos
        $this->assertDatabaseHas('sellers', [
            'id' => $this->seller->id,
            'store_name' => 'Updated Store Name',
            'description' => 'Updated description',
            'status' => 'suspended',
            'is_featured' => 1,
        ]);
    }

    #[Test]
    public function it_can_get_top_sellers_by_rating()
    {
        // Crear más vendedores con diferentes calificaciones
        $seller2 = Seller::factory()->create([
            'user_id' => User::factory()->create()->id,
            'store_name' => 'Top Store 1',
        ]);

        $seller3 = Seller::factory()->create([
            'user_id' => User::factory()->create()->id,
            'store_name' => 'Top Store 2',
        ]);

        // Añadir calificaciones
        Rating::factory()->count(3)->create([
            'seller_id' => $seller2->id,
            'rating' => 5.0,
            'type' => 'user_to_seller',
            'status' => 'approved',
        ]);

        Rating::factory()->count(2)->create([
            'seller_id' => $seller3->id,
            'rating' => 4.0,
            'type' => 'user_to_seller',
            'status' => 'approved',
        ]);

        Rating::factory()->create([
            'seller_id' => $this->seller->id,
            'rating' => 3.0,
            'type' => 'user_to_seller',
            'status' => 'approved',
        ]);

        // Obtener vendedores top por calificación
        $topSellers = $this->repository->getTopSellersByRating(2);

        // Verificar que devuelve los 2 mejores vendedores ordenados por calificación
        $this->assertCount(2, $topSellers);
        $this->assertEquals($seller2->id, $topSellers[0]->getId());
        $this->assertEquals($seller3->id, $topSellers[1]->getId());
    }

    #[Test]
    public function it_can_get_featured_sellers()
    {
        // Crear vendedores con featured=true
        $featuredSeller1 = Seller::factory()->create([
            'user_id' => User::factory()->create()->id,
            'store_name' => 'Featured Store 1',
            'is_featured' => true,
        ]);

        $featuredSeller2 = Seller::factory()->create([
            'user_id' => User::factory()->create()->id,
            'store_name' => 'Featured Store 2',
            'is_featured' => true,
        ]);

        // Actualizar el vendedor de prueba a no destacado
        $this->seller->update(['is_featured' => false]);

        // Obtener vendedores destacados
        $featuredSellers = $this->repository->getFeaturedSellers();

        // Verificar que solo devuelve los vendedores destacados
        $this->assertGreaterThanOrEqual(2, count($featuredSellers));

        // Verificar que nuestros vendedores destacados están en la lista
        $sellerIds = array_map(function ($seller) {
            return $seller->getId();
        }, $featuredSellers);

        $this->assertContains($featuredSeller1->id, $sellerIds);
        $this->assertContains($featuredSeller2->id, $sellerIds);
        $this->assertNotContains($this->seller->id, $sellerIds);
    }
}
