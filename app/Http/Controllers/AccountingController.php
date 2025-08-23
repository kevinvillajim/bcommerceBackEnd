<?php

namespace App\Http\Controllers;

use App\UseCases\Accounting\GenerateAccountingReportUseCase;
use DateTime;
use Illuminate\Http\Request;

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
}
