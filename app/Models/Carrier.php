<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'api_key',
        'api_secret',
        'tracking_url_format',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Obtener los envíos asociados a este transportista
     */
    public function shippings(): HasMany
    {
        return $this->hasMany(Shipping::class);
    }

    /**
     * Generar una URL de seguimiento para un número de tracking
     */
    public function getTrackingUrl(string $trackingNumber): string
    {
        if (empty($this->tracking_url_format)) {
            return '';
        }

        return str_replace('{tracking_number}', $trackingNumber, $this->tracking_url_format);
    }

    /**
     * Obtener un transportista por su código
     */
    public static function getByCode(string $code): ?self
    {
        return self::where('code', $code)->where('is_active', true)->first();
    }
}
