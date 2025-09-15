<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\SriApiService;
use App\UseCases\Accounting\GenerateInvoicePdfUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminInvoiceController extends Controller
{
    private SriApiService $sriApiService;
    private GenerateInvoicePdfUseCase $generateInvoicePdfUseCase;

    public function __construct(SriApiService $sriApiService, GenerateInvoicePdfUseCase $generateInvoicePdfUseCase)
    {
        $this->middleware('jwt.auth');
        $this->middleware('admin'); // Solo admins pueden acceder
        $this->sriApiService = $sriApiService;
        $this->generateInvoicePdfUseCase = $generateInvoicePdfUseCase;
    }

    /**
     * Lista todas las facturas con paginación y filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Invoice::with(['order.user', 'items.product'])
                ->orderBy('created_at', 'desc');

            // Filtros opcionales
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('customer_identification')) {
                $query->where('customer_identification', 'like', '%'.$request->customer_identification.'%');
            }

            if ($request->filled('customer_name')) {
                $query->where('customer_name', 'like', '%'.$request->customer_name.'%');
            }

            if ($request->filled('start_date')) {
                $query->whereDate('issue_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('issue_date', '<=', $request->end_date);
            }

            if ($request->filled('invoice_number')) {
                $query->where('invoice_number', 'like', '%'.$request->invoice_number.'%');
            }

            // Paginación
            $perPage = $request->input('per_page', 20);
            $invoices = $query->paginate($perPage);

            // Transformar datos para el frontend
            $data = $invoices->through(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'issue_date' => $invoice->issue_date->format('Y-m-d H:i:s'),
                    'status' => $invoice->status,
                    'status_label' => $this->getStatusLabel($invoice->status),
                    'status_color' => $this->getStatusColor($invoice->status),

                    // Datos del cliente
                    'customer' => [
                        'identification' => $invoice->customer_identification,
                        'identification_type' => $invoice->customer_identification_type,
                        'name' => $invoice->customer_name,
                        'email' => $invoice->customer_email,
                        'address' => $invoice->customer_address,
                        'phone' => $invoice->customer_phone,
                    ],

                    // Totales
                    'subtotal' => $invoice->subtotal,
                    'tax_amount' => $invoice->tax_amount,
                    'total_amount' => $invoice->total_amount,
                    'currency' => $invoice->currency,

                    // SRI
                    'sri_access_key' => $invoice->sri_access_key,
                    'sri_authorization_number' => $invoice->sri_authorization_number,
                    'sri_error_message' => $invoice->sri_error_message,

                    // Reintentos
                    'retry_count' => $invoice->retry_count,
                    'last_retry_at' => $invoice->last_retry_at?->format('Y-m-d H:i:s'),

                    // Orden relacionada (si existe)
                    'order' => $invoice->order ? [
                        'id' => $invoice->order->id,
                        'order_number' => $invoice->order->order_number,
                        'user' => [
                            'name' => $invoice->order->user->name ?? 'Usuario eliminado',
                            'email' => $invoice->order->user->email ?? '',
                        ],
                    ] : null,

                    // Items count
                    'items_count' => $invoice->items->count(),
                    'created_at' => $invoice->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page' => $invoices->lastPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'from' => $invoices->firstItem(),
                    'to' => $invoices->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error listando facturas admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las facturas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene los detalles completos de una factura
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $invoice = Invoice::with(['order.user', 'items.product'])->find($id);

            if (! $invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            $data = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $invoice->issue_date->format('Y-m-d H:i:s'),
                'status' => $invoice->status,
                'status_label' => $this->getStatusLabel($invoice->status),
                'status_color' => $this->getStatusColor($invoice->status),

                // Datos completos del cliente
                'customer' => [
                    'identification' => $invoice->customer_identification,
                    'identification_type' => $invoice->customer_identification_type,
                    'identification_type_label' => $invoice->customer_identification_type === '05' ? 'Cédula' : 'RUC',
                    'name' => $invoice->customer_name,
                    'email' => $invoice->customer_email,
                    'address' => $invoice->customer_address,
                    'phone' => $invoice->customer_phone,
                ],

                // Totales
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,

                // SRI completo
                'sri' => [
                    'access_key' => $invoice->sri_access_key,
                    'authorization_number' => $invoice->sri_authorization_number,
                    'error_message' => $invoice->sri_error_message,
                    'response' => $invoice->sri_response ? json_decode($invoice->sri_response, true) : null,
                ],

                // Sistema de reintentos
                'retry_info' => [
                    'count' => $invoice->retry_count,
                    'last_retry_at' => $invoice->last_retry_at?->format('Y-m-d H:i:s'),
                    'can_retry' => $invoice->canRetry(),
                ],

                // Orden relacionada
                'order' => $invoice->order ? [
                    'id' => $invoice->order->id,
                    'order_number' => $invoice->order->order_number,
                    'status' => $invoice->order->status,
                    'payment_status' => $invoice->order->payment_status,
                    'payment_method' => $invoice->order->payment_method,
                    'user' => [
                        'id' => $invoice->order->user->id ?? null,
                        'name' => $invoice->order->user->name ?? 'Usuario eliminado',
                        'email' => $invoice->order->user->email ?? '',
                    ],
                ] : null,

                // Items detallados
                'items' => $invoice->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_code' => $item->product_code,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'discount' => $item->discount,
                        'subtotal' => $item->subtotal,
                        'tax_rate' => $item->tax_rate,
                        'tax_amount' => $item->tax_amount,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'slug' => $item->product->slug,
                        ] : null,
                    ];
                }),

                // Metadatos
                'created_via' => $invoice->created_via,
                'created_at' => $invoice->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $invoice->updated_at->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo detalles de factura', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los detalles de la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reintenta el envío de una factura fallida al SRI
     */
    public function retry(Request $request, $id): JsonResponse
    {
        try {
            $invoice = Invoice::find($id);

            if (! $invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            if (! $invoice->canRetry()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta factura no puede reintentarse (máximo de reintentos alcanzado o estado incorrecto)',
                ], 400);
            }

            // Reintentar usando el servicio SRI
            $result = $this->sriApiService->retryInvoice($invoice);

            return response()->json([
                'success' => true,
                'message' => 'Reintento de factura iniciado correctamente',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'retry_count' => $invoice->retry_count,
                    'sri_response' => $result,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error reintentando factura', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reintentar la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta el estado actual de una factura en el SRI
     */
    public function checkStatus(Request $request, $id): JsonResponse
    {
        try {
            $invoice = Invoice::find($id);

            if (! $invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            if (! $invoice->sri_access_key) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta factura no tiene clave de acceso del SRI',
                ], 400);
            }

            // Consultar estado en el SRI
            $sriStatus = $this->sriApiService->checkInvoiceStatus($invoice->sri_access_key);

            return response()->json([
                'success' => true,
                'data' => [
                    'invoice_id' => $invoice->id,
                    'current_status' => $invoice->status,
                    'sri_status' => $sriStatus,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error consultando estado SRI', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al consultar el estado en el SRI',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza los datos editables de una factura
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $invoice = Invoice::find($id);

            if (! $invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            // Validar que la factura puede ser editada (no debe estar autorizada)
            if (in_array($invoice->status, [Invoice::STATUS_AUTHORIZED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una factura autorizada por el SRI',
                ], 400);
            }

            // Validar datos de entrada
            $validatedData = $request->validate([
                'customer_name' => 'sometimes|required|string|max:255',
                'customer_identification' => 'sometimes|required|string|max:13|min:10',
                'customer_email' => 'sometimes|nullable|email|max:255',
                'customer_address' => 'sometimes|required|string|max:500',
                'customer_phone' => 'sometimes|nullable|string|max:20',
            ]);

            // Si se actualiza la identificación, recalcular el tipo
            if (isset($validatedData['customer_identification'])) {
                $identification = $validatedData['customer_identification'];
                $length = strlen($identification);

                if ($length === 10) {
                    $validatedData['customer_identification_type'] = '05'; // Cédula
                } elseif ($length === 13 && substr($identification, -3) === '001') {
                    $validatedData['customer_identification_type'] = '04'; // RUC
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Identificación inválida. Debe ser cédula (10 dígitos) o RUC (13 dígitos terminado en 001)',
                    ], 400);
                }
            }

            // Guardar datos anteriores para log
            $oldData = [
                'customer_name' => $invoice->customer_name,
                'customer_identification' => $invoice->customer_identification,
                'customer_email' => $invoice->customer_email,
                'customer_address' => $invoice->customer_address,
                'customer_phone' => $invoice->customer_phone,
            ];

            // Actualizar factura
            $invoice->update($validatedData);

            // Log de cambios para auditoría
            Log::info('Factura editada manualmente por admin', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'admin_user' => auth()->user()->name ?? 'Admin',
                'old_data' => $oldData,
                'new_data' => array_intersect_key($invoice->toArray(), $validatedData),
                'changed_fields' => array_keys($validatedData),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Factura actualizada correctamente',
                'data' => [
                    'invoice_id' => $invoice->id,
                    'updated_fields' => array_keys($validatedData),
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error actualizando factura', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de facturas para el dashboard admin
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->sriApiService->getStatistics();

            // Estadísticas adicionales
            $recentInvoices = Invoice::orderBy('created_at', 'desc')->take(5)->get();
            $failedInvoices = Invoice::where('status', Invoice::STATUS_FAILED)->count();
            $pendingRetries = Invoice::where('status', Invoice::STATUS_FAILED)
                ->where('retry_count', '<', 12)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'sri_stats' => $stats,
                    'additional_stats' => [
                        'failed_invoices' => $failedInvoices,
                        'pending_retries' => $pendingRetries,
                        'recent_invoices' => $recentInvoices->map(function ($invoice) {
                            return [
                                'id' => $invoice->id,
                                'invoice_number' => $invoice->invoice_number,
                                'customer_name' => $invoice->customer_name,
                                'total_amount' => $invoice->total_amount,
                                'status' => $invoice->status,
                                'created_at' => $invoice->created_at->format('Y-m-d H:i:s'),
                            ];
                        }),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de facturas', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene las etiquetas de estado para mostrar en el frontend
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            Invoice::STATUS_DRAFT => 'Borrador',
            Invoice::STATUS_SENT_TO_SRI => 'Enviado al SRI',
            Invoice::STATUS_PENDING => 'Pendiente',
            Invoice::STATUS_PROCESSING => 'Procesando',
            Invoice::STATUS_RECEIVED => 'Recibida por SRI',
            Invoice::STATUS_AUTHORIZED => 'Autorizada',
            Invoice::STATUS_REJECTED => 'Rechazada',
            Invoice::STATUS_NOT_AUTHORIZED => 'No Autorizada',
            Invoice::STATUS_RETURNED => 'Devuelta',
            Invoice::STATUS_SRI_ERROR => 'Error SRI',
            Invoice::STATUS_FAILED => 'Fallida',
            Invoice::STATUS_DEFINITIVELY_FAILED => 'Fallida Definitivamente',
            default => ucfirst(strtolower($status))
        };
    }

    /**
     * Descargar PDF de una factura
     */
    public function downloadPdf(Request $request, $id): Response
    {
        try {
            $invoice = Invoice::with(['order.items.product', 'order.user'])->find($id);

            if (!$invoice) {
                return response('Factura no encontrada', 404);
            }

            // Si ya existe un PDF generado, devolverlo
            if ($invoice->pdf_path && Storage::disk('public')->exists($invoice->pdf_path)) {
                $pdfContent = Storage::disk('public')->get($invoice->pdf_path);
                $fileName = "factura_{$invoice->invoice_number}.pdf";

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                    'Content-Length' => strlen($pdfContent),
                ]);
            }

            // Si no existe PDF o la factura no está autorizada, generar uno nuevo
            if ($invoice->status !== Invoice::STATUS_AUTHORIZED) {
                // Para facturas no autorizadas, generar PDF temporal sin datos SRI
                Log::info('Generando PDF temporal para factura no autorizada', [
                    'invoice_id' => $invoice->id,
                    'status' => $invoice->status,
                ]);

                $pdfPath = $this->generateTemporaryPdf($invoice);
            } else {
                // Para facturas autorizadas, usar el UseCase completo
                $pdfPath = $this->generateInvoicePdfUseCase->execute($invoice);
            }

            $pdfContent = Storage::disk('public')->get($pdfPath);
            $fileName = "factura_{$invoice->invoice_number}.pdf";

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Content-Length' => strlen($pdfContent),
            ]);

        } catch (\Exception $e) {
            Log::error('Error descargando PDF de factura', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Error al generar el PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Genera un PDF temporal para facturas no autorizadas
     */
    private function generateTemporaryPdf(Invoice $invoice): string
    {
        $pdfData = [
            'invoice' => $invoice,
            'order' => $invoice->order,
            'customer' => $invoice->order->user,
            'items' => $invoice->order->items,
            'sriResponse' => [],
            'generatedAt' => now(),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf-template', $pdfData);
        $pdf->setPaper('A4', 'portrait');

        $fileName = "temp_invoice_{$invoice->invoice_number}_{$invoice->id}.pdf";
        $filePath = "invoices/temp/{$fileName}";

        $pdfContent = $pdf->output();
        Storage::disk('public')->put($filePath, $pdfContent);

        return $filePath;
    }

    /**
     * Obtiene los colores de estado para el frontend
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            Invoice::STATUS_DRAFT => 'gray',
            Invoice::STATUS_SENT_TO_SRI => 'blue',
            Invoice::STATUS_PENDING => 'yellow',
            Invoice::STATUS_PROCESSING => 'blue',
            Invoice::STATUS_RECEIVED => 'indigo',
            Invoice::STATUS_AUTHORIZED => 'green',
            Invoice::STATUS_REJECTED => 'red',
            Invoice::STATUS_NOT_AUTHORIZED => 'red',
            Invoice::STATUS_RETURNED => 'orange',
            Invoice::STATUS_SRI_ERROR => 'red',
            Invoice::STATUS_FAILED => 'red',
            Invoice::STATUS_DEFINITIVELY_FAILED => 'red',
            default => 'gray'
        };
    }
}
