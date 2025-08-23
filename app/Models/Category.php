<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'icon',
        'image',
        'order',
        'is_active',
        'featured',
    ];

    /**
     * ✅ CRITICAL: Definir casts para asegurar tipos correctos
     */
    protected $casts = [
        'is_active' => 'boolean',          // ✅ Crucial para persistencia
        'featured' => 'boolean',           // ✅ Crucial para persistencia
        'parent_id' => 'integer',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ✅ OPCIONAL: Definir valores por defecto
     */
    protected $attributes = [
        'is_active' => true,
        'featured' => false,
        'order' => 0,
    ];

    // ... resto de métodos del modelo ...

    /**
     * ✅ MÉTODO HELPER para verificar estado activo
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * ✅ MÉTODO HELPER para verificar estado destacado
     */
    public function isFeatured(): bool
    {
        return (bool) $this->featured;
    }

    /**
     * ✅ MÉTODO HELPER para obtener is_active como booleano
     */
    public function getIsActiveAttribute($value): bool
    {
        return (bool) $value;
    }

    /**
     * ✅ MÉTODO HELPER para obtener featured como booleano
     */
    public function getFeaturedAttribute($value): bool
    {
        return (bool) $value;
    }

    /**
     * ✅ MUTADOR para asegurar que is_active se guarde como booleano
     */
    public function setIsActiveAttribute($value): void
    {
        $this->attributes['is_active'] = (bool) $value;
    }

    /**
     * ✅ MUTADOR para asegurar que featured se guarde como booleano
     */
    public function setFeaturedAttribute($value): void
    {
        $this->attributes['featured'] = (bool) $value;
    }

    /**
     * Relación con categoría padre
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Relación con subcategorías
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Relación con productos
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
