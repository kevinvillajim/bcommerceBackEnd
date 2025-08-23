<?php

namespace App\Domain\Formatters;

use App\Domain\Entities\ProductEntity;
use App\Domain\Repositories\CategoryRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductFormatter
{
    private ProductRepositoryInterface $productRepository;

    private ?CategoryRepositoryInterface $categoryRepository;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        ?CategoryRepositoryInterface $categoryRepository = null
    ) {
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
    }

    /**
     * Formatea la información básica de un producto
     */
    public function formatBasic(int $productId): array
    {
        try {
            $product = $this->productRepository->findById($productId);

            if (! $product) {
                return [
                    'id' => $productId,
                    'name' => 'Producto no encontrado',
                    'price' => 0.0,
                    'rating' => 0.0,
                    'rating_count' => 0,
                ];
            }

            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'price' => (float) $product->getPrice(),
                'final_price' => (float) $this->calculateFinalPrice($product),
                'rating' => (float) $product->getRating(),
                'rating_count' => (int) $product->getRatingCount(),
                'discount_percentage' => (float) $product->getDiscountPercentage(),
                'main_image' => $this->getMainImageUrl($product),
                'stock' => (int) $product->getStock(),
                'is_in_stock' => $product->getStock() > 0,
                'seller_id' => (int) ($product->getSellerId() ?? $product->getUserId()),
                'created_at' => $product->getCreatedAt(),
            ];
        } catch (\Exception $e) {
            Log::error('Error formateando producto básico: '.$e->getMessage());

            return [
                'id' => $productId,
                'name' => 'Error al obtener producto',
                'price' => 0.0,
                'rating' => 0.0,
                'rating_count' => 0,
            ];
        }
    }

    /**
     * Formatea un producto para incluirlo en las recomendaciones
     *
     * @param  ProductEntity|Product  $product
     */
    public function formatForRecommendation($product, string $recommendationType): array
    {
        try {
            // Si es un modelo Eloquent, extraer los valores necesarios
            if ($product instanceof Product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price' => (float) $product->price,
                    'final_price' => (float) $product->getFinalPrice(),
                    'rating' => (float) ($product->rating ?? 0),
                    'rating_count' => (int) ($product->rating_count ?? 0),
                    'discount_percentage' => (float) $product->discount_percentage,
                    'main_image' => $product->getMainImageUrl(),
                    'category_id' => (int) $product->category_id,
                    'category_name' => $product->category->name ?? null,
                    'recommendation_type' => $recommendationType,
                    'seller_id' => (int) ($product->seller_id ?? $product->user_id),
                    'status' => $product->status,
                    'created_at' => $product->created_at ? $product->created_at->format('Y-m-d H:i:s') : null,
                ];
            }

            // Si es una entidad de dominio
            if ($product instanceof ProductEntity) {
                return [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'slug' => $product->getSlug(),
                    'price' => (float) $product->getPrice(),
                    'final_price' => (float) $this->calculateFinalPrice($product),
                    'rating' => (float) $product->getRating(),
                    'rating_count' => (int) $product->getRatingCount(),
                    'discount_percentage' => (float) $product->getDiscountPercentage(),
                    'main_image' => $this->getMainImageUrl($product),
                    'category_id' => (int) $product->getCategoryId(),
                    'category_name' => $this->getCategoryName($product->getCategoryId()),
                    'recommendation_type' => $recommendationType,
                    'seller_id' => (int) ($product->getSellerId() ?? $product->getUserId()),
                    'status' => $product->getStatus(),
                    'created_at' => $product->getCreatedAt(),
                ];
            }

            return [
                'id' => 0,
                'name' => 'Producto desconocido',
                'recommendation_type' => $recommendationType,
                'rating' => 0,
                'rating_count' => 0,
            ];
        } catch (\Exception $e) {
            Log::error('Error formateando producto para recomendación: '.$e->getMessage());

            return [
                'id' => $product instanceof Product ? $product->id : ($product instanceof ProductEntity ? $product->getId() : 0),
                'name' => $product instanceof Product ? $product->name : ($product instanceof ProductEntity ? $product->getName() : 'Producto desconocido'),
                'recommendation_type' => $recommendationType,
                'rating' => 0,
                'rating_count' => 0,
            ];
        }
    }

    /**
     * Formatea un producto para la API (versión resumida)
     *
     * @param  ProductEntity|Product  $product
     */
    public function formatForApi($product): array
    {
        try {
            // Si es un modelo Eloquent
            if ($product instanceof Product) {

                $formattedProduct = [
                    'id' => (int) $product->id,
                    'name' => (string) ($product->name ?? 'Producto sin nombre'),
                    'slug' => (string) ($product->slug ?? ''),
                    'price' => (float) ($product->price ?? 0),
                    'final_price' => (float) $product->getFinalPrice(),
                    'rating' => (float) ($product->rating ?? 0),
                    'rating_count' => (int) ($product->rating_count ?? 0),
                    'discount_percentage' => (float) ($product->discount_percentage ?? 0),
                    'main_image' => $this->getMainImageUrl($product),
                    'images' => $this->processImages($product->images),
                    'category_id' => (int) ($product->category_id ?? 0),
                    'category_name' => $product->category->name ?? null,
                    'stock' => (int) ($product->stock ?? 0),
                    'is_in_stock' => ($product->stock ?? 0) > 0,
                    'featured' => (bool) ($product->featured ?? false),
                    'published' => (bool) ($product->published ?? false),
                    'status' => (string) ($product->status ?? 'inactive'),
                    'tags' => $product->tags,
                    'seller_id' => (int) ($product->seller_id ?? $product->user_id ?? 0),
                    'created_at' => $product->created_at ? $product->created_at->format('Y-m-d H:i:s') : null,
                ];

                // Agregar campos calculados si existen
                if (isset($product->calculated_rating)) {
                    $formattedProduct['rating'] = (float) $product->calculated_rating;
                    $formattedProduct['calculated_rating'] = (float) $product->calculated_rating;
                }

                if (isset($product->calculated_rating_count)) {
                    $formattedProduct['rating_count'] = (int) $product->calculated_rating_count;
                    $formattedProduct['calculated_rating_count'] = (int) $product->calculated_rating_count;
                }

                return $formattedProduct;
            }

            // Si es una entidad de dominio
            if ($product instanceof ProductEntity) {
                return [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'slug' => $product->getSlug(),
                    'price' => (float) $product->getPrice(),
                    'final_price' => (float) $this->calculateFinalPrice($product),
                    'rating' => (float) $product->getRating(),
                    'rating_count' => (int) $product->getRatingCount(),
                    'discount_percentage' => (float) $product->getDiscountPercentage(),
                    'main_image' => $this->getMainImageUrl($product),
                    'images' => $this->processImages($product->getImages()),
                    'category_id' => (int) $product->getCategoryId(),
                    'category_name' => $this->getCategoryName($product->getCategoryId()),
                    'stock' => (int) $product->getStock(),
                    'is_in_stock' => $product->getStock() > 0,
                    'featured' => (bool) $product->isFeatured(),
                    'published' => (bool) $product->isPublished(),
                    'status' => $product->getStatus(),
                    'tags' => $product->getTags(),
                    'seller_id' => (int) ($product->getSellerId() ?? $product->getUserId()),
                    'created_at' => $product->getCreatedAt() ? $product->getCreatedAt() : null,
                ];
            }

            Log::error('Tipo de producto desconocido: '.get_class($product));

            return [
                'id' => 0,
                'name' => 'Producto desconocido',
                'slug' => '',
                'price' => (float) 0,
                'final_price' => (float) 0,
                'rating' => (float) 0,
                'rating_count' => (int) 0,
                'discount_percentage' => (float) 0,
                'images' => [],
                'main_image' => null,
                'category_id' => 0,
                'category_name' => null,
                'stock' => 0,
                'is_in_stock' => false,
                'featured' => false,
                'published' => false,
                'status' => 'inactive',
                'tags' => null,
                'seller_id' => 0,
                'created_at' => null,
            ];
        } catch (\Exception $e) {
            Log::error('Error formateando producto para API: '.$e->getMessage(), [
                'product_type' => get_class($product),
                'product_id' => $product instanceof Product ? $product->id : ($product instanceof ProductEntity ? $product->getId() : 'unknown'),
            ]);

            return [
                'id' => $product instanceof Product ? $product->id : ($product instanceof ProductEntity ? $product->getId() : 0),
                'name' => $product instanceof Product ? $product->name : ($product instanceof ProductEntity ? $product->getName() : 'Error al formatear producto'),
                'slug' => '',
                'price' => (float) 0,
                'final_price' => (float) 0,
                'rating' => (float) 0,
                'rating_count' => (int) 0,
                'discount_percentage' => (float) 0,
                'images' => [],
                'main_image' => null,
                'category_id' => 0,
                'category_name' => null,
                'stock' => 0,
                'is_in_stock' => false,
                'featured' => false,
                'published' => false,
                'status' => 'error',
                'tags' => null,
                'seller_id' => 0,
                'created_at' => null,
            ];
        }
    }

    /**
     * Procesa las imágenes y convierte rutas locales a URLs completas
     */
    private function processImages($images): array
    {
        if (! $images) {
            return [];
        }

        // Si es string JSON, decodificar
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (! is_array($images)) {
            return [];
        }

        $processedImages = [];

        foreach ($images as $index => $image) {
            if (is_string($image)) {
                // Si es string directo, convertir a URL si es necesario
                $processedUrl = $this->convertToFullUrl($image);
                $processedImages[] = $processedUrl;
            } elseif (is_array($image)) {
                // Si es objeto con diferentes tamaños, procesar cada tamaño
                $processedImage = [];
                foreach ($image as $size => $url) {
                    $processedImage[$size] = $this->convertToFullUrl($url);
                }
                $processedImages[] = $processedImage;
            }
        }

        return $processedImages;
    }

    /**
     * Convierte una ruta/URL a URL completa
     */
    private function convertToFullUrl(string $path): string
    {
        // Si ya es una URL completa (tiene http/https), devolverla tal como está
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Si es una ruta local, convertir a URL completa
        return asset('storage/'.$path);
    }

    /**
     * Formatea detalles completos de un producto
     */
    public function formatComplete(ProductEntity $product): array
    {
        try {
            $productData = $product->toArray();

            // Agregar campos calculados
            $productData['final_price'] = (float) $this->calculateFinalPrice($product);
            $productData['main_image'] = $this->getMainImageUrl($product);
            $productData['is_in_stock'] = $product->getStock() > 0;

            // Asegurar tipos correctos para campos númericos
            $productData['price'] = (float) $productData['price'];
            $productData['rating'] = (float) ($productData['rating'] ?? 0);
            $productData['rating_count'] = (int) ($productData['rating_count'] ?? 0);
            $productData['discount_percentage'] = (float) ($productData['discount_percentage'] ?? 0);
            $productData['stock'] = (int) ($productData['stock'] ?? 0);
            $productData['category_id'] = (int) ($productData['category_id'] ?? 0);

            // Asegurar que seller_id esté presente
            if (! isset($productData['seller_id']) || $productData['seller_id'] === null) {
                $productData['seller_id'] = $product->getUserId();
            }

            // Obtener información de la categoría si está disponible
            if ($this->categoryRepository && $product->getCategoryId()) {
                $category = $this->categoryRepository->findById($product->getCategoryId());
                if ($category) {
                    $productData['category'] = [
                        'id' => $category->getId()->getValue(),
                        'name' => $category->getName(),
                        'slug' => $category->getSlug()->getValue(),
                    ];
                }
            }

            return $productData;
        } catch (\Exception $e) {
            Log::error('Error formateando detalles completos del producto: '.$e->getMessage());

            $basicData = $product->toArray();
            // Asegurar tipos correctos para campos numéricos en el fallback
            $basicData['price'] = (float) ($basicData['price'] ?? 0);
            $basicData['rating'] = (float) ($basicData['rating'] ?? 0);
            $basicData['rating_count'] = (int) ($basicData['rating_count'] ?? 0);
            $basicData['discount_percentage'] = (float) ($basicData['discount_percentage'] ?? 0);
            $basicData['stock'] = (int) ($basicData['stock'] ?? 0);
            $basicData['category_id'] = (int) ($basicData['category_id'] ?? 0);

            if (! isset($basicData['seller_id']) || $basicData['seller_id'] === null) {
                $basicData['seller_id'] = (int) $product->getUserId();
            } else {
                $basicData['seller_id'] = (int) $basicData['seller_id'];
            }

            return array_merge($basicData, [
                'error' => 'Error al formatear los detalles completos',
            ]);
        }
    }

    /**
     * Calcula el precio final con descuento si aplica
     */
    private function calculateFinalPrice(ProductEntity $product): float
    {
        if ($product->getDiscountPercentage() > 0) {
            return round($product->getPrice() * (1 - $product->getDiscountPercentage() / 100), 2);
        }

        return $product->getPrice();
    }

    /**
     * Obtiene la URL de la imagen principal del producto
     *
     * @param  ProductEntity|Product  $product
     */
    private function getMainImageUrl($product): ?string
    {
        // Para modelo Eloquent
        if ($product instanceof Product) {
            $images = $product->images;
        } else {
            // Para ProductEntity
            $images = $product->getImages();
        }

        if (! $images || empty($images)) {
            return null;
        }

        // Si es string JSON, decodificar
        if (is_string($images)) {
            $images = json_decode($images, true);
        }

        if (! is_array($images) || empty($images)) {
            return null;
        }

        $firstImage = $images[0] ?? null;

        if (! $firstImage) {
            return null;
        }

        // Si es string directo
        if (is_string($firstImage)) {
            return $this->convertToFullUrl($firstImage);
        }

        // Si es objeto con diferentes tamaños
        if (is_array($firstImage)) {
            // Priorizar medium, luego original, luego otros
            $imageUrl = $firstImage['medium'] ??
                $firstImage['original'] ??
                $firstImage['large'] ??
                $firstImage['thumbnail'] ??
                $firstImage['small'] ??
                null;

            return $imageUrl ? $this->convertToFullUrl($imageUrl) : null;
        }

        return null;
    }

    /**
     * Obtiene el nombre de una categoría a partir de su ID
     */
    private function getCategoryName(int $categoryId): ?string
    {
        if (! $this->categoryRepository) {
            return null;
        }

        try {
            $category = $this->categoryRepository->findById($categoryId);

            return $category ? $category->getName() : null;
        } catch (\Exception $e) {
            Log::error('Error obteniendo nombre de categoría: '.$e->getMessage());

            return null;
        }
    }
}
