<?php

namespace App\Models;

use App\Domain\ValueObjects\ShippingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipping extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', // DEPRECATED: Mantener por compatibilidad durante migración
        'seller_order_id', // NUEVO: Asociación correcta a SellerOrder
        'tracking_number',
        'status',
        'current_location',
        'estimated_delivery',
        'delivered_at',
        'carrier_id',
        'carrier_name',
        'history',
        'last_updated',
    ];

    protected $casts = [
        'current_location' => 'array',
        'history' => 'array',
        'estimated_delivery' => 'datetime',
        'delivered_at' => 'datetime',
        'last_updated' => 'datetime',
    ];

    /**
     * Obtener la SellerOrder relacionada a este envío (NUEVA RELACIÓN PRINCIPAL)
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class);
    }

    /**
     * Obtener la orden relacionada a este envío (DEPRECATED - mantener por compatibilidad)
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Obtener la orden principal a través de SellerOrder (NUEVA FORMA RECOMENDADA)
     */
    public function mainOrder(): BelongsTo
    {
        return $this->hasOneThrough(Order::class, SellerOrder::class, 'id', 'id', 'seller_order_id', 'order_id');
    }

    /**
     * Obtener el historial de este envío
     */
    public function history(): HasMany
    {
        return $this->hasMany(ShippingHistory::class)->orderBy('timestamp', 'asc');
    }

    /**
     * Obtener los puntos de ruta de este envío
     */
    public function routePoints(): HasMany
    {
        return $this->hasMany(ShippingRoutePoint::class)->orderBy('timestamp', 'asc');
    }

    /**
     * Obtener el transportista relacionado
     */
    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class);
    }

    /**
     * Obtener la dirección del envío desde SellerOrder o Order
     */
    public function getAddressAttribute()
    {
        // Priorizar SellerOrder si existe
        if ($this->sellerOrder && $this->sellerOrder->shipping_data) {
            return $this->sellerOrder->shipping_data['address'] ?? null;
        }

        // Fallback a Order para compatibilidad
        return $this->order->shipping_data['address'] ?? null;
    }

    /**
     * Obtener la ciudad del envío desde SellerOrder o Order
     */
    public function getCityAttribute()
    {
        // Priorizar SellerOrder si existe
        if ($this->sellerOrder && $this->sellerOrder->shipping_data) {
            return $this->sellerOrder->shipping_data['city'] ?? null;
        }

        // Fallback a Order para compatibilidad
        return $this->order->shipping_data['city'] ?? null;
    }

    /**
     * Obtener el estado/provincia del envío desde SellerOrder o Order
     */
    public function getStateAttribute()
    {
        // Priorizar SellerOrder si existe
        if ($this->sellerOrder && $this->sellerOrder->shipping_data) {
            return $this->sellerOrder->shipping_data['state'] ?? null;
        }

        // Fallback a Order para compatibilidad
        return $this->order->shipping_data['state'] ?? null;
    }

    /**
     * Obtener el país del envío desde Order.shipping_data
     */
    public function getCountryAttribute()
    {
        return $this->order->shipping_data['country'] ?? null;
    }

    /**
     * Obtener el código postal del envío desde Order.shipping_data
     */
    public function getPostalCodeAttribute()
    {
        return $this->order->shipping_data['postal_code'] ?? null;
    }

    /**
     * Obtener el teléfono del envío desde Order.shipping_data
     */
    public function getPhoneAttribute()
    {
        return $this->order->shipping_data['phone'] ?? null;
    }

    /**
     * Obtener la dirección completa como string
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Verificar si el envío está en un estado final
     */
    public function isFinalStatus(): bool
    {
        return ShippingStatus::isFinalStatus($this->status);
    }

    /**
     * Agregar un nuevo evento al historial de envío
     */
    public function addHistoryEvent(string $status, ?array $location = null, ?string $details = null, ?\DateTime $timestamp = null): ShippingHistory
    {
        $timestamp = $timestamp ?? now();

        // Capturar estado anterior para el evento
        $previousStatus = $this->status;

        // Actualizar estado actual y ubicación
        $this->status = $status;
        if ($location) {
            $this->current_location = $location;
        }

        $this->last_updated = $timestamp;

        // Si es entregado, actualizar delivered_at
        if ($status === ShippingStatus::DELIVERED) {
            $this->delivered_at = $timestamp;
        }

        $this->save();

        // Disparar evento de actualización de estado
        if ($previousStatus !== $status) {
            event(new \App\Events\ShippingStatusUpdated($this->id, $previousStatus, $status));
        }

        // Crear registro de historial - corregir para que almacene JSON
        $history = new ShippingHistory([
            'shipping_id' => $this->id, // Añadir shipping_id
            'status' => $status,
            'status_description' => ShippingStatus::getDescription($status),
            'location' => $location,
            'details' => $details,
            'timestamp' => $timestamp,
        ]);

        $this->history()->save($history);

        // Crear punto de ruta si hay ubicación
        if ($location && isset($location['lat'], $location['lng'])) {
            $routePoint = new ShippingRoutePoint([
                'latitude' => $location['lat'],
                'longitude' => $location['lng'],
                'address' => $location['address'] ?? null,
                'timestamp' => $timestamp,
                'status' => $status,
                'notes' => $details,
            ]);

            $this->routePoints()->save($routePoint);
        }

        return $history;
    }

    /**
     * Generar un número de tracking
     */
    public static function generateTrackingNumber(): string
    {
        do {
            $prefix = 'TRK'; // Prefijo para identificar números de tracking
            $timestamp = str_pad(substr(time(), -6), 8, '0'); // Últimos 8 dígitos del timestamp
            $random = str_pad(mt_rand(1000, 9999), 4, '0'); // Número aleatorio de 4 dígitos

            $trackingNumber = $prefix.$timestamp.$random;
        } while (self::where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    /**
     * Update shipping status and create history entry
     */
    public function updateStatus(
        string $newStatus,
        ?array $location = null,
        ?string $details = null
    ): self {
        // Update the shipping status
        $this->status = $newStatus;
        $this->save();

        // Create a history entry
        ShippingHistory::createEntry(
            $this->id,
            $newStatus,
            $location ? json_encode($location) : null,
            $details
        );

        return $this;
    }
}
