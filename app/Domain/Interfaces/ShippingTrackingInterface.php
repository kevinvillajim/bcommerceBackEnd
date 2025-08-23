<?php

namespace App\Domain\Interfaces;

interface ShippingTrackingInterface
{
    /**
     * Obtener información de seguimiento actualizada para un envío
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @param  string|null  $carrierCode  Código del transportista (opcional)
     * @return array Información de seguimiento
     */
    public function getTrackingInfo(string $trackingNumber, ?string $carrierCode = null): array;

    /**
     * Registrar una actualización en el estado de un envío
     *
     * @param  array  $data  Datos de la actualización de seguimiento
     * @return bool Si la actualización fue exitosa
     */
    public function updateShippingStatus(array $data): bool;

    /**
     * Obtener la ruta completa (todas las ubicaciones) de un envío
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @return array Lista de puntos de la ruta con coordenadas
     */
    public function getShippingRoute(string $trackingNumber): array;

    /**
     * Estimar el tiempo de entrega para un envío
     *
     * @param  string  $originPostalCode  Código postal de origen
     * @param  string  $destinationPostalCode  Código postal de destino
     * @param  string  $carrierCode  Código del transportista
     * @param  float  $weight  Peso en kg
     * @return \DateTime|null Fecha estimada de entrega
     */
    public function estimateDeliveryDate(
        string $originPostalCode,
        string $destinationPostalCode,
        string $carrierCode,
        float $weight
    ): ?\DateTime;

    /**
     * Verificar si un número de seguimiento es válido
     *
     * @param  string  $trackingNumber  Número de seguimiento
     * @param  string|null  $carrierCode  Código del transportista (opcional)
     * @return bool Si el número de seguimiento es válido
     */
    public function isValidTrackingNumber(string $trackingNumber, ?string $carrierCode = null): bool;
}
