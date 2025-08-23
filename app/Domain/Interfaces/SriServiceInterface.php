<?php

namespace App\Domain\Interfaces;

use App\Domain\Entities\InvoiceEntity;

interface SriServiceInterface
{
    /**
     * Genera y envía una factura electrónica al SRI
     *
     * @return array Respuesta del SRI
     */
    public function generateInvoice(InvoiceEntity $invoice): array;

    /**
     * Anula una factura en el SRI
     *
     * @return array Respuesta del SRI
     */
    public function cancelInvoice(InvoiceEntity $invoice, string $reason): array;

    /**
     * Valida una clave de acceso en el SRI
     *
     * @return array Respuesta del SRI
     */
    public function validateAccessKey(string $accessKey): array;

    /**
     * Consulta el estado de una factura en el SRI
     *
     * @return array Respuesta del SRI
     */
    public function queryInvoiceStatus(string $accessKey): array;
}
