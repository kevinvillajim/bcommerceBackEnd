<?php

namespace App\Domain\Entities;

use Illuminate\Support\Facades\Log;

class ProductEntity
{
    private ?int $id;

    private int $userId;

    private ?int $sellerId;

    private int $categoryId;

    private string $name;

    private string $slug;

    private string $description;

    private float $rating = 0;

    private int $ratingCount = 0;

    private float $price;

    private int $stock;

    private ?float $weight;

    private ?float $width;

    private ?float $height;

    private ?float $depth;

    private ?string $dimensions;

    private ?array $colors;

    private ?array $sizes;

    private ?array $tags;

    private ?string $sku;

    private ?array $attributes;

    private ?array $images;

    private bool $featured;

    private bool $published;

    private string $status;

    private int $viewCount;

    private int $salesCount;

    private float $discountPercentage;

    private ?string $shortDescription;

    private ?string $createdAt;

    private ?string $updatedAt;

    // ✅ Propiedad agregada para almacenar la categoría relacionada
    private ?object $category = null;

    // ✅ NUEVO: Propiedades para descuentos por volumen
    private ?array $volumeDiscounts = null;

    private bool $hasVolumeDiscounts = false;

    public function __construct(
        int $userId,
        int $categoryId,
        string $name,
        string $slug,
        string $description,
        float $rating,
        int $ratingCount,
        float $price,
        int $stock,
        ?int $sellerId = null,
        ?float $weight = null,
        ?float $width = null,
        ?float $height = null,
        ?float $depth = null,
        ?string $dimensions = null,
        ?array $colors = null,
        ?array $sizes = null,
        ?array $tags = null,
        ?string $sku = null,
        ?array $attributes = null,
        ?array $images = null,
        bool $featured = false,
        bool $published = true,
        string $status = 'active',
        int $viewCount = 0,
        int $salesCount = 0,
        float $discountPercentage = 0,
        ?string $shortDescription = null,
        ?int $id = null,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->userId = $userId;
        $this->sellerId = $sellerId;
        $this->categoryId = $categoryId;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->rating = $rating;
        $this->ratingCount = $ratingCount;
        $this->price = $price;
        $this->stock = $stock;
        $this->weight = $weight;
        $this->width = $width;
        $this->height = $height;
        $this->depth = $depth;
        $this->dimensions = $dimensions;
        $this->colors = $colors;
        $this->sizes = $sizes;
        $this->tags = $tags;
        $this->sku = $sku;
        $this->attributes = $attributes;

        // ✅ VALIDACIÓN MEJORADA PARA IMAGES: Asegurar que sea array o null
        if ($images !== null) {
            if (is_array($images)) {
                // Aceptar tanto strings como arrays/objetos
                $this->images = array_filter($images, function ($img) {
                    return (is_string($img) && ! empty($img)) ||
                        (is_array($img) && ! empty($img));
                });
            } elseif (is_string($images)) {
                // Si viene como string (posiblemente JSON), intentar decodificar
                $decodedImages = json_decode($images, true);
                if (is_array($decodedImages)) {
                    $this->images = array_filter($decodedImages, 'is_string');
                } else {
                    // Si no es JSON válido, tratarlo como imagen única
                    $this->images = [$images];
                }
            } else {
                // Cualquier otro tipo, convertir a array vacío
                $this->images = [];
            }
        } else {
            $this->images = null;
        }

        $this->featured = $featured;
        $this->published = $published;
        $this->status = $status;
        $this->viewCount = $viewCount;
        $this->salesCount = $salesCount;
        $this->discountPercentage = $discountPercentage;
        $this->shortDescription = $shortDescription;
        $this->id = $id;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    // Getters existentes
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSellerId(): ?int
    {
        return $this->sellerId;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getRating(): float
    {
        return $this->rating;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function getDepth(): ?float
    {
        return $this->depth;
    }

    public function getDimensions(): ?string
    {
        return $this->dimensions;
    }

    public function getColors(): ?array
    {
        return $this->colors;
    }

    public function getSizes(): ?array
    {
        return $this->sizes;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function getImages(): ?array
    {
        return $this->images;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function getSalesCount(): int
    {
        return $this->salesCount;
    }

    public function getDiscountPercentage(): float
    {
        return $this->discountPercentage;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    // ✅ MÉTODOS AGREGADOS para compatibilidad con controladores

    /**
     * Calcula el precio final aplicando descuento si existe
     */
    public function getFinalPrice(): float
    {
        return $this->calculateFinalPrice();
    }

    /**
     * ✅ NUEVO: Calcula precio con descuento por volumen
     */
    public function getVolumePriceForQuantity(int $quantity): array
    {
        $basePrice = $this->calculateFinalPrice(); // Precio base con descuento regular

        if (! $this->hasVolumeDiscounts || empty($this->volumeDiscounts)) {
            return [
                'original_price' => $basePrice,
                'discounted_price' => $basePrice,
                'discount_percentage' => 0,
                'savings' => 0,
                'discount_label' => null,
                'tier_label' => null,
            ];
        }

        // Buscar el descuento aplicable para la cantidad
        $applicableDiscount = null;
        foreach ($this->volumeDiscounts as $discount) {
            if ($quantity >= $discount['quantity']) {
                $applicableDiscount = $discount;
            } else {
                break; // Los descuentos están ordenados, no hay más aplicables
            }
        }

        if (! $applicableDiscount) {
            return [
                'original_price' => $basePrice,
                'discounted_price' => $basePrice,
                'discount_percentage' => 0,
                'savings' => 0,
                'discount_label' => null,
                'tier_label' => null,
            ];
        }

        $discountedPrice = $basePrice * (1 - $applicableDiscount['discount'] / 100);
        $savings = $basePrice - $discountedPrice;

        return [
            'original_price' => $basePrice,
            'discounted_price' => $discountedPrice,
            'discount_percentage' => $applicableDiscount['discount'],
            'savings' => $savings,
            'discount_label' => $applicableDiscount['label'],
            'tier_label' => "Descuento por {$quantity}+ unidades",
        ];
    }

    /**
     * ✅ NUEVO: Obtener todos los niveles de descuento por volumen
     */
    public function getVolumeDiscountTiers(): array
    {
        return $this->volumeDiscounts ?? [];
    }

    /**
     * ✅ NUEVO: Verificar si tiene descuentos por volumen
     */
    public function hasVolumeDiscounts(): bool
    {
        return $this->hasVolumeDiscounts && ! empty($this->volumeDiscounts);
    }

    /**
     * ✅ NUEVO: Establecer descuentos por volumen
     */
    public function setVolumeDiscounts(array $discounts): void
    {
        $this->volumeDiscounts = $discounts;
        $this->hasVolumeDiscounts = ! empty($discounts);
    }

    /**
     * ✅ NUEVO: Obtener el próximo nivel de descuento para motivar la compra
     */
    public function getNextVolumeDiscount(int $currentQuantity): ?array
    {
        if (! $this->hasVolumeDiscounts || empty($this->volumeDiscounts)) {
            return null;
        }

        foreach ($this->volumeDiscounts as $discount) {
            if ($currentQuantity < $discount['quantity']) {
                return [
                    'quantity' => $discount['quantity'],
                    'discount' => $discount['discount'],
                    'label' => $discount['label'],
                    'items_needed' => $discount['quantity'] - $currentQuantity,
                ];
            }
        }

        return null; // Ya tiene el máximo descuento
    }

    /**
     * Obtiene la imagen principal del producto
     */
    public function getMainImage(): ?string
    {
        // Debug para ver qué tipo de datos tenemos
        Log::debug('ProductEntity::getMainImage() - Debug de imágenes', [
            'images_type' => gettype($this->images),
            'images_value' => $this->images,
            'is_array' => is_array($this->images),
            'is_string' => is_string($this->images),
            'is_null' => is_null($this->images),
            'empty_check' => empty($this->images),
        ]);

        if (is_null($this->images) || empty($this->images)) {
            return null;
        }

        if (is_array($this->images)) {
            $firstImage = $this->images[0] ?? null;
            if ($firstImage && is_string($firstImage)) {
                return $firstImage;
            }

            return null;
        }

        if (is_string($this->images)) {
            $decodedImages = json_decode($this->images, true);
            if (is_array($decodedImages) && ! empty($decodedImages)) {
                $firstImage = $decodedImages[0] ?? null;
                if ($firstImage && is_string($firstImage)) {
                    return $firstImage;
                }
            }

            return $this->images;
        }

        return null;
    }

    /**
     * Obtiene la categoría relacionada (si está cargada)
     */
    public function getCategory(): ?object
    {
        return $this->category;
    }

    /**
     * Establece la categoría relacionada
     */
    public function setCategory(?object $category): void
    {
        $this->category = $category;
    }

    // Setters existentes...
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setCategoryId(int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function setRating(float $rating): void
    {
        $this->rating = $rating;
    }

    public function setRatingCount(int $ratingCount): void
    {
        $this->ratingCount = $ratingCount;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function setStock(int $stock): void
    {
        $this->stock = $stock;
    }

    public function setWeight(?float $weight): void
    {
        $this->weight = $weight;
    }

    public function setWidth(?float $width): void
    {
        $this->width = $width;
    }

    public function setHeight(?float $height): void
    {
        $this->height = $height;
    }

    public function setDepth(?float $depth): void
    {
        $this->depth = $depth;
    }

    public function setDimensions(?string $dimensions): void
    {
        $this->dimensions = $dimensions;
    }

    public function setColors(?array $colors): void
    {
        $this->colors = $colors;
    }

    public function setSizes(?array $sizes): void
    {
        $this->sizes = $sizes;
    }

    public function setTags(?array $tags): void
    {
        $this->tags = $tags;
    }

    public function setSku(?string $sku): void
    {
        $this->sku = $sku;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function setImages(?array $images): void
    {
        $this->images = $images;
    }

    public function setFeatured(bool $featured): void
    {
        $this->featured = $featured;
    }

    public function setPublished(bool $published): void
    {
        $this->published = $published;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setViewCount(int $viewCount): void
    {
        $this->viewCount = $viewCount;
    }

    public function setSalesCount(int $salesCount): void
    {
        $this->salesCount = $salesCount;
    }

    public function setDiscountPercentage(float $discountPercentage): void
    {
        $this->discountPercentage = $discountPercentage;
    }

    public function setShortDescription(?string $shortDescription): void
    {
        $this->shortDescription = $shortDescription;
    }

    public function incrementViewCount(): void
    {
        $this->viewCount++;
    }

    public function incrementSalesCount(int $quantity = 1): void
    {
        $this->salesCount += $quantity;
    }

    public function decrementStock(int $quantity = 1): void
    {
        $this->stock = max(0, $this->stock - $quantity);
    }

    public function calculateFinalPrice(): float
    {
        if ($this->discountPercentage > 0) {
            return $this->price * (1 - $this->discountPercentage / 100);
        }

        return $this->price;
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    // Helper methods
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'seller_id' => $this->sellerId,
            'category_id' => $this->categoryId,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'rating' => $this->rating,
            'rating_count' => $this->ratingCount,
            'price' => $this->price,
            'stock' => $this->stock,
            'weight' => $this->weight,
            'width' => $this->width,
            'height' => $this->height,
            'depth' => $this->depth,
            'dimensions' => $this->dimensions,
            'colors' => $this->colors,
            'sizes' => $this->sizes,
            'tags' => $this->tags,
            'sku' => $this->sku,
            'attributes' => $this->attributes,
            'images' => $this->images,
            'featured' => $this->featured,
            'published' => $this->published,
            'status' => $this->status,
            'view_count' => $this->viewCount,
            'sales_count' => $this->salesCount,
            'discount_percentage' => $this->discountPercentage,
            'short_description' => $this->shortDescription,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            // ✅ NUEVO: Información de descuentos por volumen
            'has_volume_discounts' => $this->hasVolumeDiscounts,
            'volume_discounts' => $this->volumeDiscounts,
        ];
    }
}
