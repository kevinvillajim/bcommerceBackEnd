<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'category',
        'is_active',
    ];

    protected $casts = [
        'value' => 'json',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener una configuración por su clave
     */
    public static function getValue(string $key, $default = null)
    {
        $config = static::where('key', $key)
            ->where('is_active', true)
            ->first();

        if (!$config) {
            return $default;
        }

        return $config->value;
    }

    /**
     * Establecer una configuración
     */
    public static function setValue(string $key, $value, string $description = null, string $category = 'general')
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'description' => $description,
                'category' => $category,
                'is_active' => true,
            ]
        );
    }

    /**
     * Obtener configuraciones por categoría
     */
    public static function getByCategory(string $category)
    {
        return static::where('category', $category)
            ->where('is_active', true)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
    }
}