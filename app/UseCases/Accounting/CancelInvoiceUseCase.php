<?php

namespace App\UseCases\Accounting;

use App\Domain\Entities\AccountingEntryEntity;
use App\Domain\Entities\AccountingTransactionEntity;
use App\Domain\Interfaces\SriServiceInterface;
use App\Domain\Repositories\AccountingRepositoryInterface;
use App\Domain\Repositories\InvoiceRepositoryInterface;
use DateTime;

class CancelInvoiceUseCase
{
    private $invoiceRepository;

    private $accountingRepository;

    private $sriService;

    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        AccountingRepositoryInterface $accountingRepository,
        SriServiceInterface $sriService
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->accountingRepository = $accountingRepository;
        $this->sriService = $sriService;
    }

    public function execute(int $invoiceId, string $reason): bool
    {
        // Obtener la factura
        $invoice = $this->invoiceRepository->getInvoiceById($invoiceId);
        if (! $invoice) {
            throw new \Exception("Invoice not found with ID: {$invoiceId}");
        }

        // Verificar que la factura no esté ya cancelada
        if ($invoice->status === 'CANCELLED') {
            throw new \Exception('Invoice is already cancelled');
        }

        // Anular la factura en el SRI
        $sriResponse = $this->sriService->cancelInvoice($invoice, $reason);

        // Si la anulación fue exitosa, crear una transacción contable de reverso
        if ($sriResponse['success'] ?? false) {
            // Obtener la transacción original
            $originalTransaction = $this->accountingRepository->getTransactionById($invoice->transactionId);

            if ($originalTransaction) {
                // Crear una transacción de reverso
                $reverseTransaction = $this->createReverseTransaction($originalTransaction, $reason, $invoice->id);
                $this->accountingRepository->createTransaction($reverseTransaction);
            }

            // Actualizar el estado de la factura
            return $this->invoiceRepository->cancelInvoice($invoiceId, $reason);
        }

        return false;
    }

    private function createReverseTransaction(
        AccountingTransactionEntity $originalTransaction,
        string $reason,
        int $invoiceId
    ): AccountingTransactionEntity {
        // Crear la transacción de reverso
        $reverseTransaction = new AccountingTransactionEntity(
            referenceNumber: 'REV-'.$originalTransaction->referenceNumber,
            transactionDate: new DateTime,
            description: "Anulación de factura #{$invoiceId}: {$reason}",
            type: 'ADJUSTMENT',
            userId: $originalTransaction->userId,
            orderId: $originalTransaction->orderId,
            isPosted: true
        );

        // Invertir los asientos originales
        foreach ($originalTransaction->entries as $originalEntry) {
            $reverseTransaction->addEntry(new AccountingEntryEntity(
                accountId: $originalEntry->accountId,
                debitAmount: $originalEntry->creditAmount,
                creditAmount: $originalEntry->debitAmount,
                notes: "Reversión del asiento ID:{$originalEntry->id}"
            ));
        }

        return $reverseTransaction;
    }
}
