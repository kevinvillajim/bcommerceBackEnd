<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelInvoiceRequest;
use App\Http\Requests\GenerateInvoiceRequest;
use App\UseCases\Accounting\CancelInvoiceUseCase;
use App\UseCases\Accounting\GenerateInvoiceUseCase;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    private $generateInvoiceUseCase;

    private $cancelInvoiceUseCase;

    public function __construct(
        GenerateInvoiceUseCase $generateInvoiceUseCase,
        CancelInvoiceUseCase $cancelInvoiceUseCase
    ) {
        $this->generateInvoiceUseCase = $generateInvoiceUseCase;
        $this->cancelInvoiceUseCase = $cancelInvoiceUseCase;
    }

    /**
     * Genera una factura para una orden específica
     */
    public function generate(GenerateInvoiceRequest $request)
    {
        try {
            $validated = $request->validated();
            $invoice = $this->generateInvoiceUseCase->execute($validated['order_id']);

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated successfully',
                'data' => $invoice,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancela una factura existente
     */
    public function cancel(CancelInvoiceRequest $request, $id)
    {
        try {
            $result = $this->cancelInvoiceUseCase->execute($id, $request->input('reason'));

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice cancelled successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel invoice, check SRI service response',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene una factura por su ID
     */
    public function show($id)
    {
        $invoice = app()->make('App\Domain\Repositories\InvoiceRepositoryInterface')->getInvoiceById($id);

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $invoice,
        ]);
    }

    /**
     * Lista las facturas según los filtros proporcionados
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'start_date', 'end_date', 'user_id', 'seller_id']);
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $invoices = app()->make('App\Domain\Repositories\InvoiceRepositoryInterface')
            ->listInvoices($filters, $page, $perPage);

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    /**
     * Descarga una factura en formato PDF
     */
    public function download($id)
    {
        $invoice = app()->make('App\Domain\Repositories\InvoiceRepositoryInterface')->getInvoiceById($id);

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        // En un caso real, aquí generarías el PDF de la factura

        return response()->json([
            'success' => true,
            'message' => 'This functionality is not implemented yet',
        ]);
    }
}
