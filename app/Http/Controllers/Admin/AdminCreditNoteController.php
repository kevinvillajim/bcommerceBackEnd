<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Services\SriApiService;
use App\UseCases\Accounting\GenerateCreditNotePdfUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class AdminCreditNoteController extends Controller
{
    private SriApiService $sriApiService;
    private GenerateCreditNotePdfUseCase $generatePdfUseCase;

    public function __construct(SriApiService $sriApiService, GenerateCreditNotePdfUseCase $generatePdfUseCase)
    {
        $this->middleware('jwt.auth');
        $this->middleware('admin'); // Solo admins pueden acceder
        $this->sriApiService = $sriApiService;
        $this->generatePdfUseCase = $generatePdfUseCase;
    }

    /**
     * Lista todas las notas de crédito con paginación y filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CreditNote::with(['invoice', 'order.user', 'items.product'])
                ->orderBy('created_at', 'desc');

            // Filtros opcionales (idénticos a AdminInvoiceController)
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

            if ($request->filled('credit_note_number')) {
                $query->where('credit_note_number', 'like', '%'.$request->credit_note_number.'%');
            }

            // Paginación
            $perPage = $request->input('per_page', 20);
            $creditNotes = $query->paginate($perPage);

            // Transformar datos para el frontend (patrón AdminInvoiceController)
            $data = $creditNotes->through(function ($creditNote) {
                return [
                    'id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'issue_date' => $creditNote->issue_date->format('Y-m-d H:i:s'),
                    'status' => $creditNote->status,
                    'status_label' => $this->getStatusLabel($creditNote->status),
                    'status_color' => $this->getStatusColor($creditNote->status),

                    // Datos del cliente
                    'customer' => [
                        'identification' => $creditNote->customer_identification,
                        'identification_type' => $creditNote->customer_identification_type,
                        'name' => $creditNote->customer_name,
                        'email' => $creditNote->customer_email,
                        'address' => $creditNote->customer_address,
                        'phone' => $creditNote->customer_phone,
                    ],

                    // Totales
                    'subtotal' => $creditNote->subtotal,
                    'tax_amount' => $creditNote->tax_amount,
                    'total_amount' => $creditNote->total_amount,
                    'currency' => $creditNote->currency,

                    // Información específica de nota de crédito
                    'motivo' => $creditNote->motivo,
                    'documento_modificado' => $creditNote->documento_modificado_numero,

                    // SRI
                    'sri_access_key' => $creditNote->sri_access_key,
                    'sri_authorization_number' => $creditNote->sri_authorization_number,
                    'sri_error_message' => $creditNote->sri_error_message,

                    // Reintentos
                    'retry_count' => $creditNote->retry_count,
                    'last_retry_at' => $creditNote->last_retry_at?->format('Y-m-d H:i:s'),

                    // Factura original
                    'invoice' => $creditNote->invoice ? [
                        'id' => $creditNote->invoice->id,
                        'invoice_number' => $creditNote->invoice->invoice_number,
                        'status' => $creditNote->invoice->status,
                    ] : null,

                    // Orden relacionada (si existe)
                    'order' => $creditNote->order ? [
                        'id' => $creditNote->order->id,
                        'order_number' => $creditNote->order->order_number,
                        'user' => [
                            'name' => $creditNote->order->user->name ?? 'Usuario eliminado',
                            'email' => $creditNote->order->user->email ?? '',
                        ],
                    ] : null,

                    // Items count
                    'items_count' => $creditNote->items->count(),
                    'created_at' => $creditNote->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $creditNotes->currentPage(),
                    'last_page' => $creditNotes->lastPage(),
                    'per_page' => $creditNotes->perPage(),
                    'total' => $creditNotes->total(),
                    'from' => $creditNotes->firstItem(),
                    'to' => $creditNotes->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error listando notas de crédito admin', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las notas de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene los detalles completos de una nota de crédito
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $creditNote = CreditNote::with(['invoice', 'order.user', 'items.product'])->find($id);

            if (!$creditNote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota de crédito no encontrada',
                ], 404);
            }

            $data = [
                'id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'issue_date' => $creditNote->issue_date->format('Y-m-d H:i:s'),
                'status' => $creditNote->status,
                'status_label' => $this->getStatusLabel($creditNote->status),
                'status_color' => $this->getStatusColor($creditNote->status),

                // Datos completos del cliente
                'customer' => [
                    'identification' => $creditNote->customer_identification,
                    'identification_type' => $creditNote->customer_identification_type,
                    'identification_type_label' => $creditNote->customer_identification_type === '05' ? 'Cédula' : 'RUC',
                    'name' => $creditNote->customer_name,
                    'email' => $creditNote->customer_email,
                    'address' => $creditNote->customer_address,
                    'phone' => $creditNote->customer_phone,
                ],

                // Totales
                'subtotal' => $creditNote->subtotal,
                'tax_amount' => $creditNote->tax_amount,
                'total_amount' => $creditNote->total_amount,
                'currency' => $creditNote->currency,

                // Información específica de nota de crédito
                'motivo' => $creditNote->motivo,
                'documento_modificado' => [
                    'tipo' => $creditNote->documento_modificado_tipo,
                    'numero' => $creditNote->documento_modificado_numero,
                    'fecha' => $creditNote->documento_modificado_fecha?->format('Y-m-d'),
                ],

                // SRI completo
                'sri' => [
                    'access_key' => $creditNote->sri_access_key,
                    'authorization_number' => $creditNote->sri_authorization_number,
                    'error_message' => $creditNote->sri_error_message,
                    'response' => $creditNote->sri_response ? json_decode($creditNote->sri_response, true) : null,
                ],

                // Sistema de reintentos
                'retry_info' => [
                    'count' => $creditNote->retry_count,
                    'last_retry_at' => $creditNote->last_retry_at?->format('Y-m-d H:i:s'),
                    'can_retry' => $creditNote->canRetry(),
                ],

                // Factura original
                'invoice' => $creditNote->invoice ? [
                    'id' => $creditNote->invoice->id,
                    'invoice_number' => $creditNote->invoice->invoice_number,
                    'status' => $creditNote->invoice->status,
                    'issue_date' => $creditNote->invoice->issue_date->format('Y-m-d H:i:s'),
                    'total_amount' => $creditNote->invoice->total_amount,
                ] : null,

                // Orden relacionada
                'order' => $creditNote->order ? [
                    'id' => $creditNote->order->id,
                    'order_number' => $creditNote->order->order_number,
                    'status' => $creditNote->order->status,
                    'payment_status' => $creditNote->order->payment_status,
                    'payment_method' => $creditNote->order->payment_method,
                    'user' => [
                        'id' => $creditNote->order->user->id ?? null,
                        'name' => $creditNote->order->user->name ?? 'Usuario eliminado',
                        'email' => $creditNote->order->user->email ?? '',
                    ],
                ] : null,

                // Items detallados
                'items' => $creditNote->items->map(function ($item) {
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
                        'codigo_iva' => $item->codigo_iva,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'slug' => $item->product->slug,
                        ] : null,
                    ];
                }),

                // Metadatos
                'created_via' => $creditNote->created_via,
                'created_at' => $creditNote->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $creditNote->updated_at->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo detalles de nota de crédito', [
                'credit_note_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los detalles de la nota de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear nueva nota de crédito desde factura
     */
    public function store(Request $request): JsonResponse
    {
        try {

            // Validar datos de entrada en formato SRI exacto (usando snake_case que Laravel recibe)
            $validatedData = $request->validate([
                'invoice_id' => 'nullable|integer|exists:invoices,id', // ID de factura original (opcional)
                'secuencial' => 'nullable|string', // Puede venir vacío para auto-generar
                'fecha_emision' => 'required|date',
                'motivo' => 'required|string|min:5|max:500',
                'documento_modificado' => 'required|array',
                'documento_modificado.tipo' => 'required|string',
                'documento_modificado.numero' => 'required|string',
                'documento_modificado.fecha_emision' => 'required|date',
                'comprador' => 'required|array',
                'comprador.tipo_identificacion' => 'required|string',
                'comprador.identificacion' => 'required|string',
                'comprador.razon_social' => 'required|string',
                'comprador.direccion' => 'nullable|string',
                'comprador.email' => 'nullable|email',
                'comprador.telefono' => 'nullable|string|max:20',
                'detalles' => 'required|array|min:1',
                'detalles.*.codigo_interno' => 'required|string',
                'detalles.*.descripcion' => 'required|string',
                'detalles.*.cantidad' => 'required|numeric|min:0.01',
                'detalles.*.precio_unitario' => 'required|numeric|min:0.01',
                'detalles.*.descuento' => 'nullable|numeric|min:0',
                'detalles.*.codigo_iva' => 'required|in:0,2,3,4,5,6,7',
                'informacion_adicional' => 'nullable|array',
            ]);

            // Generar secuencial automáticamente si no se proporciona
            if (empty($validatedData['secuencial'])) {
                $validatedData['secuencial'] = $this->generateNextSecuencial();
            }

            // Calcular totales reales basados en detalles
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($validatedData['detalles'] as $detalle) {
                $itemSubtotal = ($detalle['precio_unitario'] * $detalle['cantidad']) - ($detalle['descuento'] ?? 0);
                $subtotal += $itemSubtotal;

                // Calcular IVA basado en código
                $taxRate = match ($detalle['codigo_iva']) {
                    '0' => 0.00, '2' => 12.00, '3' => 14.00,
                    '4' => 15.00, '5' => 5.00, '6' => 0.00, '7' => 0.00,
                    default => 0.00
                };
                $taxAmount += round(($itemSubtotal * $taxRate) / 100, 2);
            }

            $totalAmount = $subtotal + $taxAmount;

            // Usar transacción para asegurar consistencia de datos
            DB::beginTransaction();

            // Crear nota de crédito en la base de datos
            $creditNote = CreditNote::create([
                // Relaciones con factura original si está disponible
                'invoice_id' => $validatedData['invoice_id'] ?? null,
                'order_id' => null, // Se puede obtener de la factura si es necesario
                'user_id' => auth()->id(),

                // Numeración y fechas
                'credit_note_number' => $validatedData['secuencial'],
                'issue_date' => $validatedData['fecha_emision'],
                'motivo' => $validatedData['motivo'],

                // Documento modificado
                'documento_modificado_tipo' => $validatedData['documento_modificado']['tipo'],
                'documento_modificado_numero' => $this->formatDocumentNumber($validatedData['documento_modificado']['numero']),
                'documento_modificado_fecha' => $validatedData['documento_modificado']['fecha_emision'],

                // Datos del cliente
                'customer_identification' => $validatedData['comprador']['identificacion'],
                'customer_identification_type' => $validatedData['comprador']['tipo_identificacion'],
                'customer_name' => $validatedData['comprador']['razon_social'],
                'customer_email' => $validatedData['comprador']['email'] ?? null,
                'customer_address' => $validatedData['comprador']['direccion'] ?? null,
                'customer_phone' => $validatedData['comprador']['telefono'] ?? null,

                // Totales calculados
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'currency' => 'DOLAR',

                // Estado inicial
                'status' => CreditNote::STATUS_DRAFT,
                'created_via' => 'manual',
            ]);

            // Crear items de la nota de crédito
            foreach ($validatedData['detalles'] as $detalle) {
                $itemSubtotal = ($detalle['precio_unitario'] * $detalle['cantidad']) - ($detalle['descuento'] ?? 0);

                // Calcular IVA por item
                $taxRate = match ($detalle['codigo_iva']) {
                    '0' => 0.00, '2' => 12.00, '3' => 14.00,
                    '4' => 15.00, '5' => 5.00, '6' => 0.00, '7' => 0.00,
                    default => 0.00
                };
                $itemTaxAmount = round(($itemSubtotal * $taxRate) / 100, 2);

                $creditNote->items()->create([
                    'product_id' => null, // Manual entry
                    'product_code' => $detalle['codigo_interno'],
                    'product_name' => $detalle['descripcion'],
                    'quantity' => $detalle['cantidad'],
                    'unit_price' => $detalle['precio_unitario'],
                    'discount' => $detalle['descuento'] ?? 0,
                    'subtotal' => $itemSubtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $itemTaxAmount,
                    'codigo_iva' => $detalle['codigo_iva'],
                ]);
            }

            // Enviar al SRI usando el método correcto
            $sriResponse = $this->sriApiService->sendCreditNote($creditNote);

            // Confirmar transacción
            DB::commit();

            // Respuesta en formato SRI exacto
            return response()->json([
                'success' => true,
                'message' => 'Nota de crédito procesada: ' . ($sriResponse['estado'] ?? 'PROCESADO'),
                'data' => [
                    'notaCreditoId' => $creditNote->id,
                    'claveAcceso' => $creditNote->sri_access_key ?? null,
                    'numeroNotaCredito' => $creditNote->credit_note_number,
                    'estado' => $creditNote->status,
                    'fechaEmision' => $creditNote->issue_date->format('Y-m-d'),
                    'total' => $creditNote->total_amount,
                    'motivo' => $creditNote->motivo,
                    'documentoModificado' => $creditNote->documento_modificado_numero,
                    'numeroAutorizacion' => $creditNote->sri_authorization_number ?? null,
                    'fechaAutorizacion' => null,
                    'sri' => [
                        'estado' => $creditNote->status,
                        'respuesta' => $sriResponse
                    ]
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando nota de crédito', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la nota de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reintenta el envío de una nota de crédito fallida al SRI
     */
    public function retry(Request $request, $id): JsonResponse
    {
        try {
            $creditNote = CreditNote::find($id);

            if (!$creditNote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota de crédito no encontrada',
                ], 404);
            }

            if (!$creditNote->canRetry()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta nota de crédito no puede reintentarse (máximo de reintentos alcanzado o estado incorrecto)',
                ], 400);
            }

            // Reintentar usando el servicio SRI
            $result = $this->sriApiService->retryCreditNote($creditNote);

            return response()->json([
                'success' => true,
                'message' => 'Reintento de nota de crédito iniciado correctamente',
                'data' => [
                    'credit_note_id' => $creditNote->id,
                    'retry_count' => $creditNote->retry_count,
                    'sri_response' => $result,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error reintentando nota de crédito', [
                'credit_note_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al reintentar la nota de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta el estado actual de una nota de crédito en el SRI
     */
    public function checkStatus(Request $request, $id): JsonResponse
    {
        try {
            $creditNote = CreditNote::find($id);

            if (!$creditNote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota de crédito no encontrada',
                ], 404);
            }

            if (!$creditNote->sri_access_key) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta nota de crédito no tiene clave de acceso del SRI',
                ], 400);
            }

            // Extraer información del estado desde sri_response
            $sriData = null;
            if ($creditNote->sri_response) {
                try {
                    $sriResponse = json_decode($creditNote->sri_response, true);
                    if ($sriResponse && isset($sriResponse['response']['data'])) {
                        $responseData = $sriResponse['response']['data'];
                        $sriData = [
                            'clave_acceso' => $responseData['claveAcceso'] ?? $creditNote->sri_access_key,
                            'estado' => $responseData['estado'] ?? $creditNote->status,
                            'numero_autorizacion' => $responseData['numeroAutorizacion'] ?? $creditNote->sri_authorization_number,
                            'fecha_autorizacion' => $responseData['fechaAutorizacion'] ?? null,
                            'total' => $responseData['total'] ?? $creditNote->total_amount,
                            'motivo' => $responseData['motivo'] ?? $creditNote->motivo,
                            'documento_modificado' => $responseData['documentoModificado'] ?? $creditNote->documento_modificado_numero,
                        ];
                    }
                } catch (\Exception $e) {
                    // Si hay error parseando JSON, usar datos básicos
                    $sriData = [
                        'clave_acceso' => $creditNote->sri_access_key,
                        'estado' => $creditNote->status,
                        'numero_autorizacion' => $creditNote->sri_authorization_number,
                        'fecha_autorizacion' => null,
                        'total' => $creditNote->total_amount,
                        'motivo' => $creditNote->motivo,
                        'documento_modificado' => $creditNote->documento_modificado_numero,
                    ];
                }
            } else {
                // Si no hay sri_response, usar datos básicos
                $sriData = [
                    'clave_acceso' => $creditNote->sri_access_key,
                    'estado' => $creditNote->status,
                    'numero_autorizacion' => $creditNote->sri_authorization_number,
                    'fecha_autorizacion' => null,
                    'total' => $creditNote->total_amount,
                    'motivo' => $creditNote->motivo,
                    'documento_modificado' => $creditNote->documento_modificado_numero,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'credit_note_id' => $creditNote->id,
                    'current_status' => $creditNote->status,
                    'sri_access_key' => $creditNote->sri_access_key,
                    'sri_authorization_number' => $creditNote->sri_authorization_number,
                    'sri_status' => $sriData,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error consultando estado SRI de nota de crédito', [
                'credit_note_id' => $id,
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
     * Actualiza los datos editables de una nota de crédito
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $creditNote = CreditNote::find($id);

            if (!$creditNote) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nota de crédito no encontrada',
                ], 404);
            }

            // Validar que la nota puede ser editada (no debe estar autorizada)
            if (in_array($creditNote->status, [CreditNote::STATUS_AUTHORIZED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una nota de crédito autorizada por el SRI',
                ], 400);
            }

            // Validar datos de entrada
            $validatedData = $request->validate([
                'customer_name' => 'sometimes|required|string|max:255',
                'customer_identification' => 'sometimes|required|string|max:13|min:10',
                'customer_email' => 'sometimes|nullable|email|max:255',
                'customer_address' => 'sometimes|required|string|max:500',
                'customer_phone' => 'sometimes|nullable|string|max:20',
                'motivo' => 'sometimes|required|string|min:5|max:500',
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

            // Actualizar nota de crédito
            $creditNote->update($validatedData);

            // Log de cambios para auditoría
            Log::info('Nota de crédito editada manualmente por admin', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'admin_user' => auth()->user()->name ?? 'Admin',
                'changed_fields' => array_keys($validatedData),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nota de crédito actualizada correctamente',
                'data' => [
                    'credit_note_id' => $creditNote->id,
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
            Log::error('Error actualizando nota de crédito', [
                'credit_note_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la nota de crédito',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de notas de crédito para el dashboard admin
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            // Estadísticas básicas de notas de crédito
            $stats = [
                'total_credit_notes' => CreditNote::count(),
                'authorized' => CreditNote::where('status', CreditNote::STATUS_AUTHORIZED)->count(),
                'pending' => CreditNote::whereIn('status', [
                    CreditNote::STATUS_PENDING,
                    CreditNote::STATUS_PROCESSING,
                    CreditNote::STATUS_SENT_TO_SRI
                ])->count(),
                'failed' => CreditNote::where('status', CreditNote::STATUS_FAILED)->count(),
                'definitively_failed' => CreditNote::where('status', CreditNote::STATUS_DEFINITIVELY_FAILED)->count(),
                'draft' => CreditNote::where('status', CreditNote::STATUS_DRAFT)->count(),
            ];

            $stats['success_rate'] = $stats['total_credit_notes'] > 0
                ? round(($stats['authorized'] / $stats['total_credit_notes']) * 100, 2)
                : 0;

            // Estadísticas adicionales
            $recentCreditNotes = CreditNote::orderBy('created_at', 'desc')->take(5)->get();
            $pendingRetries = CreditNote::where('status', CreditNote::STATUS_FAILED)
                ->where('retry_count', '<', 12)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'sri_stats' => $stats,
                    'additional_stats' => [
                        'failed_credit_notes' => $stats['failed'],
                        'pending_retries' => $pendingRetries,
                        'recent_credit_notes' => $recentCreditNotes->map(function ($creditNote) {
                            return [
                                'id' => $creditNote->id,
                                'credit_note_number' => $creditNote->credit_note_number,
                                'customer_name' => $creditNote->customer_name,
                                'total_amount' => $creditNote->total_amount,
                                'status' => $creditNote->status,
                                'created_at' => $creditNote->created_at->format('Y-m-d H:i:s'),
                            ];
                        }),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estadísticas de notas de crédito', [
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
     * Descargar PDF de una nota de crédito
     */
    public function downloadPdf(Request $request, $id): Response
    {
        try {
            $creditNote = CreditNote::with(['invoice', 'order.items.product', 'order.user', 'items', 'user'])->find($id);

            if (!$creditNote) {
                return response('Nota de crédito no encontrada', 404);
            }

            // Verificar que la nota de crédito esté autorizada por el SRI
            if ($creditNote->status !== CreditNote::STATUS_AUTHORIZED) {
                return response('Solo se pueden generar PDFs para notas de crédito autorizadas por el SRI', 400);
            }

            // Si ya existe un PDF generado, devolverlo
            if ($creditNote->pdf_path && Storage::disk('public')->exists($creditNote->pdf_path)) {
                $pdfContent = Storage::disk('public')->get($creditNote->pdf_path);
                $fileName = "nota_credito_{$creditNote->credit_note_number}.pdf";

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                    'Content-Length' => strlen($pdfContent),
                ]);
            }

            // Generar PDF usando el UseCase
            $pdfPath = $this->generatePdfUseCase->execute($creditNote);

            // Obtener el contenido del PDF generado
            $pdfContent = Storage::disk('public')->get($pdfPath);
            $fileName = "nota_credito_{$creditNote->credit_note_number}.pdf";

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
                'Content-Length' => strlen($pdfContent),
            ]);

        } catch (\Exception $e) {
            Log::error('Error descargando PDF de nota de crédito', [
                'credit_note_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Error al generar el PDF: '.$e->getMessage(), 500);
        }
    }

    /**
     * Obtiene las etiquetas de estado para mostrar en el frontend
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            CreditNote::STATUS_DRAFT => 'Borrador',
            CreditNote::STATUS_SENT_TO_SRI => 'Enviado al SRI',
            CreditNote::STATUS_PENDING => 'Pendiente',
            CreditNote::STATUS_PROCESSING => 'Procesando',
            CreditNote::STATUS_RECEIVED => 'Recibida por SRI',
            CreditNote::STATUS_AUTHORIZED => 'Autorizada',
            CreditNote::STATUS_REJECTED => 'Rechazada',
            CreditNote::STATUS_NOT_AUTHORIZED => 'No Autorizada',
            CreditNote::STATUS_RETURNED => 'Devuelta',
            CreditNote::STATUS_SRI_ERROR => 'Error SRI',
            CreditNote::STATUS_FAILED => 'Fallida',
            CreditNote::STATUS_DEFINITIVELY_FAILED => 'Fallida Definitivamente',
            default => ucfirst(strtolower($status))
        };
    }

    /**
     * Obtiene los colores de estado para el frontend
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            CreditNote::STATUS_DRAFT => 'gray',
            CreditNote::STATUS_SENT_TO_SRI => 'blue',
            CreditNote::STATUS_PENDING => 'yellow',
            CreditNote::STATUS_PROCESSING => 'blue',
            CreditNote::STATUS_RECEIVED => 'indigo',
            CreditNote::STATUS_AUTHORIZED => 'green',
            CreditNote::STATUS_REJECTED => 'red',
            CreditNote::STATUS_NOT_AUTHORIZED => 'red',
            CreditNote::STATUS_RETURNED => 'orange',
            CreditNote::STATUS_SRI_ERROR => 'red',
            CreditNote::STATUS_FAILED => 'red',
            CreditNote::STATUS_DEFINITIVELY_FAILED => 'red',
            default => 'gray'
        };
    }

    /**
     * Obtiene lista de facturas autorizadas para crear notas de crédito
     */
    public function getAuthorizedInvoices(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search', '');
            $limit = min($request->get('limit', 50), 100); // Máximo 100 resultados

            $query = Invoice::where('status', Invoice::STATUS_AUTHORIZED)
                ->orderBy('created_at', 'desc');

            // Búsqueda por número de factura o nombre de cliente
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'like', '%' . $search . '%')
                      ->orWhere('customer_name', 'like', '%' . $search . '%')
                      ->orWhere('customer_identification', 'like', '%' . $search . '%');
                });
            }

            $invoices = $query->limit($limit)->get([
                'id',
                'invoice_number',
                'customer_name',
                'customer_identification',
                'customer_email',
                'customer_address',
                'customer_phone',
                'customer_identification_type',
                'total_amount',
                'subtotal',
                'tax_amount',
                'issue_date',
                'created_at'
            ]);

            $data = $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'display_label' => $invoice->invoice_number . ' - ' . $invoice->customer_name . ' ($' . number_format($invoice->total_amount, 2) . ')',
                    'customer' => [
                        'name' => $invoice->customer_name,
                        'identification' => $invoice->customer_identification,
                        'identification_type' => $invoice->customer_identification_type,
                        'email' => $invoice->customer_email,
                        'address' => $invoice->customer_address,
                        'phone' => $invoice->customer_phone,
                    ],
                    'amounts' => [
                        'total' => $invoice->total_amount,
                        'subtotal' => $invoice->subtotal,
                        'tax' => $invoice->tax_amount,
                    ],
                    'issue_date' => $invoice->issue_date->format('Y-m-d'),
                    'created_at' => $invoice->created_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo facturas autorizadas', [
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
     * Obtener datos completos de una factura para crear nota de crédito (SRI)
     * Incluye: cliente, factura, detalles - datos REALES sin hardcodeo
     */
    public function getInvoiceFullData(Request $request, $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::with('items')->find($invoiceId);

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            if ($invoice->status !== Invoice::STATUS_AUTHORIZED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden usar facturas autorizadas para crear notas de crédito',
                ], 400);
            }

            // Datos completos REALES de la factura para SRI
            $fullData = [
                // Datos de la factura original
                'factura' => [
                    'numero' => $invoice->invoice_number,
                    'fechaEmision' => $invoice->issue_date->format('Y-m-d'),
                    'total' => $invoice->total_amount,
                    'subtotal' => $invoice->subtotal,
                    'taxAmount' => $invoice->tax_amount,
                ],

                // Datos REALES del cliente
                'comprador' => [
                    'tipoIdentificacion' => $invoice->customer_identification_type, // "05" o "04"
                    'identificacion' => $invoice->customer_identification,
                    'razonSocial' => $invoice->customer_name,
                    'direccion' => $invoice->customer_address,
                    'email' => $invoice->customer_email,
                    'telefono' => $invoice->customer_phone,
                ],

                // Detalles REALES de productos
                'detalles' => $invoice->items->map(function ($item) {
                    return [
                        'codigoInterno' => $item->product_code,
                        'descripcion' => $item->product_name,
                        'cantidad' => $item->quantity,
                        'precioUnitario' => $item->unit_price,
                        'descuento' => $item->discount ?? 0,
                        'codigoIva' => $this->mapTaxRateToCodigoIva($item->tax_rate),
                        'subtotal' => $item->subtotal,
                        'taxAmount' => $item->tax_amount,
                    ];
                })->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $fullData,
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo datos completos de factura', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los datos completos de la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles de una factura específica para crear nota de crédito
     */
    public function getInvoiceDetails(Request $request, $invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::with('items')->find($invoiceId);

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            if ($invoice->status !== Invoice::STATUS_AUTHORIZED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden usar facturas autorizadas para crear notas de crédito',
                ], 400);
            }

            // Formatear detalles de la factura con datos REALES
            $details = $invoice->items->map(function ($item) {
                return [
                    'product_code' => $item->product_code,  // Campo real existente
                    'product_name' => $item->product_name,  // Campo real existente (no description)
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount ?? 0,
                    'codigo_iva' => $this->mapTaxRateToCodigoIva($item->tax_rate),  // Usar tax_rate real
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $details,
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo detalles de factura', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los detalles de la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mapear tarifa real de IVA a código SRI Ecuador 2024
     * Usa datos REALES del campo tax_rate en InvoiceItem
     */
    private function mapTaxRateToCodigoIva(float $taxRate): string
    {
        return match($taxRate) {
            0.00 => '0',   // IVA 0% - Venta exenta
            5.00 => '5',   // IVA 5% - Materiales construcción
            12.00 => '2',  // IVA 12% - Obsoleto desde abril 2024
            14.00 => '3',  // IVA 14% - Casos especiales
            15.00 => '4',  // IVA 15% - Vigente Ecuador 2024
            default => '4' // Por defecto IVA 15% (vigente Ecuador 2024)
        };
    }

    /**
     * Mapear tipo de impuesto a código IVA del SRI Ecuador 2024
     * IVA 15% vigente desde abril 2024
     * @deprecated Usar mapTaxRateToCodigoIva() para datos reales
     */
    private function mapTaxTypeToCodigoIva(?string $taxType): string
    {
        switch ($taxType) {
            case 'exempt':
                return '0'; // Venta exenta
            case 'zero':
                return '7'; // IVA 0%
            case 'twelve':
                return '2'; // IVA 12% (obsoleto desde abril 2024)
            case 'fifteen':
                return '4'; // IVA 15% (vigente desde abril 2024)
            case 'fourteen':
                return '3'; // IVA 14%
            case 'five':
                return '5'; // IVA 5% (materiales construcción)
            default:
                return '4'; // Por defecto IVA 15% (vigente Ecuador 2024)
        }
    }

    /**
     * Obtener facturas autorizadas disponibles para notas de crédito (datos reales para UX)
     */
    public function getAuthorizedInvoicesReal(Request $request): JsonResponse
    {
        try {
            $search = $request->get('search');
            $limit = min((int)$request->get('limit', 20), 100);

            $query = Invoice::with(['user', 'items'])
                ->where('status', Invoice::STATUS_AUTHORIZED);

            // Filtrar por búsqueda
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('invoice_number', 'LIKE', "%{$search}%")
                      ->orWhere('customer_name', 'LIKE', "%{$search}%")
                      ->orWhere('customer_identification', 'LIKE', "%{$search}%");
                });
            }

            $invoices = $query->orderBy('created_at', 'desc')
                             ->limit($limit)
                             ->get();

            $data = $invoices->map(function($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'display_label' => "{$invoice->invoice_number} - {$invoice->customer_name} ($".number_format($invoice->total_amount, 2).")",
                    'customer' => [
                        'name' => $invoice->customer_name,
                        'identification' => $invoice->customer_identification,
                        'identification_type' => $invoice->customer_identification_type,
                        'email' => $invoice->customer_email,
                        'address' => $invoice->customer_address,
                        'phone' => $invoice->customer_phone,
                    ],
                    'amounts' => [
                        'total' => $invoice->total_amount,
                        'subtotal' => $invoice->subtotal,
                        'tax' => $invoice->tax_amount,
                    ],
                    'issue_date' => $invoice->issue_date->format('Y-m-d'),
                    'created_at' => $invoice->created_at->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'total' => $data->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo facturas autorizadas', [
                'error' => $e->getMessage(),
                'search' => $request->get('search'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener facturas autorizadas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles completos de una factura para nota de crédito (datos reales)
     */
    public function getInvoiceDetailsReal($invoiceId): JsonResponse
    {
        try {
            $invoice = Invoice::with('items.product')->find($invoiceId);

            if (!$invoice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada',
                ], 404);
            }

            if ($invoice->status !== Invoice::STATUS_AUTHORIZED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden crear notas de crédito para facturas autorizadas',
                ], 400);
            }

            $details = $invoice->items->map(function($item) {
                return [
                    'product_code' => $item->product_code,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount ?? 0,
                    'codigo_iva' => $this->mapTaxRateToCodigoIva($item->tax_rate),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $details,
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo detalles de factura', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalles de la factura',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Generar el siguiente secuencial para notas de crédito
     */
    private function generateNextSecuencial(): string
    {
        // Obtener el último secuencial de la base de datos
        $lastCreditNote = CreditNote::orderBy('id', 'desc')->first();

        if ($lastCreditNote && $lastCreditNote->credit_note_number) {
            // Extraer el número y incrementar
            $lastNumber = (int) $lastCreditNote->credit_note_number;
            $nextNumber = $lastNumber + 1;
        } else {
            // Si no hay notas de crédito, empezar en 1
            $nextNumber = 1;
        }

        // Formatear a 9 dígitos con ceros a la izquierda
        return str_pad($nextNumber, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Formatea el número de documento para usar el formato requerido por SRI
     * Si viene solo el secuencial (ej: "000000026"), lo convierte a "001-001-000000026"
     */
    private function formatDocumentNumber(string $number): string
    {
        // Si ya tiene el formato correcto (XXX-XXX-XXXXXXXXX), lo devuelve tal como está
        if (preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{9}$/', $number)) {
            return $number;
        }

        // Si solo viene el secuencial (9 dígitos), agrega el establecimiento y punto de emisión
        if (preg_match('/^[0-9]{9}$/', $number)) {
            return "001-001-{$number}";
        }

        // Si viene como número sin ceros a la izquierda, lo formatea
        if (is_numeric($number)) {
            $secuencial = str_pad($number, 9, '0', STR_PAD_LEFT);
            return "001-001-{$secuencial}";
        }

        // Si no cumple ningún formato esperado, se asume que es el secuencial
        // y se formatea con ceros a la izquierda
        $cleanNumber = preg_replace('/[^0-9]/', '', $number);
        $secuencial = str_pad($cleanNumber, 9, '0', STR_PAD_LEFT);
        return "001-001-{$secuencial}";
    }
}