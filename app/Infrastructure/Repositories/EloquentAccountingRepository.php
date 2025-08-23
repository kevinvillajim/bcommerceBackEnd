<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Entities\AccountingAccountEntity;
use App\Domain\Entities\AccountingEntryEntity;
use App\Domain\Entities\AccountingTransactionEntity;
use App\Domain\Repositories\AccountingRepositoryInterface;
use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\AccountingTransaction;
use DateTime;
use Illuminate\Support\Facades\DB;

class EloquentAccountingRepository implements AccountingRepositoryInterface
{
    public function createAccount(AccountingAccountEntity $account): AccountingAccountEntity
    {
        $model = new AccountingAccount;
        $model->code = $account->code;
        $model->name = $account->name;
        $model->type = $account->type;
        $model->description = $account->description;
        $model->is_active = $account->isActive;
        $model->save();

        $account->id = $model->id;

        return $account;
    }

    public function getAccountById(int $id): ?AccountingAccountEntity
    {
        $model = AccountingAccount::find($id);
        if (! $model) {
            return null;
        }

        return $this->mapAccountModelToEntity($model);
    }

    public function getAccountByCode(string $code): ?AccountingAccountEntity
    {
        $model = AccountingAccount::where('code', $code)->first();
        if (! $model) {
            return null;
        }

        return $this->mapAccountModelToEntity($model);
    }

    public function listAccounts(bool $activeOnly = true): array
    {
        $query = AccountingAccount::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $accounts = $query->orderBy('code')->get();

        return $accounts->map(function ($model) {
            return $this->mapAccountModelToEntity($model);
        })->toArray();
    }

    public function createTransaction(AccountingTransactionEntity $transaction): AccountingTransactionEntity
    {
        DB::beginTransaction();

        try {
            $model = new AccountingTransaction;
            $model->reference_number = $transaction->referenceNumber;
            $model->transaction_date = $transaction->transactionDate;
            $model->description = $transaction->description;
            $model->type = $transaction->type;
            $model->user_id = $transaction->userId;
            $model->order_id = $transaction->orderId;
            $model->is_posted = $transaction->isPosted;
            $model->save();

            $transaction->id = $model->id;

            // Guardar los asientos contables
            foreach ($transaction->entries as $entry) {
                $entry->transactionId = $transaction->id;
                $this->createEntry($entry);
            }

            DB::commit();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getTransactionById(int $id): ?AccountingTransactionEntity
    {
        $model = AccountingTransaction::find($id);
        if (! $model) {
            return null;
        }

        $transaction = $this->mapTransactionModelToEntity($model);
        $transaction->entries = $this->getEntriesByTransactionId($id);

        return $transaction;
    }

    public function getTransactionByReference(string $reference): ?AccountingTransactionEntity
    {
        $model = AccountingTransaction::where('reference_number', $reference)->first();
        if (! $model) {
            return null;
        }

        $transaction = $this->mapTransactionModelToEntity($model);
        $transaction->entries = $this->getEntriesByTransactionId($transaction->id);

        return $transaction;
    }

    public function createEntry(AccountingEntryEntity $entry): AccountingEntryEntity
    {
        $model = new AccountingEntry;
        $model->transaction_id = $entry->transactionId;
        $model->account_id = $entry->accountId;
        $model->debit_amount = $entry->debitAmount;
        $model->credit_amount = $entry->creditAmount;
        $model->notes = $entry->notes;
        $model->save();

        $entry->id = $model->id;

        return $entry;
    }

    public function getEntriesByTransactionId(int $transactionId): array
    {
        $models = AccountingEntry::where('transaction_id', $transactionId)->get();

        return $models->map(function ($model) {
            return $this->mapEntryModelToEntity($model);
        })->toArray();
    }

    public function getAccountBalance(int $accountId, ?DateTime $asOf = null): float
    {
        $query = AccountingEntry::where('account_id', $accountId)
            ->join('accounting_transactions', 'accounting_entries.transaction_id', '=', 'accounting_transactions.id')
            ->where('accounting_transactions.is_posted', true);

        if ($asOf) {
            $query->where('accounting_transactions.transaction_date', '<=', $asOf);
        }

        $result = $query->selectRaw('SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit')
            ->first();

        return ($result->total_debit ?? 0) - ($result->total_credit ?? 0);
    }

    public function getAccountLedger(int $accountId, DateTime $startDate, DateTime $endDate): array
    {
        $entries = DB::table('accounting_entries')
            ->join('accounting_transactions', 'accounting_entries.transaction_id', '=', 'accounting_transactions.id')
            ->join('accounting_accounts', 'accounting_entries.account_id', '=', 'accounting_accounts.id')
            ->where('accounting_entries.account_id', $accountId)
            ->where('accounting_transactions.is_posted', true)
            ->whereBetween('accounting_transactions.transaction_date', [$startDate, $endDate])
            ->select(
                'accounting_transactions.id',
                'accounting_transactions.reference_number',
                'accounting_transactions.transaction_date',
                'accounting_transactions.description',
                'accounting_entries.debit_amount',
                'accounting_entries.credit_amount',
                'accounting_entries.notes'
            )
            ->orderBy('accounting_transactions.transaction_date')
            ->get();

        return $entries->map(function ($entry) {
            return [
                'transaction_id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'date' => $entry->transaction_date,
                'description' => $entry->description,
                'debit' => $entry->debit_amount,
                'credit' => $entry->credit_amount,
                'balance' => $entry->debit_amount - $entry->credit_amount,
                'notes' => $entry->notes,
            ];
        })->toArray();
    }

    public function getBalanceSheet(?DateTime $asOf = null): array
    {
        // Implementar lógica para generar balance general
        $asOfDate = $asOf ?? new DateTime;

        $accounts = AccountingAccount::whereIn('type', ['ASSET', 'LIABILITY', 'EQUITY'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $result = [
            'as_of' => $asOfDate->format('Y-m-d'),
            'assets' => [],
            'liabilities' => [],
            'equity' => [],
            'total_assets' => 0,
            'total_liabilities' => 0,
            'total_equity' => 0,
        ];

        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->id, $asOfDate);

            $accountData = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $balance,
            ];

            if ($account->type === 'ASSET') {
                $result['assets'][] = $accountData;
                $result['total_assets'] += $balance;
            } elseif ($account->type === 'LIABILITY') {
                $result['liabilities'][] = $accountData;
                $result['total_liabilities'] += $balance;
            } elseif ($account->type === 'EQUITY') {
                $result['equity'][] = $accountData;
                $result['total_equity'] += $balance;
            }
        }

        return $result;
    }

    public function getIncomeStatement(DateTime $startDate, DateTime $endDate): array
    {
        // Implementar lógica para generar estado de resultados
        $accounts = AccountingAccount::whereIn('type', ['REVENUE', 'EXPENSE'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $result = [
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'revenue' => [],
            'expenses' => [],
            'total_revenue' => 0,
            'total_expenses' => 0,
            'net_income' => 0,
        ];

        foreach ($accounts as $account) {
            // Para un estado de resultados, necesitamos el movimiento en el período, no el saldo
            $movements = $this->getAccountMovement($account->id, $startDate, $endDate);

            $accountData = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'balance' => $movements,
            ];

            if ($account->type === 'REVENUE') {
                $result['revenue'][] = $accountData;
                $result['total_revenue'] += $movements;
            } elseif ($account->type === 'EXPENSE') {
                $result['expenses'][] = $accountData;
                $result['total_expenses'] += $movements;
            }
        }

        $result['net_income'] = $result['total_revenue'] - $result['total_expenses'];

        return $result;
    }

    private function getAccountMovement(int $accountId, DateTime $startDate, DateTime $endDate): float
    {
        $result = AccountingEntry::join('accounting_transactions', 'accounting_entries.transaction_id', '=', 'accounting_transactions.id')
            ->where('accounting_entries.account_id', $accountId)
            ->where('accounting_transactions.is_posted', true)
            ->whereBetween('accounting_transactions.transaction_date', [$startDate, $endDate])
            ->selectRaw('SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit')
            ->first();

        return ($result->total_debit ?? 0) - ($result->total_credit ?? 0);
    }

    private function mapAccountModelToEntity(AccountingAccount $model): AccountingAccountEntity
    {
        return new AccountingAccountEntity(
            id: $model->id,
            code: $model->code,
            name: $model->name,
            type: $model->type,
            description: $model->description,
            isActive: $model->is_active
        );
    }

    private function mapTransactionModelToEntity(AccountingTransaction $model): AccountingTransactionEntity
    {
        return new AccountingTransactionEntity(
            id: $model->id,
            referenceNumber: $model->reference_number,
            transactionDate: new DateTime($model->transaction_date),
            description: $model->description,
            type: $model->type,
            userId: $model->user_id,
            orderId: $model->order_id,
            isPosted: $model->is_posted
        );
    }

    private function mapEntryModelToEntity(AccountingEntry $model): AccountingEntryEntity
    {
        return new AccountingEntryEntity(
            id: $model->id,
            transactionId: $model->transaction_id,
            accountId: $model->account_id,
            debitAmount: $model->debit_amount,
            creditAmount: $model->credit_amount,
            notes: $model->notes
        );
    }
}
