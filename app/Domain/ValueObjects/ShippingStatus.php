<?php

namespace App\Domain\ValueObjects;

/**
 * Value Object para los estados de envío
 */
final class ShippingStatus
{
    // Estados principales
    public const PENDING = 'pending';             // Pedido recibido, pero aún no procesado para envío

    public const PROCESSING = 'processing';       // El pedido está siendo procesado en el almacén

    public const READY_FOR_PICKUP = 'ready_for_pickup'; // Listo para ser recogido por el transportista

    public const READY_TO_SHIP = 'ready_to_ship'; // Listo para enviar (usado por seller)

    public const PICKED_UP = 'picked_up';         // Recogido por el transportista

    public const SHIPPED = 'shipped';             // Enviado (estado genérico)

    public const IN_TRANSIT = 'in_transit';       // En camino (entre centros de distribución)

    public const OUT_FOR_DELIVERY = 'out_for_delivery'; // En camino al destino final

    public const DELIVERED = 'delivered';         // Entregado al destinatario

    public const EXCEPTION = 'exception';         // Problema durante el envío

    public const RETURNED = 'returned';           // Devuelto al remitente

    public const CANCELLED = 'cancelled';         // Cancelado

    public const FAILED = 'failed';               // Fallido (usado por seller)

    // Estados de excepción
    public const EXCEPTION_WEATHER = 'exception_weather';           // Retraso por condiciones climáticas

    public const EXCEPTION_ADDRESS = 'exception_address';           // Problema con la dirección

    public const EXCEPTION_DAMAGED = 'exception_damaged';           // Paquete dañado

    public const EXCEPTION_CUSTOMS = 'exception_customs';           // Retenido en aduanas

    public const EXCEPTION_DELIVERY_ATTEMPT = 'exception_delivery_attempt'; // Intento de entrega fallido

    /**
     * Lista de todos los estados válidos
     */
    public static function getAllStatuses(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::READY_FOR_PICKUP,
            self::READY_TO_SHIP,
            self::PICKED_UP,
            self::SHIPPED,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::EXCEPTION,
            self::RETURNED,
            self::CANCELLED,
            self::FAILED,
            self::EXCEPTION_WEATHER,
            self::EXCEPTION_ADDRESS,
            self::EXCEPTION_DAMAGED,
            self::EXCEPTION_CUSTOMS,
            self::EXCEPTION_DELIVERY_ATTEMPT,
        ];
    }

    /**
     * Verifica si el estado proporcionado es válido
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::getAllStatuses());
    }

    /**
     * Obtiene una descripción legible del estado
     */
    public static function getDescription(string $status): string
    {
        $descriptions = [
            self::PENDING => 'Pendiente de procesamiento',
            self::PROCESSING => 'En procesamiento',
            self::READY_FOR_PICKUP => 'Listo para recoger',
            self::READY_TO_SHIP => 'Listo para enviar',
            self::PICKED_UP => 'Recogido por transportista',
            self::SHIPPED => 'Enviado',
            self::IN_TRANSIT => 'En tránsito',
            self::OUT_FOR_DELIVERY => 'En camino para entrega',
            self::DELIVERED => 'Entregado',
            self::EXCEPTION => 'Problema en el envío',
            self::RETURNED => 'Devuelto al remitente',
            self::CANCELLED => 'Cancelado',
            self::FAILED => 'Fallido',
            self::EXCEPTION_WEATHER => 'Retraso por condiciones climáticas',
            self::EXCEPTION_ADDRESS => 'Problema con la dirección',
            self::EXCEPTION_DAMAGED => 'Paquete dañado',
            self::EXCEPTION_CUSTOMS => 'Retenido en aduanas',
            self::EXCEPTION_DELIVERY_ATTEMPT => 'Intento de entrega fallido',
        ];

        return $descriptions[$status] ?? 'Estado desconocido';
    }

    /**
     * Verifica si el estado es una excepción
     */
    public static function isException(string $status): bool
    {
        return $status === self::EXCEPTION ||
            strpos($status, 'exception_') === 0;
    }

    /**
     * Verifica si el estado es uno final (no se esperan más cambios)
     */
    public static function isFinalStatus(string $status): bool
    {
        return in_array($status, [
            self::DELIVERED,
            self::RETURNED,
            self::CANCELLED,
        ]);
    }

    /**
     * Obtiene el siguiente estado natural en el flujo
     */
    public static function getNextStatus(string $currentStatus): ?string
    {
        $flow = [
            self::PENDING => self::PROCESSING,
            self::PROCESSING => self::READY_FOR_PICKUP,
            self::READY_FOR_PICKUP => self::PICKED_UP,
            self::READY_TO_SHIP => self::SHIPPED,
            self::PICKED_UP => self::IN_TRANSIT,
            self::SHIPPED => self::IN_TRANSIT,
            self::IN_TRANSIT => self::OUT_FOR_DELIVERY,
            self::OUT_FOR_DELIVERY => self::DELIVERED,
        ];

        return $flow[$currentStatus] ?? null;
    }
}
