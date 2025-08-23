<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\AccountingAccountEntity;
use App\Domain\Entities\AccountingEntryEntity;
use App\Domain\Entities\AccountingTransactionEntity;
use DateTime;

interface AccountingRepositoryInterface
{
    /**
     * Crea una cuenta contable
     */
    public function createAccount(AccountingAccountEntity $account): AccountingAccountEntity;

    /**
     * Obtiene una cuenta por su ID
     */
    public function getAccountById(int $id): ?AccountingAccountEntity;

    /**
     * Obtiene una cuenta por su código
     */
    public function getAccountByCode(string $code): ?AccountingAccountEntity;

    /**
     * Lista todas las cuentas activas
     */
    public function listAccounts(bool $activeOnly = true): array;

    /**
     * Crea una transacción contable
     */
    public function createTransaction(AccountingTransactionEntity $transaction): AccountingTransactionEntity;

    /**
     * Obtiene una transacción por su ID
     */
    public function getTransactionById(int $id): ?AccountingTransactionEntity;

    /**
     * Obtiene una transacción por su número de referencia
     */
    public function getTransactionByReference(string $reference): ?AccountingTransactionEntity;

    /**
     * Registra un asiento contable
     */
    public function createEntry(AccountingEntryEntity $entry): AccountingEntryEntity;

    /**
     * Obtiene los asientos de una transacción
     */
    public function getEntriesByTransactionId(int $transactionId): array;

    /**
     * Obtiene el balance actual de una cuenta
     */
    public function getAccountBalance(int $accountId, ?DateTime $asOf = null): float;

    /**
     * Obtiene el libro mayor de una cuenta
     */
    public function getAccountLedger(int $accountId, DateTime $startDate, DateTime $endDate): array;

    /**
     * Genera un reporte de balance general
     */
    public function getBalanceSheet(?DateTime $asOf = null): array;

    /**
     * Genera un reporte de resultados (pérdidas y ganancias)
     */
    public function getIncomeStatement(DateTime $startDate, DateTime $endDate): array;
}
