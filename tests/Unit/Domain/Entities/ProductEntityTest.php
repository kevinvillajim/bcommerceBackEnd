<?php

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\ProductEntity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductEntityTest extends TestCase
{
    #[Test]
    public function it_can_be_instantiated()
    {
        $product = new ProductEntity(
            1, // user_id
            2, // category_id
            'Test Product', // name
            'test-product', // slug
            'Test description', // description
            100.00, // price
            10, // stock
            1.5, // weight
            10.0, // width
            20.0, // height
            5.0, // depth
            '10x20x5', // dimensions
            ['red', 'blue'], // colors
            ['S', 'M', 'L'], // sizes
            ['electronics', 'gadget'], // tags
            'SKU123', // sku
            ['material' => 'plastic'], // attributes
            [['original' => 'test.jpg']], // images
            true, // featured
            true, // published
            'active', // status
            0, // view_count
            0, // sales_count
            10.0 // discount_percentage
        );

        $this->assertInstanceOf(ProductEntity::class, $product);
        $this->assertEquals('Test Product', $product->getName());
        $this->assertEquals('test-product', $product->getSlug());
        $this->assertEquals(100.00, $product->getPrice());
        $this->assertEquals(10, $product->getStock());
    }

    #[Test]
    public function it_can_calculate_final_price_with_discount()
    {
        $product = new ProductEntity(
            1, // user_id
            2, // category_id
            'Test Product', // name
            'test-product', // slug
            'Test description', // description
            100.00, // price
            10, // stock
            null, // weight
            null, // width
            null, // height
            null, // depth
            null, // dimensions
            null, // colors
            null, // sizes
            null, // tags
            null, // sku
            null, // attributes
            null, // images
            false, // featured
            true, // published
            'active', // status
            0, // view_count
            0, // sales_count
            20.0 // discount_percentage (20%)
        );

        $this->assertEquals(80.0, $product->calculateFinalPrice());
    }

    #[Test]
    public function it_can_determine_if_in_stock()
    {
        // Producto con stock
        $productInStock = new ProductEntity(
            1,
            2,
            'Product In Stock',
            'product-in-stock',
            'Description',
            100.00,
            5,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            true,
            'active',
            0,
            0,
            0
        );

        // Producto sin stock
        $productOutOfStock = new ProductEntity(
            1,
            2,
            'Product Out Of Stock',
            'product-out-of-stock',
            'Description',
            100.00,
            0,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            true,
            'active',
            0,
            0,
            0
        );

        $this->assertTrue($productInStock->isInStock());
        $this->assertFalse($productOutOfStock->isInStock());
    }

    #[Test]
    public function it_can_decrement_stock()
    {
        $product = new ProductEntity(
            1,
            2,
            'Test Product',
            'test-product',
            'Description',
            100.00,
            10,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            true,
            'active',
            0,
            0,
            0
        );

        $product->decrementStock(3);
        $this->assertEquals(7, $product->getStock());

        // Intentar decrementar más que el stock disponible
        $product->decrementStock(10);
        // El stock no debería ser negativo
        $this->assertEquals(0, $product->getStock());
    }

    #[Test]
    public function it_can_increment_view_count()
    {
        $product = new ProductEntity(
            1,
            2,
            'Test Product',
            'test-product',
            'Description',
            100.00,
            10,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            true,
            'active',
            5,
            0,
            0
        );

        $product->incrementViewCount();
        $this->assertEquals(6, $product->getViewCount());
    }

    #[Test]
    public function it_can_increment_sales_count()
    {
        $product = new ProductEntity(
            1,
            2,
            'Test Product',
            'test-product',
            'Description',
            100.00,
            10,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            true,
            'active',
            0,
            10,
            0
        );

        $product->incrementSalesCount(3);
        $this->assertEquals(13, $product->getSalesCount());
    }

    #[Test]
    public function it_can_convert_to_array()
    {
        $product = new ProductEntity(
            1, // user_id
            2, // category_id
            'Test Product', // name
            'test-product', // slug
            'Test description', // description
            100.00, // price
            10, // stock
            null, // weight
            null, // width
            null, // height
            null, // depth
            null, // dimensions
            null, // colors
            null, // sizes
            null, // tags
            null, // sku
            null, // attributes
            null, // images
            false, // featured
            true, // published
            'active', // status
            0, // view_count
            0, // sales_count
            0.0 // discount_percentage
        );

        $array = $product->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('Test Product', $array['name']);
        $this->assertEquals('test-product', $array['slug']);
        $this->assertEquals(100.00, $array['price']);
        $this->assertEquals(10, $array['stock']);
        $this->assertEquals(1, $array['user_id']);
        $this->assertEquals(2, $array['category_id']);
    }
}
