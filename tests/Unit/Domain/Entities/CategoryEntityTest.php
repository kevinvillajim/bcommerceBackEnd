<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\CategoryEntity;
use App\Domain\ValueObjects\CategoryId;
use App\Domain\ValueObjects\Slug;
use DateTime;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CategoryEntityTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated()
    {
        $category = new CategoryEntity(
            'Test Category',
            new Slug('test-category'),
            'Test Description',
            null, // parent_id
            'icon-test',
            'image.jpg',
            1, // order
            true, // is_active
            false, // featured
            new CategoryId(1),
            new DateTime,
            new DateTime
        );

        $this->assertInstanceOf(CategoryEntity::class, $category);
        $this->assertEquals('Test Category', $category->getName());
        $this->assertEquals('test-category', $category->getSlug()->getValue());
        $this->assertEquals('Test Description', $category->getDescription());
    }

    #[Test]
    public function it_can_be_created_from_static_method()
    {
        $category = CategoryEntity::create(
            'Created Category',
            'created-category',
            'Created from static method',
            null, // parent_id
            'icon-create',
            'create-image.jpg',
            2, // order
            true, // is_active
            true // featured
        );

        $this->assertInstanceOf(CategoryEntity::class, $category);
        $this->assertEquals('Created Category', $category->getName());
        $this->assertEquals('created-category', $category->getSlug()->getValue());
        $this->assertEquals('Created from static method', $category->getDescription());
        $this->assertTrue($category->isFeatured());
        $this->assertTrue($category->isActive());
        $this->assertNull($category->getId()); // ID is null for newly created categories
    }

    #[Test]
    public function it_can_be_reconstituted()
    {
        $category = CategoryEntity::reconstitute(
            1,
            'Reconstituted Category',
            'reconstituted-category',
            'Reconstituted from data',
            null, // parent_id
            'icon-reconstitute',
            'reconstitute-image.jpg',
            3, // order
            true, // is_active
            false, // featured
            '2023-01-01 00:00:00',
            '2023-01-02 00:00:00'
        );

        $this->assertInstanceOf(CategoryEntity::class, $category);
        $this->assertEquals('Reconstituted Category', $category->getName());
        $this->assertEquals('reconstituted-category', $category->getSlug()->getValue());
        $this->assertNotNull($category->getId());
        $this->assertEquals(1, $category->getId()->getValue());
    }

    #[Test]
    public function it_updates_timestamp_when_modified()
    {
        $originalTimestamp = new DateTime('2023-01-01 00:00:00');
        $category = new CategoryEntity(
            'Original Name',
            new Slug('original-name'),
            'Original Description',
            null, // parent_id
            null, // icon
            null, // image
            0, // order
            true, // is_active
            false, // featured
            new CategoryId(1),
            $originalTimestamp,
            $originalTimestamp
        );

        // Save original updated_at for comparison
        $originalUpdatedAt = $category->getUpdatedAt();

        // Modify the category
        sleep(1); // Ensure a time difference
        $category->setName('New Name');

        // The updated_at timestamp should have changed
        $this->assertNotEquals($originalUpdatedAt->format('Y-m-d H:i:s'), $category->getUpdatedAt()->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_can_add_child_categories()
    {
        $parentCategory = new CategoryEntity(
            'Parent Category',
            new Slug('parent-category'),
            null, // description
            null, // parent_id
            null, // icon
            null, // image
            0, // order
            true, // is_active
            false, // featured
            new CategoryId(1)
        );

        $childCategory = new CategoryEntity(
            'Child Category',
            new Slug('child-category'),
            null, // description
            new CategoryId(1), // parent_id
            null, // icon
            null, // image
            0, // order
            true, // is_active
            false, // featured
            new CategoryId(2)
        );

        $parentCategory->addChild($childCategory);

        $children = $parentCategory->getChildren();
        $this->assertCount(1, $children);
        $this->assertEquals('Child Category', $children[0]->getName());
    }

    #[Test]
    public function it_can_activate_and_deactivate()
    {
        $category = new CategoryEntity(
            'Test Category',
            new Slug('test-category'),
            null, // description
            null, // parent_id
            null, // icon
            null, // image
            0, // order
            true, // is_active initially
            false, // featured
            new CategoryId(1)
        );

        $this->assertTrue($category->isActive());

        $category->deactivate();
        $this->assertFalse($category->isActive());

        $category->activate();
        $this->assertTrue($category->isActive());
    }

    #[Test]
    public function it_can_be_featured_and_unfeatured()
    {
        $category = new CategoryEntity(
            'Test Category',
            new Slug('test-category'),
            null, // description
            null, // parent_id
            null, // icon
            null, // image
            0, // order
            true, // is_active
            false, // featured initially
            new CategoryId(1)
        );

        $this->assertFalse($category->isFeatured());

        $category->markAsFeatured();
        $this->assertTrue($category->isFeatured());

        $category->unmarkAsFeatured();
        $this->assertFalse($category->isFeatured());
    }

    #[Test]
    public function it_can_convert_to_array()
    {
        $createdAt = new DateTime('2023-01-01 00:00:00');
        $updatedAt = new DateTime('2023-01-02 00:00:00');

        $category = new CategoryEntity(
            'Array Test',
            new Slug('array-test'),
            'Testing array conversion',
            null, // parent_id
            'icon-test',
            'test-image.jpg',
            5, // order
            true, // is_active
            true, // featured
            new CategoryId(10),
            $createdAt,
            $updatedAt
        );

        $array = $category->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Array Test', $array['name']);
        $this->assertEquals('array-test', $array['slug']);
        $this->assertEquals('Testing array conversion', $array['description']);
        $this->assertEquals('icon-test', $array['icon']);
        $this->assertEquals('test-image.jpg', $array['image']);
        $this->assertEquals(5, $array['order']);
        $this->assertTrue($array['is_active']);
        $this->assertTrue($array['featured']);
        $this->assertEquals(10, $array['id']);
        $this->assertEquals('2023-01-01 00:00:00', $array['created_at']);
        $this->assertEquals('2023-01-02 00:00:00', $array['updated_at']);
    }
}
