<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RetryFailedSriInvoiceJob;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Services\SriApiService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    private InvoiceRepository $invoiceRepository;

    private SriApiService $sriApiService;

    public function __construct(
        InvoiceRepository $invoiceRepository,
        SriApiService $sriApiService
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->sriApiService = $sriApiService;

        // ✅ Solo administradores pueden acceder
        $this->middleware('auth:api');
        $this->middleware('role:admin');
    }

    /**
     * ✅ Lista todas las facturas con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'status',
                'date_from',
                'date_to',
                'customer_identification',
                'customer_name',
                'invoice_number',
                'amount_from',
                'amount_to',
            ]);

            $perPage = $request->get('per_page', 15);
            $invoices = $this->invoiceRepository->getAllWithFilters($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'message' => 'Facturas obtenidas exitosamente',
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo facturas', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo facturas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Obtiene una factura específica por ID
     */
    public function show(Invoice $invoice): JsonResponse
    {
        try {
            // ✅ Cargar relaciones
            $invoice->load(['items.product', 'user', 'order']);

            return response()->json([
                'success' => true,
                'data' => $invoice,
                'message' => 'Factura obtenida exitosamente',
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo factura', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo factura: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Reintenta el envío de una factura fallida al SRI
     */
    public function retry(Invoice $invoice): JsonResponse
    {
        try {
            // ✅ Verificar que la factura puede reintentarse
            if (! $invoice->canRetry()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La factura no puede reintentarse (máximo de reintentos alcanzado o estado incorrecto)',
                ], 422);
            }

            // ✅ Realizar reintento
            $response = $this->sriApiService->retryInvoice($invoice);

            Log::info('Reintento manual de factura exitoso', [
                'invoice_id' => $invoice->id,
                'admin_user' => auth()->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => $invoice->fresh(),
                    'sri_response' => $response,
                ],
                'message' => 'Factura reenviada exitosamente al SRI',
            ]);

        } catch (Exception $e) {
            Log::error('Error en reintento manual de factura', [
                'invoice_id' => $invoice->id,
                'admin_user' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error reintentando factura: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Programa reintentos masivos de todas las facturas fallidas
     */
    public function retryAllFailed(): JsonResponse
    {
        try {
            $processedCount = RetryFailedSriInvoiceJob::retryAllFailedInvoices();

            Log::info('Reintentos masivos programados por admin', [
                'admin_user' => auth()->user()->id,
                'jobs_dispatched' => $processedCount,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'jobs_dispatched' => $processedCount,
                ],
                'message' => "Se programaron {$processedCount} jobs de reintento",
            ]);

        } catch (Exception $e) {
            Log::error('Error programando reintentos masivos', [
                'admin_user' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error programando reintentos: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Consulta el estado actual de una factura en el SRI
     */
    public function checkSriStatus(Invoice $invoice): JsonResponse
    {
        try {
            // ✅ Verificar que la factura tenga clave de acceso
            if (empty($invoice->sri_access_key)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La factura no tiene clave de acceso del SRI',
                ], 422);
            }

            $sriResponse = $this->sriApiService->checkInvoiceStatus($invoice->sri_access_key);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice' => $invoice,
                    'sri_status' => $sriResponse,
                ],
                'message' => 'Estado consultado exitosamente',
            ]);

        } catch (Exception $e) {
            Log::error('Error consultando estado en SRI', [
                'invoice_id' => $invoice->id,
                'sri_access_key' => $invoice->sri_access_key,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error consultando estado: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Obtiene estadísticas generales de facturas
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->invoiceRepository->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Estadísticas obtenidas exitosamente',
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo estadísticas', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo estadísticas: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Genera reporte mensual de facturación
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'year' => 'required|integer|between:2020,2030',
                'month' => 'required|integer|between:1,12',
            ]);

            $year = $request->get('year');
            $month = $request->get('month');

            $report = $this->invoiceRepository->getMonthlyReport($year, $month);

            return response()->json([
                'success' => true,
                'data' => $report,
                'message' => 'Reporte mensual generado exitosamente',
            ]);

        } catch (Exception $e) {
            Log::error('Error generando reporte mensual', [
                'year' => $request->get('year'),
                'month' => $request->get('month'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error generando reporte: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Prueba la conectividad con el API del SRI
     */
    public function testSriConnection(): JsonResponse
    {
        try {
            $result = $this->sriApiService->testConnection();

            Log::info('Prueba de conexión SRI ejecutada por admin', [
                'admin_user' => auth()->user()->id,
                'result' => $result,
            ]);

            return response()->json([
                'success' => $result['success'],
                'data' => $result,
                'message' => $result['message'],
            ], $result['success'] ? 200 : 500);

        } catch (Exception $e) {
            Log::error('Error en prueba de conexión SRI', [
                'admin_user' => auth()->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error probando conexión: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Obtiene facturas que necesitan reintento automático
     */
    public function getRetryableInvoices(): JsonResponse
    {
        try {
            $invoices = $this->invoiceRepository->getRetryableInvoices();

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'message' => 'Facturas reintentables obtenidas exitosamente',
            ]);

        } catch (Exception $e) {
            Log::error('Error obteniendo facturas reintentables', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error obteniendo facturas: '.$e->getMessage(),
            ], 500);
        }
    }
}
