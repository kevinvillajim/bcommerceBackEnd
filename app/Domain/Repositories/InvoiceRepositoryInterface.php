<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\InvoiceEntity;
use App\Domain\Entities\InvoiceItemEntity;
use App\Domain\Entities\SriTransactionEntity;

interface InvoiceRepositoryInterface
{
    /**
     * Crea una nueva factura
     */
    public function createInvoice(InvoiceEntity $invoice): InvoiceEntity;

    /**
     * Obtiene una factura por su ID
     */
    public function getInvoiceById(int $id): ?InvoiceEntity;

    /**
     * Obtiene una factura por su número
     */
    public function getInvoiceByNumber(string $invoiceNumber): ?InvoiceEntity;

    /**
     * Obtiene una factura por su clave de acceso SRI
     */
    public function getInvoiceByAccessKey(string $accessKey): ?InvoiceEntity;

    /**
     * Obtiene una factura por su ID de orden
     */
    public function getInvoiceByOrderId(int $orderId): ?InvoiceEntity;

    /**
     * Actualiza una factura
     */
    public function updateInvoice(InvoiceEntity $invoice): InvoiceEntity;

    /**
     * Añade un ítem a una factura
     */
    public function addInvoiceItem(InvoiceItemEntity $item): InvoiceItemEntity;

    /**
     * Obtiene los ítems de una factura
     */
    public function getInvoiceItems(int $invoiceId): array;

    /**
     * Cancela una factura
     */
    public function cancelInvoice(int $invoiceId, string $reason): bool;

    /**
     * Registra una transacción con el SRI
     */
    public function recordSriTransaction(SriTransactionEntity $transaction): SriTransactionEntity;

    /**
     * Obtiene las transacciones SRI de una factura
     */
    public function getSriTransactions(int $invoiceId): array;

    /**
     * Lista las facturas según filtros
     */
    public function listInvoices(array $filters = [], int $page = 1, int $perPage = 15): array;
}
