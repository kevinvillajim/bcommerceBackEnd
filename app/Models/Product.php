<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'stock',
        'category_id',
        'user_id',
        'seller_id',
        'sku',
        'weight',
        'width',
        'height',
        'depth',
        'dimensions',
        'colors',
        'sizes',
        'tags',
        'attributes',
        'images',
        'featured',
        'published',
        'status',
        'discount_percentage',
        'view_count',
        'sales_count',
    ];

    /**
     * ✅ CRITICAL: Definir casts para asegurar tipos correctos
     */
    protected $casts = [
        'featured' => 'boolean',           // ✅ Crucial para persistencia
        'published' => 'boolean',          // ✅ Crucial para persistencia
        'price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'weight' => 'decimal:3',
        'width' => 'decimal:2',
        'height' => 'decimal:2',
        'depth' => 'decimal:2',
        'stock' => 'integer',
        'view_count' => 'integer',
        'sales_count' => 'integer',
        'category_id' => 'integer',
        'user_id' => 'integer',
        'seller_id' => 'integer',
        'colors' => 'array',               // ✅ Cambio de 'json' a 'array'
        'sizes' => 'array',                // ✅ Cambio de 'json' a 'array'
        'tags' => 'array',                 // ✅ Cambio de 'json' a 'array'
        'attributes' => 'array',           // ✅ Cambio de 'json' a 'array'
        'images' => 'array',               // ✅ AGREGADO: Cast para imágenes
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ✅ OPCIONAL: Definir valores por defecto
     */
    protected $attributes = [
        'featured' => false,
        'published' => true,
        'status' => 'active',
        'view_count' => 0,
        'sales_count' => 0,
        'discount_percentage' => 0,
    ];

    /**
     * ✅ NUEVO: Relación con descuentos por volumen
     */
    public function volumeDiscounts()
    {
        return $this->hasMany(VolumeDiscount::class)->active()->orderBy('min_quantity');
    }

    /**
     * ✅ MÉTODO HELPER para verificar estado destacado
     */
    public function isFeatured(): bool
    {
        return (bool) $this->featured;
    }

    /**
     * ✅ MÉTODO HELPER para verificar estado publicado
     */
    public function isPublished(): bool
    {
        return (bool) $this->published;
    }

    /**
     * ✅ ACCESSOR CORREGIDO para featured
     */
    public function getFeaturedAttribute($value): bool
    {
        return (bool) $value;
    }

    /**
     * ✅ ACCESSOR CORREGIDO para published
     */
    public function getPublishedAttribute($value): bool
    {
        return (bool) $value;
    }

    /**
     * ✅ MUTADOR para asegurar que featured se guarde como booleano
     */
    public function setFeaturedAttribute($value): void
    {
        $this->attributes['featured'] = (bool) $value;
    }

    /**
     * ✅ MUTADOR para asegurar que published se guarde como booleano
     */
    public function setPublishedAttribute($value): void
    {
        $this->attributes['published'] = (bool) $value;
    }

    /**
     * ✅ MUTADOR para arrays JSON - colors
     */
    public function setColorsAttribute($value): void
    {
        if (is_string($value)) {
            $this->attributes['colors'] = $value;
        } elseif (is_array($value)) {
            $this->attributes['colors'] = json_encode($value);
        } else {
            $this->attributes['colors'] = null;
        }
    }

    /**
     * ✅ MUTADOR para arrays JSON - sizes
     */
    public function setSizesAttribute($value): void
    {
        if (is_string($value)) {
            $this->attributes['sizes'] = $value;
        } elseif (is_array($value)) {
            $this->attributes['sizes'] = json_encode($value);
        } else {
            $this->attributes['sizes'] = null;
        }
    }

    /**
     * ✅ MUTADOR para arrays JSON - tags
     */
    public function setTagsAttribute($value): void
    {
        if (is_string($value)) {
            $this->attributes['tags'] = $value;
        } elseif (is_array($value)) {
            $this->attributes['tags'] = json_encode($value);
        } else {
            $this->attributes['tags'] = null;
        }
    }

    /**
     * ✅ MUTADOR para arrays JSON - attributes
     */
    public function setAttributesAttribute($value): void
    {
        if (is_string($value)) {
            $this->attributes['attributes'] = $value;
        } elseif (is_array($value)) {
            $this->attributes['attributes'] = json_encode($value);
        } else {
            $this->attributes['attributes'] = null;
        }
    }

    /**
     * ✅ MÉTODO para obtener URL de imagen principal
     * Extrae la primera imagen del campo images JSON
     */
    public function getMainImageUrl(): ?string
    {
        $images = $this->images;

        if (empty($images) || ! is_array($images)) {
            return null;
        }

        $firstImage = $images[0] ?? null;

        if (! $firstImage || ! is_array($firstImage)) {
            return null;
        }

        // Priorizar 'original', luego 'large', luego 'medium', luego cualquiera
        $priorities = ['original', 'large', 'medium', 'thumbnail'];

        foreach ($priorities as $key) {
            if (isset($firstImage[$key]) && ! empty($firstImage[$key])) {
                return $firstImage[$key];
            }
        }

        // Si no encuentra ninguno con prioridad, devolver el primer valor
        return array_values($firstImage)[0] ?? null;
    }

    /**
     * ✅ ACCESSOR para main_image_url (compatibilidad)
     */
    public function getMainImageUrlAttribute(): ?string
    {
        return $this->getMainImageUrl();
    }

    /**
     * Relación con la categoría
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relación con el vendedor (si es diferente del usuario)
     */
    public function seller()
    {
        return $this->belongsTo(\App\Models\User::class, 'seller_id');
    }

    /**
     * Scope para productos activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('published', true);
    }

    /**
     * Scope para productos destacados
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope para productos en stock
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * ✅ NUEVO: Calcular precio final con descuento por volumen
     */
    public function getFinalPriceAttribute(): float
    {
        // Si no hay descuento regular, devolver precio base
        if ($this->discount_percentage <= 0) {
            return $this->price;
        }

        return $this->price * (1 - $this->discount_percentage / 100);
    }

    /**
     * ✅ FIX: Método getFinalPrice() que faltaba (para ProductFormatter)
     */
    public function getFinalPrice(): float
    {
        return $this->getFinalPriceAttribute();
    }

    /**
     * ✅ NUEVO: Calcular precio con descuento por volumen para una cantidad específica
     */
    public function getVolumePrice(int $quantity = 1): array
    {
        $basePrice = $this->final_price; // Usar precio ya con descuento regular

        return VolumeDiscount::calculateVolumePrice($this->id, $basePrice, $quantity);
    }

    /**
     * ✅ NUEVO: Obtener niveles de descuento por volumen
     */
    public function getVolumeDiscountTiers(): array
    {
        return VolumeDiscount::getDiscountTiers($this->id);
    }

    /**
     * ✅ NUEVO: Verificar si tiene descuentos por volumen activos
     */
    public function hasVolumeDiscounts(): bool
    {
        return $this->volumeDiscounts()->count() > 0;
    }

    /**
     * ✅ NUEVO: Obtener el mejor descuento disponible para una cantidad
     */
    public function getBestVolumeDiscount(int $quantity): ?VolumeDiscount
    {
        return VolumeDiscount::getDiscountForQuantity($this->id, $quantity);
    }

    /**
     * Verificar si el producto está en stock
     */
    public function getInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Obtener la imagen principal del producto
     */
    public function getMainImageAttribute(): ?string
    {
        $images = $this->images ?? [];

        // Si images es string, decodificar JSON
        if (is_string($images)) {
            $images = json_decode($images, true) ?? [];
        }

        // Asegurar que es un array y obtener el primer elemento
        if (is_array($images) && ! empty($images)) {
            $firstImage = $images[0];

            // Asegurar que el primer elemento es string
            return is_string($firstImage) ? $firstImage : null;
        }

        return null;
    }

    /**
     * Obtener todas las imágenes del producto
     */
    public function getImagesAttribute($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Establecer imágenes del producto
     */
    public function setImagesAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['images'] = json_encode($value);
        } elseif (is_string($value)) {
            $this->attributes['images'] = $value;
        } else {
            $this->attributes['images'] = null;
        }
    }
}
