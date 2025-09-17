<?php

namespace App\Http\Controllers;

use App\UseCases\Accounting\GenerateAccountingReportUseCase;
use App\Models\AccountingTransaction;
use App\Models\AccountingAccount;
use App\Models\AccountingEntry;
use App\Models\Order;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountingController extends Controller
{
    private $generateReportUseCase;

    public function __construct(GenerateAccountingReportUseCase $generateReportUseCase)
    {
        $this->generateReportUseCase = $generateReportUseCase;
        $this->middleware('admin');
    }

    /**
     * Muestra el balance general
     */
    public function balanceSheet(Request $request)
    {
        $asOf = $request->input('as_of') ? new DateTime($request->input('as_of')) : null;

        $report = $this->generateReportUseCase->executeBalanceSheet($asOf);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Muestra el estado de resultados
     */
    public function incomeStatement(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new DateTime($request->input('start_date'));
        $endDate = new DateTime($request->input('end_date'));

        $report = $this->generateReportUseCase->executeIncomeStatement($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Muestra el libro mayor de una cuenta
     */
    public function accountLedger(Request $request, $accountId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = new DateTime($request->input('start_date'));
        $endDate = new DateTime($request->input('end_date'));

        $ledger = $this->generateReportUseCase->executeAccountLedger($accountId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $ledger,
        ]);
    }

    /**
     * Lista las cuentas contables
     */
    public function accounts(Request $request)
    {
        $activeOnly = $request->input('active_only', true);

        $accounts = app()->make('App\Domain\Repositories\AccountingRepositoryInterface')
            ->listAccounts($activeOnly);

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * ✅ NUEVO: Lista las transacciones contables con paginación y filtros
     */
    public function transactions(Request $request)
    {
        try {
            $query = AccountingTransaction::with(['entries.account', 'user', 'order']);

            // Filtros opcionales
            if ($request->has('start_date') && $request->has('end_date')) {
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');
                $query->whereBetween('transaction_date', [$startDate, $endDate]);
            }

            if ($request->has('type') && $request->input('type') !== 'all') {
                $query->where('type', $request->input('type'));
            }

            if ($request->has('is_posted')) {
                $query->where('is_posted', $request->boolean('is_posted'));
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('reference_number', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Ordenar por fecha más reciente
            $query->orderBy('transaction_date', 'desc')
                  ->orderBy('created_at', 'desc');

            // Paginación
            $perPage = $request->input('per_page', 15);
            $transactions = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo transacciones contables', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las transacciones contables'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Obtiene una transacción específica con sus detalles
     */
    public function transaction($id)
    {
        try {
            $transaction = AccountingTransaction::with([
                'entries.account',
                'user',
                'order.items.product'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transacción no encontrada'
            ], 404);
        }
    }

    /**
     * ✅ NUEVO: Crea una nueva transacción contable manual
     */
    public function createTransaction(Request $request)
    {
        $request->validate([
            'reference_number' => 'required|string|unique:accounting_transactions',
            'transaction_date' => 'required|date',
            'description' => 'required|string',
            'type' => 'required|string',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|exists:accounting_accounts,id',
            'entries.*.debit_amount' => 'required|numeric|min:0',
            'entries.*.credit_amount' => 'required|numeric|min:0',
            'entries.*.notes' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            // Validar que la transacción esté balanceada
            $totalDebits = collect($request->entries)->sum('debit_amount');
            $totalCredits = collect($request->entries)->sum('credit_amount');

            if (abs($totalDebits - $totalCredits) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => "La transacción debe estar balanceada. Débitos: {$totalDebits}, Créditos: {$totalCredits}"
                ], 400);
            }

            // Crear la transacción
            $transaction = AccountingTransaction::create([
                'reference_number' => $request->reference_number,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'type' => $request->type,
                'user_id' => auth()->id(),
                'is_posted' => false // Las transacciones manuales inician como pendientes
            ]);

            // Crear las entradas
            foreach ($request->entries as $entryData) {
                AccountingEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $entryData['account_id'],
                    'debit_amount' => $entryData['debit_amount'],
                    'credit_amount' => $entryData['credit_amount'],
                    'notes' => $entryData['notes'] ?? null
                ]);
            }

            DB::commit();

            // Recargar con relaciones
            $transaction->load(['entries.account']);

            return response()->json([
                'success' => true,
                'message' => 'Transacción creada exitosamente',
                'data' => $transaction
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando transacción contable', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la transacción'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Contabiliza (marca como posted) una transacción
     */
    public function postTransaction($id)
    {
        try {
            $transaction = AccountingTransaction::findOrFail($id);

            if ($transaction->is_posted) {
                return response()->json([
                    'success' => false,
                    'message' => 'La transacción ya está contabilizada'
                ], 400);
            }

            $transaction->update(['is_posted' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Transacción contabilizada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al contabilizar la transacción'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Obtiene métricas financieras para el dashboard
     */
    public function metrics(Request $request)
    {
        try {
            // Parámetros de fecha (último mes por defecto)
            $startDate = $request->input('start_date', now()->subMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));

            // Ventas totales (ingresos)
            $salesQuery = AccountingTransaction::where('accounting_transactions.type', 'SALE')
                ->where('is_posted', true)
                ->whereBetween('transaction_date', [$startDate, $endDate]);

            $totalSales = $salesQuery->join('accounting_entries', 'accounting_transactions.id', '=', 'accounting_entries.transaction_id')
                ->join('accounting_accounts', 'accounting_entries.account_id', '=', 'accounting_accounts.id')
                ->where('accounting_accounts.type', 'REVENUE')
                ->sum('accounting_entries.credit_amount');

            // Gastos totales
            $expensesQuery = AccountingTransaction::where('accounting_transactions.type', 'EXPENSE')
                ->where('is_posted', true)
                ->whereBetween('transaction_date', [$startDate, $endDate]);

            $totalExpenses = $expensesQuery->join('accounting_entries', 'accounting_transactions.id', '=', 'accounting_entries.transaction_id')
                ->join('accounting_accounts', 'accounting_entries.account_id', '=', 'accounting_accounts.id')
                ->where('accounting_accounts.type', 'EXPENSE')
                ->sum('accounting_entries.debit_amount');

            // IVA por pagar
            $vatPayable = AccountingEntry::join('accounting_accounts', 'accounting_entries.account_id', '=', 'accounting_accounts.id')
                ->join('accounting_transactions', 'accounting_entries.transaction_id', '=', 'accounting_transactions.id')
                ->where('accounting_accounts.name', 'LIKE', '%IVA%')
                ->where('accounting_accounts.type', 'LIABILITY')
                ->where('accounting_transactions.is_posted', true)
                ->whereBetween('accounting_transactions.transaction_date', [$startDate, $endDate])
                ->sum('accounting_entries.credit_amount');

            // Efectivo disponible
            $cashBalance = AccountingEntry::join('accounting_accounts', 'accounting_entries.account_id', '=', 'accounting_accounts.id')
                ->join('accounting_transactions', 'accounting_entries.transaction_id', '=', 'accounting_transactions.id')
                ->where('accounting_accounts.name', 'LIKE', '%Efectivo%')
                ->where('accounting_transactions.is_posted', true)
                ->sum(DB::raw('accounting_entries.debit_amount - accounting_entries.credit_amount'));

            // Transacciones pendientes
            $pendingTransactions = AccountingTransaction::where('is_posted', false)->count();

            // Número de órdenes en el período
            $ordersCount = Order::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'sales' => [
                        'total' => round($totalSales, 2),
                        'orders_count' => $ordersCount,
                        'average_order' => $ordersCount > 0 ? round($totalSales / $ordersCount, 2) : 0
                    ],
                    'expenses' => [
                        'total' => round($totalExpenses, 2)
                    ],
                    'profit' => [
                        'gross' => round($totalSales - $totalExpenses, 2),
                        'margin_percentage' => $totalSales > 0 ? round(($totalSales - $totalExpenses) / $totalSales * 100, 2) : 0
                    ],
                    'vat' => [
                        'payable' => round($vatPayable, 2)
                    ],
                    'cash' => [
                        'balance' => round($cashBalance, 2)
                    ],
                    'pending' => [
                        'transactions_count' => $pendingTransactions
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo métricas contables', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las métricas'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Crea una nueva cuenta contable
     */
    public function createAccount(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:accounting_accounts',
            'name' => 'required|string',
            'type' => 'required|in:Activo,Pasivo,Patrimonio,Ingreso,Gasto,Costo',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        try {
            $account = AccountingAccount::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Cuenta creada exitosamente',
                'data' => $account
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creando cuenta contable', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la cuenta'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Actualiza una cuenta contable
     */
    public function updateAccount(Request $request, $id)
    {
        $request->validate([
            'code' => 'required|string|unique:accounting_accounts,code,' . $id,
            'name' => 'required|string',
            'type' => 'required|in:Activo,Pasivo,Patrimonio,Ingreso,Gasto,Costo',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        try {
            $account = AccountingAccount::findOrFail($id);
            $account->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Cuenta actualizada exitosamente',
                'data' => $account
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la cuenta'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Actualiza una transacción contable existente
     */
    public function updateTransaction(Request $request, $id)
    {
        $request->validate([
            'reference_number' => 'required|string|unique:accounting_transactions,reference_number,' . $id,
            'transaction_date' => 'required|date',
            'description' => 'required|string',
            'type' => 'required|string',
            'entries' => 'required|array|min:2',
            'entries.*.account_id' => 'required|exists:accounting_accounts,id',
            'entries.*.debit_amount' => 'required|numeric|min:0',
            'entries.*.credit_amount' => 'required|numeric|min:0',
            'entries.*.notes' => 'nullable|string'
        ]);

        try {
            $transaction = AccountingTransaction::findOrFail($id);

            // Verificar que la transacción no esté contabilizada
            if ($transaction->is_posted) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una transacción que ya está contabilizada'
                ], 400);
            }

            DB::beginTransaction();

            // Validar que la transacción esté balanceada
            $totalDebits = collect($request->entries)->sum('debit_amount');
            $totalCredits = collect($request->entries)->sum('credit_amount');

            if (abs($totalDebits - $totalCredits) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => "La transacción debe estar balanceada. Débitos: {$totalDebits}, Créditos: {$totalCredits}"
                ], 400);
            }

            // Actualizar la transacción
            $transaction->update([
                'reference_number' => $request->reference_number,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'type' => $request->type,
            ]);

            // Eliminar entradas existentes y crear las nuevas
            $transaction->entries()->delete();

            foreach ($request->entries as $entryData) {
                AccountingEntry::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $entryData['account_id'],
                    'debit_amount' => $entryData['debit_amount'],
                    'credit_amount' => $entryData['credit_amount'],
                    'notes' => $entryData['notes'] ?? null
                ]);
            }

            DB::commit();

            // Recargar con relaciones
            $transaction->load(['entries.account', 'user']);

            return response()->json([
                'success' => true,
                'message' => 'Transacción actualizada exitosamente',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando transacción contable', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'transaction_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la transacción'
            ], 500);
        }
    }

    /**
     * ✅ NUEVO: Elimina una transacción contable (para ajustes/devoluciones)
     */
    public function deleteTransaction($id)
    {
        try {
            $transaction = AccountingTransaction::findOrFail($id);

            // Verificar que la transacción no esté contabilizada
            if ($transaction->is_posted) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una transacción que ya está contabilizada'
                ], 400);
            }

            // Eliminar las entradas asociadas
            $transaction->entries()->delete();

            // Eliminar la transacción
            $transaction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transacción eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error eliminando transacción contable', [
                'error' => $e->getMessage(),
                'transaction_id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la transacción'
            ], 500);
        }
    }
}
