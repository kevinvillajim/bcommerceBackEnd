<?php

namespace App\UseCases\Accounting;

use App\Domain\Repositories\AccountingRepositoryInterface;
use DateTime;

class GenerateAccountingReportUseCase
{
    private $accountingRepository;

    public function __construct(AccountingRepositoryInterface $accountingRepository)
    {
        $this->accountingRepository = $accountingRepository;
    }

    public function executeBalanceSheet(?DateTime $asOf = null): array
    {
        return $this->accountingRepository->getBalanceSheet($asOf);
    }

    public function executeIncomeStatement(DateTime $startDate, DateTime $endDate): array
    {
        return $this->accountingRepository->getIncomeStatement($startDate, $endDate);
    }

    public function executeAccountLedger(int $accountId, DateTime $startDate, DateTime $endDate): array
    {
        // Obtener los movimientos de la cuenta
        $ledger = $this->accountingRepository->getAccountLedger($accountId, $startDate, $endDate);

        // Obtener el saldo inicial
        $initialBalance = $this->accountingRepository->getAccountBalance($accountId, $startDate);

        // Agregar el saldo inicial al libro mayor
        array_unshift($ledger, [
            'transaction_id' => null,
            'reference_number' => 'INICIAL',
            'date' => $startDate->format('Y-m-d'),
            'description' => 'Saldo inicial',
            'debit' => 0,
            'credit' => 0,
            'balance' => $initialBalance,
            'notes' => null,
        ]);

        // Calcular los saldos acumulados
        $runningBalance = $initialBalance;
        foreach ($ledger as $key => $entry) {
            if ($key === 0) {
                continue;
            } // Saltar el registro inicial

            $runningBalance += $entry['debit'] - $entry['credit'];
            $ledger[$key]['balance'] = $runningBalance;
        }

        return $ledger;
    }
}
