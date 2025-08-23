<?php

namespace Tests\Unit\Infrastructure\Repositories;

use App\Domain\Entities\CategoryEntity;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Repositories\EloquentCategoryRepository;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentCategoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected EloquentCategoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentCategoryRepository;
    }

    #[Test]
    public function it_can_find_a_category_by_id()
    {
        // Crear una categoría en la base de datos
        $category = Category::factory()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'Test Description',
        ]);

        // Buscar la categoría usando el repositorio
        $foundCategory = $this->repository->findById($category->id);

        // Verificar que se encontró correctamente
        $this->assertInstanceOf(CategoryEntity::class, $foundCategory);
        $this->assertEquals($category->id, $foundCategory->getId()->getValue());
        $this->assertEquals('Test Category', $foundCategory->getName());
        $this->assertEquals('test-category', $foundCategory->getSlug()->getValue());
    }

    #[Test]
    public function it_returns_null_when_category_not_found()
    {
        // Intentar encontrar una categoría inexistente
        $nonExistentCategory = $this->repository->findById(999999);

        // Debería devolver null
        $this->assertNull($nonExistentCategory);
    }

    #[Test]
    public function it_can_find_a_category_by_slug()
    {
        // Crear una categoría en la base de datos
        $category = Category::factory()->create([
            'name' => 'Slug Test',
            'slug' => 'slug-test',
            'description' => 'Testing slug search',
        ]);

        // Buscar la categoría usando el repositorio
        $foundCategory = $this->repository->findBySlug('slug-test');

        // Verificar que se encontró correctamente
        $this->assertInstanceOf(CategoryEntity::class, $foundCategory);
        $this->assertEquals($category->id, $foundCategory->getId()->getValue());
        $this->assertEquals('Slug Test', $foundCategory->getName());
    }

    #[Test]
    public function it_can_find_all_categories()
    {
        // Crear categorías en la base de datos
        Category::factory()->count(5)->create(['is_active' => true]);
        Category::factory()->count(2)->create(['is_active' => false]);

        // Buscar todas las categorías activas
        $activeCategories = $this->repository->findAll(true);

        // Verificar que solo se encontraron las categorías activas
        $this->assertCount(5, $activeCategories);
        foreach ($activeCategories as $category) {
            $this->assertTrue($category->isActive());
        }

        // Buscar todas las categorías (activas e inactivas)
        $allCategories = $this->repository->findAll(false);

        // Verificar que se encontraron todas las categorías
        $this->assertCount(7, $allCategories);
    }

    #[Test]
    public function it_can_find_featured_categories()
    {
        // Crear categorías destacadas
        Category::factory()->count(3)->create([
            'featured' => true,
            'is_active' => true,
        ]);

        // Crear categorías no destacadas
        Category::factory()->count(4)->create([
            'featured' => false,
            'is_active' => true,
        ]);

        // Buscar categorías destacadas
        $featuredCategories = $this->repository->findFeatured();

        // Verificar que solo se encontraron las categorías destacadas
        $this->assertCount(3, $featuredCategories);
        foreach ($featuredCategories as $category) {
            $this->assertTrue($category->isFeatured());
        }
    }

    #[Test]
    public function it_can_find_main_categories()
    {
        // Crear categorías principales (sin padre)
        Category::factory()->count(4)->create([
            'parent_id' => null,
            'is_active' => true,
        ]);

        // Crear subcategorías
        $parentCategory = Category::factory()->create();
        Category::factory()->count(3)->create([
            'parent_id' => $parentCategory->id,
            'is_active' => true,
        ]);

        // Buscar categorías principales
        $mainCategories = $this->repository->findMainCategories();

        // Verificar que solo se encontraron las categorías principales
        $this->assertCount(5, $mainCategories); // 4 + la categoría padre creada
        foreach ($mainCategories as $category) {
            $this->assertNull($category->getParentId());
        }
    }

    #[Test]
    public function it_can_find_subcategories()
    {
        // Crear una categoría padre
        $parentCategory = Category::factory()->create([
            'name' => 'Parent Category',
            'is_active' => true,
        ]);

        // Crear subcategorías
        Category::factory()->count(3)->create([
            'parent_id' => $parentCategory->id,
            'is_active' => true,
        ]);

        // Crear otras categorías (no relacionadas)
        Category::factory()->count(2)->create([
            'is_active' => true,
        ]);

        // Buscar subcategorías
        $subcategories = $this->repository->findSubcategories($parentCategory->id);

        // Verificar que solo se encontraron las subcategorías
        $this->assertCount(3, $subcategories);
        foreach ($subcategories as $category) {
            $this->assertNotNull($category->getParentId());
            $this->assertEquals($parentCategory->id, $category->getParentId()->getValue());
        }
    }

    #[Test]
    public function it_can_save_a_new_category()
    {
        // Crear un CategoryEntity para guardar
        $categoryEntity = new CategoryEntity(
            'New Category',
            new Slug('new-category'),
            'New category description',
            null, // parent_id
            'icon-test',
            'image-test.jpg',
            1, // order
            true, // is_active
            false // featured
        );

        $savedCategory = $this->repository->save($categoryEntity);

        // Verificar que se guardó y se asignó un ID
        $this->assertInstanceOf(CategoryEntity::class, $savedCategory);
        $this->assertNotNull($savedCategory->getId());
        $this->assertEquals('New Category', $savedCategory->getName());

        // Verificar que se guardó en la base de datos
        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
            'description' => 'New category description',
        ]);
    }

    #[Test]
    public function it_can_update_an_existing_category()
    {
        // Crear una categoría en la base de datos
        $category = Category::factory()->create([
            'name' => 'Original Category',
            'slug' => 'original-category',
            'description' => 'Original description',
        ]);

        // Obtener la entidad y modificarla
        $categoryEntity = $this->repository->findById($category->id);
        $categoryEntity->setName('Updated Category');
        $categoryEntity->setDescription('Updated description');

        // Guardar los cambios
        $updatedCategory = $this->repository->save($categoryEntity);

        // Verificar que los cambios se guardaron
        $this->assertEquals('Updated Category', $updatedCategory->getName());
        $this->assertEquals('Updated description', $updatedCategory->getDescription());

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
            'description' => 'Updated description',
        ]);
    }

    #[Test]
    public function it_can_delete_a_category()
    {
        // Crear una categoría en la base de datos
        $category = Category::factory()->create();

        // Verificar que existe
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);

        // Eliminar la categoría
        $result = $this->repository->delete($category->id);

        // Verificar que el resultado es verdadero
        $this->assertTrue($result);

        // Verificar que se eliminó
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function it_can_count_categories()
    {
        // Crear categorías con diferentes estados
        Category::factory()->count(3)->create(['is_active' => true, 'featured' => true]);
        Category::factory()->count(2)->create(['is_active' => true, 'featured' => false]);
        Category::factory()->count(1)->create(['is_active' => false, 'featured' => true]);

        // Contar todas las categorías
        $totalCount = $this->repository->count();
        $this->assertEquals(6, $totalCount);

        // Contar categorías activas
        $activeCount = $this->repository->count(['active' => true]);
        $this->assertEquals(5, $activeCount);

        // Contar categorías destacadas
        $featuredCount = $this->repository->count(['featured' => true]);
        $this->assertEquals(4, $featuredCount);

        // Contar categorías activas y destacadas
        $activeFeaturedCount = $this->repository->count(['active' => true, 'featured' => true]);
        $this->assertEquals(3, $activeFeaturedCount);
    }

    #[Test]
    public function it_can_create_from_array()
    {
        $categoryData = [
            'name' => 'Array Created Category',
            'description' => 'Created from array data',
            'icon' => 'icon-array',
            'is_active' => true,
        ];

        $category = $this->repository->createFromArray($categoryData);

        // Verificar la entidad creada
        $this->assertInstanceOf(CategoryEntity::class, $category);
        $this->assertEquals('Array Created Category', $category->getName());
        $this->assertEquals('Created from array data', $category->getDescription());
        $this->assertEquals('icon-array', $category->getIcon());
        $this->assertTrue($category->isActive());

        // Verificar en la base de datos
        $this->assertDatabaseHas('categories', [
            'name' => 'Array Created Category',
            'description' => 'Created from array data',
            'icon' => 'icon-array',
            'is_active' => 1,
        ]);
    }

    #[Test]
    public function it_can_update_from_array()
    {
        // Crear una categoría en la base de datos
        $category = Category::factory()->create([
            'name' => 'Original Array Category',
            'slug' => 'original-array-category',
            'description' => 'Original description',
        ]);

        $updateData = [
            'name' => 'Updated Array Category',
            'description' => 'Updated from array',
            'featured' => true,
        ];

        $updatedCategory = $this->repository->updateFromArray($category->id, $updateData);

        // Verificar la entidad actualizada
        $this->assertInstanceOf(CategoryEntity::class, $updatedCategory);
        $this->assertEquals('Updated Array Category', $updatedCategory->getName());
        $this->assertEquals('Updated from array', $updatedCategory->getDescription());
        $this->assertTrue($updatedCategory->isFeatured());

        // Verificar en la base de datos
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Array Category',
            'description' => 'Updated from array',
            'featured' => 1,
        ]);
    }
}
