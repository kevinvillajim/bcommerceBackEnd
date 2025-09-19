<?php

namespace App\Services;

use App\Events\CreditNoteApproved;
use App\Events\InvoiceApproved;
use App\Models\Invoice;
use App\Models\CreditNote;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SriApiService
{
    private string $apiUrl;

    private string $email;

    private string $password;

    private int $timeout;

    private ?string $authToken = null;

    public function __construct()
    {
        // ✅ Configuración para API real con JWT
        $this->apiUrl = config('sri.api_url', 'http://localhost:3100');
        $this->email = config('sri.email', 'businessconnect@businessconnect.com.ec');
        $this->password = config('sri.password', 'dalcroze77aA@');
        $this->timeout = config('sri.timeout', 30);

        if (empty($this->email) || empty($this->password)) {
            throw new Exception('Credenciales del SRI no configuradas (SRI_EMAIL, SRI_PASSWORD)');
        }
    }

    /**
     * ✅ Autentica con la API SRI y obtiene JWT token
     */
    private function authenticate(): string
    {
        if ($this->authToken) {
            return $this->authToken; // Reutilizar token existente
        }

        Log::info('Autenticando con API SRI', [
            'email' => $this->email,
            'api_url' => $this->apiUrl,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->apiUrl.'/api/auth/login', [
                    'email' => $this->email,
                    'password' => $this->password,
                ]);

            if (! $response->successful()) {
                throw new Exception(
                    "Error de autenticación HTTP {$response->status()}: ".$response->body()
                );
            }

            $responseData = $response->json();

            if (! isset($responseData['token'])) {
                throw new Exception('Respuesta de autenticación inválida: falta token');
            }

            $this->authToken = $responseData['token'];

            Log::info('Autenticación exitosa con API SRI');

            return $this->authToken;

        } catch (Exception $e) {
            Log::error('Error en autenticación con API SRI', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * ✅ Envía una factura al SRI y retorna la respuesta
     */
    public function sendInvoice(Invoice $invoice): array
    {
        Log::info('Enviando factura al SRI', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'api_url' => $this->apiUrl,
        ]);

        try {
            // ✅ Autenticar y obtener token JWT
            $token = $this->authenticate();

            // ✅ Preparar payload usando el mapper
            $mapper = new SriDataMapperService;
            $payload = $mapper->buildSriPayload($invoice);

            // ✅ Realizar petición HTTP al API del SRI con JWT
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post($this->apiUrl.'/api/invoices', $payload);

            // ✅ Verificar respuesta HTTP
            if (! $response->successful()) {
                $errorMessage = $this->extractSriErrorMessage($response);
                throw new Exception($errorMessage);
            }

            $responseData = $response->json();

            Log::info('Respuesta del SRI recibida', [
                'invoice_id' => $invoice->id,
                'status_code' => $response->status(),
                'response' => $responseData,
            ]);

            // ✅ Procesar respuesta según el estado
            $this->processApiResponse($invoice, $responseData);

            return $responseData;

        } catch (Exception $e) {
            Log::error('Error enviando factura al SRI', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ Marcar factura como fallida
            $invoice->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * ✅ Procesa la respuesta del API y actualiza el estado de la factura
     */
    private function processApiResponse(Invoice $invoice, array $response): void
    {
        // ✅ Estructura real según InvoiceCreationResponse:
        // {
        //   "success": true,
        //   "message": "Factura creada exitosamente",
        //   "data": {
        //     "invoice": {
        //       "id": 1,
        //       "secuencial": "000000001",
        //       "claveAcceso": "1501202501179320414400110010010000000011234567814",
        //       "fechaEmision": "2025-01-15",
        //       "estado": "PENDIENTE",
        //       "total": 115.00
        //     },
        //     "totales": {
        //       "subtotal": 100.00,
        //       "iva": 15.00,
        //       "total": 115.00
        //     }
        //   }
        // }

        if (! isset($response['success'])) {
            throw new Exception('Respuesta del SRI inválida: falta campo success');
        }

        if ($response['success'] === true && isset($response['data'])) {
            $invoiceData = $response['data'];

            // ✅ Factura creada exitosamente en la API SRI
            $claveAcceso = $invoiceData['claveAcceso'] ?? '';
            $estado = $invoiceData['estado'] ?? 'PENDIENTE';

            if (empty($claveAcceso)) {
                throw new Exception('Respuesta del SRI inválida: falta claveAcceso');
            }

            // ✅ Mapear estados de API SRI a estados BCommerce
            $newStatus = match ($estado) {
                'PENDIENTE' => Invoice::STATUS_PENDING,
                'PROCESANDO' => Invoice::STATUS_PROCESSING,
                'RECIBIDA' => Invoice::STATUS_RECEIVED,
                'AUTORIZADO' => Invoice::STATUS_AUTHORIZED,
                'RECHAZADO' => Invoice::STATUS_REJECTED,
                'NO_AUTORIZADO' => Invoice::STATUS_NOT_AUTHORIZED,
                'DEVUELTA' => Invoice::STATUS_RETURNED,
                'ERROR', 'ERROR_SRI' => Invoice::STATUS_SRI_ERROR,
                default => Invoice::STATUS_PENDING
            };

            // ✅ Procesar información adicional del SRI (si está disponible)
            $sriInfo = $invoiceData['sri'] ?? null;
            $authNumber = $invoiceData['numeroAutorizacion'] ?? null;

            // ✅ Actualizar factura con datos del SRI
            $invoice->update([
                'status' => $newStatus,
                'sri_access_key' => $claveAcceso,
                'sri_authorization_number' => $authNumber,
                'sri_response' => json_encode([
                    'response' => $response,
                    'sri_info' => $sriInfo,
                    'processed_at' => now()->toISOString(),
                ]),
            ]);

            Log::info('Factura procesada por la API SRI', [
                'invoice_id' => $invoice->id,
                'clave_acceso' => $claveAcceso,
                'estado_sri' => $estado,
                'estado_bcommerce' => $newStatus,
                'sri_invoice_id' => $invoiceData['facturaId'] ?? null,
            ]);

            // ✅ Disparar evento si la factura fue aprobada Y no se ha procesado antes
            if (in_array($newStatus, [Invoice::STATUS_AUTHORIZED]) && empty($invoice->pdf_path)) {
                Log::info('Factura aprobada por SRI, disparando evento InvoiceApproved', [
                    'invoice_id' => $invoice->id,
                    'status' => $newStatus,
                ]);

                event(new InvoiceApproved($invoice, $response));
            }

        } else {
            // ✅ Factura rechazada o con errores
            $errorMessage = $response['message'] ?? 'Error desconocido del SRI';
            $invoice->markAsFailed($errorMessage);

            Log::warning('Factura rechazada por la API SRI', [
                'invoice_id' => $invoice->id,
                'error_message' => $errorMessage,
                'response' => $response,
            ]);

            throw new Exception("Factura rechazada por la API SRI: {$errorMessage}");
        }
    }

    /**
     * ✅ Consulta el estado de una factura en el SRI usando su clave de acceso
     */
    public function checkInvoiceStatus(string $claveAcceso): array
    {
        Log::info('Consultando estado de factura en SRI', [
            'clave_acceso' => $claveAcceso,
        ]);

        try {
            $token = $this->authenticate();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->get($this->apiUrl.'/api/invoices/status/'.$claveAcceso);

            if (! $response->successful()) {
                throw new Exception(
                    "Error HTTP {$response->status()} consultando estado: ".$response->body()
                );
            }

            $responseData = $response->json();

            Log::info('Estado de factura consultado', [
                'clave_acceso' => $claveAcceso,
                'response' => $responseData,
            ]);

            return $responseData;

        } catch (Exception $e) {
            Log::error('Error consultando estado de factura', [
                'clave_acceso' => $claveAcceso,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * ✅ Prueba la conectividad con el API del SRI
     */
    public function testConnection(): array
    {
        Log::info('Probando conexión con API del SRI', [
            'api_url' => $this->apiUrl,
        ]);

        try {
            $token = $this->authenticate();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])
                ->timeout(10) // Timeout corto para prueba
                ->get($this->apiUrl.'/health');

            $isSuccessful = $response->successful();
            $statusCode = $response->status();
            $responseData = $response->json();

            Log::info('Resultado de prueba de conexión', [
                'success' => $isSuccessful,
                'status_code' => $statusCode,
                'response' => $responseData,
            ]);

            return [
                'success' => $isSuccessful,
                'status_code' => $statusCode,
                'response' => $responseData,
                'message' => $isSuccessful ? 'Conexión exitosa' : 'Error de conexión',
            ];

        } catch (Exception $e) {
            Log::error('Error en prueba de conexión con SRI', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status_code' => 0,
                'response' => null,
                'message' => 'Error de conexión: '.$e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Reintenta el envío de una factura existente al SRI sin modificar datos originales
     */
    public function retrySendInvoice(Invoice $invoice): array
    {
        Log::info('Sistema inteligente de retry para factura SRI', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'has_sri_access_key' => ! empty($invoice->sri_access_key),
            'current_status' => $invoice->status,
            'current_retry_count' => $invoice->retry_count,
        ]);

        try {
            // ✅ Autenticar y obtener token JWT
            $token = $this->authenticate();

            // ✅ Si la factura ya tiene clave de acceso, consultar estado actual
            if ($invoice->sri_access_key) {
                Log::info('CASO A: Factura ya enviada previamente - consultando estado actual', [
                    'invoice_id' => $invoice->id,
                    'sri_access_key' => $invoice->sri_access_key,
                ]);

                $statusResponse = $this->checkInvoiceStatus($invoice->sri_access_key);

                // Procesar respuesta de consulta de estado
                $this->processStatusResponse($invoice, $statusResponse);

                return $statusResponse;
            }

            // ✅ CASO B: Factura sin sri_access_key - puede estar en estado intermedio
            // Con el sistema robusto, esto puede pasar si falló antes de completarse
            Log::info('Factura sin sri_access_key - verificando si está en estado intermedio', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status,
                'strategy' => 'Usar sendInvoice() que ahora maneja estados intermedios inteligentemente',
            ]);

            // ✅ Usar sendInvoice() que ahora tiene lógica de estados inteligente
            // La API del SRI detectará si está en estado intermedio y continuará desde ahí
            return $this->sendInvoice($invoice);

        } catch (Exception $e) {
            Log::error('Error en sistema inteligente de retry', [
                'invoice_id' => $invoice->id,
                'has_sri_access_key' => ! empty($invoice->sri_access_key),
                'retry_count' => $invoice->retry_count,
                'error' => $e->getMessage(),
            ]);

            // ✅ Marcar como fallida usando método existente
            $invoice->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * ✅ Reintenta el envío de una factura (para el sistema de retry)
     */
    public function retryInvoice(Invoice $invoice): array
    {
        Log::info('Reintentando envío de factura al SRI', [
            'invoice_id' => $invoice->id,
            'current_retry_count' => $invoice->retry_count,
        ]);

        // ✅ Verificar que la factura puede reintentarse
        if (! $invoice->canRetry()) {
            throw new Exception("La factura {$invoice->id} no puede reintentarse (max reintentos alcanzado o estado incorrecto)");
        }

        // ✅ Incrementar contador de reintentos ANTES del intento
        $invoice->incrementRetryCount();

        try {
            // ✅ Actualizar estado a "enviando"
            $invoice->update(['status' => Invoice::STATUS_SENT_TO_SRI]);

            // ✅ Intentar envío usando método específico para retry
            $response = $this->retrySendInvoice($invoice);

            Log::info('Reintento de factura exitoso', [
                'invoice_id' => $invoice->id,
                'retry_count' => $invoice->retry_count,
            ]);

            return $response;

        } catch (Exception $e) {
            Log::error('Fallo en reintento de factura', [
                'invoice_id' => $invoice->id,
                'retry_count' => $invoice->retry_count,
                'error' => $e->getMessage(),
            ]);

            // ✅ Si alcanzó el máximo de reintentos, marcar como definitivamente fallida
            if (! $invoice->canRetry()) {
                $invoice->markAsDefinitivelyFailed();

                Log::critical('Factura marcada como definitivamente fallida', [
                    'invoice_id' => $invoice->id,
                    'final_retry_count' => $invoice->retry_count,
                ]);
            }

            throw $e;
        }
    }

    /**
     * ✅ Obtiene estadísticas de facturas enviadas al SRI
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_invoices' => Invoice::count(),
            'authorized' => Invoice::where('status', Invoice::STATUS_AUTHORIZED)->count(),
            'pending' => Invoice::where('status', Invoice::STATUS_SENT_TO_SRI)->count(),
            'failed' => Invoice::where('status', Invoice::STATUS_FAILED)->count(),
            'definitively_failed' => Invoice::where('status', Invoice::STATUS_DEFINITIVELY_FAILED)->count(),
            'draft' => Invoice::where('status', Invoice::STATUS_DRAFT)->count(),
        ];

        $stats['success_rate'] = $stats['total_invoices'] > 0
            ? round(($stats['authorized'] / $stats['total_invoices']) * 100, 2)
            : 0;

        return $stats;
    }

    /**
     * ✅ Procesa respuesta de consulta de estado SRI
     */
    private function processStatusResponse(Invoice $invoice, array $response): void
    {
        // Lógica para procesar consulta de estado y actualizar factura si es necesario
        if (isset($response['estado'])) {
            $estado = $response['estado'];

            $newStatus = match ($estado) {
                'AUTORIZADO' => Invoice::STATUS_AUTHORIZED,
                'RECHAZADO' => Invoice::STATUS_REJECTED,
                'PENDIENTE' => Invoice::STATUS_PENDING,
                'PROCESANDO' => Invoice::STATUS_PROCESSING,
                'RECIBIDA' => Invoice::STATUS_RECEIVED,
                'NO_AUTORIZADO' => Invoice::STATUS_NOT_AUTHORIZED,
                'DEVUELTA' => Invoice::STATUS_RETURNED,
                'ERROR', 'ERROR_SRI' => Invoice::STATUS_SRI_ERROR,
                default => $invoice->status // Mantener estado actual si no cambia
            };

            if ($newStatus !== $invoice->status) {
                $updateData = ['status' => $newStatus];

                // Si tiene información adicional del SRI, actualizarla también
                if (isset($response['numeroAutorizacion'])) {
                    $updateData['sri_authorization_number'] = $response['numeroAutorizacion'];
                }

                if (isset($response['fechaAutorizacion'])) {
                    $updateData['sri_response'] = json_encode([
                        'response' => $response,
                        'updated_at' => now()->toISOString(),
                        'source' => 'status_check_retry',
                    ]);
                }

                $invoice->update($updateData);

                Log::info('Estado de factura actualizado desde consulta SRI', [
                    'invoice_id' => $invoice->id,
                    'old_status' => $invoice->status,
                    'new_status' => $newStatus,
                    'sri_estado' => $estado,
                    'updated_fields' => array_keys($updateData),
                ]);

                // Si la factura fue autorizada Y no se ha procesado antes, disparar evento
                if (in_array($newStatus, [Invoice::STATUS_AUTHORIZED]) && empty($invoice->pdf_path)) {
                    Log::info('Factura autorizada durante retry, disparando evento', [
                        'invoice_id' => $invoice->id,
                        'status' => $newStatus,
                    ]);

                    event(new \App\Events\InvoiceApproved($invoice, $response));
                }
            } else {
                Log::info('Estado de factura sin cambios después de consulta SRI', [
                    'invoice_id' => $invoice->id,
                    'current_status' => $invoice->status,
                    'sri_estado' => $estado,
                ]);
            }
        } else {
            Log::warning('Respuesta de consulta SRI sin campo estado', [
                'invoice_id' => $invoice->id,
                'response' => $response,
            ]);
        }
    }

    /**
     * Extrae el mensaje específico del error del SRI API
     */
    private function extractSriErrorMessage($response): string
    {
        try {
            // Intentar parsear el JSON de la respuesta
            $responseData = $response->json();

            // Si tiene el campo 'message', usarlo directamente
            if (isset($responseData['message']) && ! empty($responseData['message'])) {
                return $responseData['message'];
            }

            // Si tiene 'error', usarlo como fallback
            if (isset($responseData['error']) && ! empty($responseData['error'])) {
                return $responseData['error'];
            }

        } catch (Exception $e) {
            // Si no se puede parsear el JSON, usar fallback
            Log::warning('No se pudo parsear respuesta JSON del SRI', [
                'response_body' => $response->body(),
                'parse_error' => $e->getMessage(),
            ]);
        }

        // Fallback: usar el mensaje HTTP con el body completo
        return "Error HTTP {$response->status()}: ".$response->body();
    }

    // ✅ =================== MÉTODOS PARA NOTAS DE CRÉDITO ===================

    /**
     * ✅ Envía una nota de crédito al SRI y retorna la respuesta
     */
    public function sendCreditNote(CreditNote $creditNote): array
    {
        Log::info('Enviando nota de crédito al SRI', [
            'credit_note_id' => $creditNote->id,
            'credit_note_number' => $creditNote->credit_note_number,
            'api_url' => $this->apiUrl,
        ]);

        try {
            // ✅ Autenticar y obtener token JWT
            $token = $this->authenticate();

            // ✅ Preparar payload usando el mapper
            $mapper = new SriDataMapperService;
            $payload = $mapper->buildCreditNoteSriPayload($creditNote);

            // ✅ Realizar petición HTTP al API del SRI con JWT
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->post($this->apiUrl.'/api/credit-notes', $payload);

            // ✅ Verificar respuesta HTTP
            if (!$response->successful()) {
                $errorMessage = $this->extractSriErrorMessage($response);
                throw new Exception($errorMessage);
            }

            $responseData = $response->json();

            Log::info('Respuesta del SRI para nota de crédito recibida', [
                'credit_note_id' => $creditNote->id,
                'status_code' => $response->status(),
                'response' => $responseData,
            ]);

            // ✅ Procesar respuesta según el estado
            $this->processCreditNoteApiResponse($creditNote, $responseData);

            return $responseData;

        } catch (Exception $e) {
            Log::error('Error enviando nota de crédito al SRI', [
                'credit_note_id' => $creditNote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // ✅ Marcar nota de crédito como fallida
            $creditNote->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * ✅ Procesa la respuesta del API para notas de crédito y actualiza el estado
     */
    private function processCreditNoteApiResponse(CreditNote $creditNote, array $response): void
    {
        // ✅ Estructura real según CreditNoteCreationResponse:
        // {
        //   "success": true,
        //   "message": "Nota de crédito procesada: AUTORIZADO",
        //   "data": {
        //     "notaCreditoId": 123,
        //     "claveAcceso": "1709202504179320414400110010010000000016171157517",
        //     "numeroNotaCredito": "000000001",
        //     "estado": "AUTORIZADO",
        //     "fechaEmision": "2025-01-15",
        //     "total": 100.80,
        //     "motivo": "Devolución de mercadería...",
        //     "documentoModificado": "001-001-000000126",
        //     "numeroAutorizacion": "1709202504179320414400110010010000000016171157517",
        //     "fechaAutorizacion": "2025-01-15T22:56:15.000Z"
        //   }
        // }

        if (!isset($response['success'])) {
            throw new Exception('Respuesta del SRI inválida: falta campo success');
        }

        if ($response['success'] === true && isset($response['data'])) {
            $creditNoteData = $response['data'];

            // ✅ Nota de crédito creada exitosamente en la API SRI
            $claveAcceso = $creditNoteData['claveAcceso'] ?? '';
            $estado = $creditNoteData['estado'] ?? 'PENDIENTE';

            if (empty($claveAcceso)) {
                throw new Exception('Respuesta del SRI inválida: falta claveAcceso');
            }

            // ✅ Mapear estados de API SRI a estados BCommerce (idénticos a facturas)
            $newStatus = match ($estado) {
                'PENDIENTE' => CreditNote::STATUS_PENDING,
                'PROCESANDO' => CreditNote::STATUS_PROCESSING,
                'RECIBIDA' => CreditNote::STATUS_RECEIVED,
                'AUTORIZADO' => CreditNote::STATUS_AUTHORIZED,
                'RECHAZADO' => CreditNote::STATUS_REJECTED,
                'NO_AUTORIZADO' => CreditNote::STATUS_NOT_AUTHORIZED,
                'DEVUELTA' => CreditNote::STATUS_RETURNED,
                'ERROR', 'ERROR_SRI' => CreditNote::STATUS_SRI_ERROR,
                default => CreditNote::STATUS_PENDING
            };

            // ✅ Procesar información adicional del SRI (si está disponible)
            $sriInfo = $creditNoteData['sri'] ?? null;
            $authNumber = $creditNoteData['numeroAutorizacion'] ?? null;

            // ✅ Actualizar nota de crédito con datos del SRI
            $creditNote->update([
                'status' => $newStatus,
                'sri_access_key' => $claveAcceso,
                'sri_authorization_number' => $authNumber,
                'sri_response' => json_encode([
                    'response' => $response,
                    'sri_info' => $sriInfo,
                    'processed_at' => now()->toISOString(),
                ]),
            ]);

            Log::info('Nota de crédito procesada por la API SRI', [
                'credit_note_id' => $creditNote->id,
                'clave_acceso' => $claveAcceso,
                'estado_sri' => $estado,
                'estado_bcommerce' => $newStatus,
                'sri_credit_note_id' => $creditNoteData['notaCreditoId'] ?? null,
            ]);

            // ✅ Disparar evento si la nota fue aprobada por el SRI
            if (in_array($newStatus, [CreditNote::STATUS_AUTHORIZED])) {
                Log::info('Nota de crédito aprobada por SRI', [
                    'credit_note_id' => $creditNote->id,
                    'status' => $newStatus,
                ]);

                // Disparar evento para generar PDF y enviar email automáticamente
                event(new CreditNoteApproved($creditNote, $response));
            }

        } else {
            // ✅ Nota de crédito rechazada o con errores
            $errorMessage = $response['message'] ?? 'Error desconocido del SRI';
            $creditNote->markAsFailed($errorMessage);

            Log::warning('Nota de crédito rechazada por la API SRI', [
                'credit_note_id' => $creditNote->id,
                'error_message' => $errorMessage,
                'response' => $response,
            ]);

            throw new Exception("Nota de crédito rechazada por la API SRI: {$errorMessage}");
        }
    }

    /**
     * ✅ Consulta el estado de una nota de crédito en el SRI usando su clave de acceso
     */
    public function checkCreditNoteStatus(string $claveAcceso): array
    {
        Log::info('Consultando estado de nota de crédito en SRI', [
            'clave_acceso' => $claveAcceso,
        ]);

        try {
            $token = $this->authenticate();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->get($this->apiUrl.'/api/credit-notes/status/'.$claveAcceso);

            if (!$response->successful()) {
                throw new Exception(
                    "Error HTTP {$response->status()} consultando estado: ".$response->body()
                );
            }

            $responseData = $response->json();

            Log::info('Estado de nota de crédito consultado', [
                'clave_acceso' => $claveAcceso,
                'response' => $responseData,
            ]);

            return $responseData;

        } catch (Exception $e) {
            Log::error('Error consultando estado de nota de crédito', [
                'clave_acceso' => $claveAcceso,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * ✅ Reintenta el envío de una nota de crédito existente al SRI
     */
    public function retrySendCreditNote(CreditNote $creditNote): array
    {
        Log::info('Sistema inteligente de retry para nota de crédito SRI', [
            'credit_note_id' => $creditNote->id,
            'credit_note_number' => $creditNote->credit_note_number,
            'has_sri_access_key' => !empty($creditNote->sri_access_key),
            'current_status' => $creditNote->status,
            'current_retry_count' => $creditNote->retry_count,
        ]);

        try {
            // ✅ Autenticar y obtener token JWT
            $token = $this->authenticate();

            // ✅ Si la nota ya tiene clave de acceso, consultar estado actual
            if ($creditNote->sri_access_key) {
                Log::info('CASO A: Nota de crédito ya enviada previamente - consultando estado actual', [
                    'credit_note_id' => $creditNote->id,
                    'sri_access_key' => $creditNote->sri_access_key,
                ]);

                $statusResponse = $this->checkCreditNoteStatus($creditNote->sri_access_key);

                // Procesar respuesta de consulta de estado
                $this->processCreditNoteStatusResponse($creditNote, $statusResponse);

                return $statusResponse;
            }

            // ✅ CASO B: Nota sin sri_access_key - puede estar en estado intermedio
            Log::info('Nota de crédito sin sri_access_key - verificando si está en estado intermedio', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'status' => $creditNote->status,
                'strategy' => 'Usar sendCreditNote() que maneja estados intermedios inteligentemente',
            ]);

            // ✅ Usar sendCreditNote() que ahora tiene lógica de estados inteligente
            return $this->sendCreditNote($creditNote);

        } catch (Exception $e) {
            Log::error('Error en sistema inteligente de retry para nota de crédito', [
                'credit_note_id' => $creditNote->id,
                'has_sri_access_key' => !empty($creditNote->sri_access_key),
                'retry_count' => $creditNote->retry_count,
                'error' => $e->getMessage(),
            ]);

            // ✅ Marcar como fallida usando método existente
            $creditNote->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * ✅ Reintenta el envío de una nota de crédito (para el sistema de retry)
     */
    public function retryCreditNote(CreditNote $creditNote): array
    {
        Log::info('Reintentando envío de nota de crédito al SRI', [
            'credit_note_id' => $creditNote->id,
            'current_retry_count' => $creditNote->retry_count,
        ]);

        // ✅ Verificar que la nota puede reintentarse
        if (!$creditNote->canRetry()) {
            throw new Exception("La nota de crédito {$creditNote->id} no puede reintentarse (max reintentos alcanzado o estado incorrecto)");
        }

        // ✅ Incrementar contador de reintentos ANTES del intento
        $creditNote->incrementRetryCount();

        try {
            // ✅ Actualizar estado a "enviando"
            $creditNote->update(['status' => CreditNote::STATUS_SENT_TO_SRI]);

            // ✅ Intentar envío usando método específico para retry
            $response = $this->retrySendCreditNote($creditNote);

            Log::info('Reintento de nota de crédito exitoso', [
                'credit_note_id' => $creditNote->id,
                'retry_count' => $creditNote->retry_count,
            ]);

            return $response;

        } catch (Exception $e) {
            Log::error('Fallo en reintento de nota de crédito', [
                'credit_note_id' => $creditNote->id,
                'retry_count' => $creditNote->retry_count,
                'error' => $e->getMessage(),
            ]);

            // ✅ Si alcanzó el máximo de reintentos, marcar como definitivamente fallida
            if (!$creditNote->canRetry()) {
                $creditNote->markAsDefinitivelyFailed();

                Log::critical('Nota de crédito marcada como definitivamente fallida', [
                    'credit_note_id' => $creditNote->id,
                    'final_retry_count' => $creditNote->retry_count,
                ]);
            }

            throw $e;
        }
    }

    /**
     * ✅ Procesa respuesta de consulta de estado SRI para notas de crédito
     */
    private function processCreditNoteStatusResponse(CreditNote $creditNote, array $response): void
    {
        // Lógica para procesar consulta de estado y actualizar nota si es necesario
        if (isset($response['estado'])) {
            $estado = $response['estado'];

            $newStatus = match ($estado) {
                'AUTORIZADO' => CreditNote::STATUS_AUTHORIZED,
                'RECHAZADO' => CreditNote::STATUS_REJECTED,
                'PENDIENTE' => CreditNote::STATUS_PENDING,
                'PROCESANDO' => CreditNote::STATUS_PROCESSING,
                'RECIBIDA' => CreditNote::STATUS_RECEIVED,
                'NO_AUTORIZADO' => CreditNote::STATUS_NOT_AUTHORIZED,
                'DEVUELTA' => CreditNote::STATUS_RETURNED,
                'ERROR', 'ERROR_SRI' => CreditNote::STATUS_SRI_ERROR,
                default => $creditNote->status // Mantener estado actual si no cambia
            };

            if ($newStatus !== $creditNote->status) {
                $updateData = ['status' => $newStatus];

                // Si tiene información adicional del SRI, actualizarla también
                if (isset($response['numeroAutorizacion'])) {
                    $updateData['sri_authorization_number'] = $response['numeroAutorizacion'];
                }

                if (isset($response['fechaAutorizacion'])) {
                    $updateData['sri_response'] = json_encode([
                        'response' => $response,
                        'updated_at' => now()->toISOString(),
                        'source' => 'status_check_retry',
                    ]);
                }

                $creditNote->update($updateData);

                Log::info('Estado de nota de crédito actualizado desde consulta SRI', [
                    'credit_note_id' => $creditNote->id,
                    'old_status' => $creditNote->status,
                    'new_status' => $newStatus,
                    'sri_estado' => $estado,
                    'updated_fields' => array_keys($updateData),
                ]);

                // Si la nota fue autorizada, disparar evento (si existe)
                if (in_array($newStatus, [CreditNote::STATUS_AUTHORIZED])) {
                    Log::info('Nota de crédito autorizada durante retry', [
                        'credit_note_id' => $creditNote->id,
                        'status' => $newStatus,
                    ]);

                    // Disparar evento para generar PDF y enviar email automáticamente
                    event(new CreditNoteApproved($creditNote, $response));
                }
            } else {
                Log::info('Estado de nota de crédito sin cambios después de consulta SRI', [
                    'credit_note_id' => $creditNote->id,
                    'current_status' => $creditNote->status,
                    'sri_estado' => $estado,
                ]);
            }
        } else {
            Log::warning('Respuesta de consulta SRI sin campo estado para nota de crédito', [
                'credit_note_id' => $creditNote->id,
                'response' => $response,
            ]);
        }
    }
}
