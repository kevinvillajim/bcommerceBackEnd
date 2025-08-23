<?php

namespace App\Infrastructure\External\SRI;

use App\Domain\Entities\InvoiceEntity;
use App\Domain\Entities\SriTransactionEntity;
use App\Domain\Interfaces\SriServiceInterface;
use App\Domain\Repositories\InvoiceRepositoryInterface;
use GuzzleHttp\Client;

class SriService implements SriServiceInterface
{
    private $client;

    private $baseUrl;

    private $apiKey;

    private $invoiceRepository;

    public function __construct(InvoiceRepositoryInterface $invoiceRepository)
    {
        $this->invoiceRepository = $invoiceRepository;
        $this->baseUrl = config('services.sri.url');
        $this->apiKey = config('services.sri.api_key');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$this->apiKey,
            ],
        ]);
    }

    public function generateInvoice(InvoiceEntity $invoice): array
    {
        // Generar la clave de acceso si no existe
        if (! $invoice->sriAccessKey) {
            $invoice->sriAccessKey = $this->generateAccessKey($invoice);
        }

        // Preparar los datos para el SRI
        $requestData = $this->prepareInvoiceData($invoice);

        try {
            // Registrar la transacción antes de enviar
            $sriTransaction = new SriTransactionEntity(
                invoiceId: $invoice->id,
                type: 'EMISSION',
                requestData: $requestData,
            );

            // Enviar la solicitud al SRI
            $response = $this->client->post('/api/v1/comprobantes-electronicos/emitir', [
                'json' => $requestData,
            ]);

            $responseData = json_decode($response->getBody(), true);

            // Actualizar la transacción con la respuesta
            $sriTransaction->responseData = $responseData;
            $sriTransaction->success = $responseData['success'] ?? false;
            $sriTransaction->errorMessage = $responseData['mensaje'] ?? null;

            // Registrar la transacción
            $this->invoiceRepository->recordSriTransaction($sriTransaction);

            // Si fue exitoso, actualizar los datos de la factura
            if ($sriTransaction->success) {
                $invoice->status = 'ISSUED';
                $invoice->sriAuthorizationNumber = $responseData['numeroAutorizacion'] ?? null;
                $invoice->sriResponse = $responseData;

                // Actualizar la factura en la base de datos
                $this->invoiceRepository->updateInvoice($invoice);
            }

            return $responseData;
        } catch (\Exception $e) {
            // Registrar el error
            $sriTransaction = new SriTransactionEntity(
                invoiceId: $invoice->id,
                type: 'EMISSION',
                requestData: $requestData,
                success: false,
                errorMessage: $e->getMessage()
            );

            $this->invoiceRepository->recordSriTransaction($sriTransaction);

            // Actualizar el estado de la factura a ERROR
            $invoice->status = 'ERROR';
            $this->invoiceRepository->updateInvoice($invoice);

            return [
                'success' => false,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    public function cancelInvoice(InvoiceEntity $invoice, string $reason): array
    {
        // Preparar los datos para la anulación
        $requestData = [
            'claveAcceso' => $invoice->sriAccessKey,
            'motivo' => $reason,
        ];

        try {
            // Registrar la transacción antes de enviar
            $sriTransaction = new SriTransactionEntity(
                invoiceId: $invoice->id,
                type: 'CANCELLATION',
                requestData: $requestData,
            );

            // Enviar la solicitud al SRI
            $response = $this->client->post('/api/v1/comprobantes-electronicos/anular', [
                'json' => $requestData,
            ]);

            $responseData = json_decode($response->getBody(), true);

            // Actualizar la transacción con la respuesta
            $sriTransaction->responseData = $responseData;
            $sriTransaction->success = $responseData['success'] ?? false;
            $sriTransaction->errorMessage = $responseData['mensaje'] ?? null;

            // Registrar la transacción
            $this->invoiceRepository->recordSriTransaction($sriTransaction);

            // Si fue exitoso, actualizar los datos de la factura
            if ($sriTransaction->success) {
                $invoice->cancel($reason);
                $this->invoiceRepository->updateInvoice($invoice);
            }

            return $responseData;
        } catch (\Exception $e) {
            // Registrar el error
            $sriTransaction = new SriTransactionEntity(
                invoiceId: $invoice->id,
                type: 'CANCELLATION',
                requestData: $requestData,
                success: false,
                errorMessage: $e->getMessage()
            );

            $this->invoiceRepository->recordSriTransaction($sriTransaction);

            return [
                'success' => false,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    public function validateAccessKey(string $accessKey): array
    {
        $requestData = [
            'claveAcceso' => $accessKey,
        ];

        try {
            $response = $this->client->post('/api/v1/comprobantes-electronicos/validar', [
                'json' => $requestData,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    public function queryInvoiceStatus(string $accessKey): array
    {
        $requestData = [
            'claveAcceso' => $accessKey,
        ];

        try {
            $response = $this->client->post('/api/v1/comprobantes-electronicos/consultar', [
                'json' => $requestData,
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'mensaje' => $e->getMessage(),
            ];
        }
    }

    /**
     * Genera una clave de acceso para una factura según el formato del SRI
     */
    private function generateAccessKey(InvoiceEntity $invoice): string
    {
        $date = $invoice->issueDate->format('dmY');
        $invoiceType = '01'; // Factura
        $ruc = config('services.sri.ruc');
        $environment = config('services.sri.environment', '1'); // 1: Pruebas, 2: Producción
        $series = config('services.sri.series', '001001');
        $sequential = str_pad($invoice->id, 9, '0', STR_PAD_LEFT);
        $numericCode = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $emissionType = '1'; // Emisión normal

        $key = $date.$invoiceType.$ruc.$environment.$series.$sequential.$numericCode.$emissionType;

        // Calcular el dígito verificador con el algoritmo Módulo 11
        $factors = [2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3];
        $sum = 0;

        for ($i = 0; $i < strlen($key); $i++) {
            $sum += (int) $key[$i] * $factors[$i];
        }

        $mod = $sum % 11;
        $verifier = $mod == 0 ? 0 : 11 - $mod;

        return $key.$verifier;
    }

    /**
     * Prepara los datos de la factura para el formato requerido por el SRI
     */
    private function prepareInvoiceData(InvoiceEntity $invoice): array
    {
        // Obtener información del vendedor
        $sellerInfo = $this->getSellerInfo($invoice->sellerId);

        // Obtener información del cliente/comprador
        $buyerInfo = $this->getBuyerInfo($invoice->userId);

        $items = [];
        foreach ($invoice->items as $item) {
            $items[] = [
                'codigoPrincipal' => $item->productId,
                'codigoAuxiliar' => $item->sriProductCode ?? 'SRV',
                'descripcion' => $item->description,
                'cantidad' => $item->quantity,
                'precioUnitario' => number_format($item->unitPrice, 2, '.', ''),
                'descuento' => number_format($item->discount, 2, '.', ''),
                'precioTotalSinImpuesto' => number_format($item->quantity * $item->unitPrice - $item->discount, 2, '.', ''),
                'impuestos' => [
                    [
                        'codigo' => '2', // IVA
                        'codigoPorcentaje' => '2', // IVA 12%
                        'tarifa' => $item->taxRate,
                        'baseImponible' => number_format($item->quantity * $item->unitPrice - $item->discount, 2, '.', ''),
                        'valor' => number_format($item->taxAmount, 2, '.', ''),
                    ],
                ],
            ];
        }

        $totals = [
            'totalSinImpuestos' => number_format($invoice->subtotal, 2, '.', ''),
            'totalDescuento' => '0.00',
            'totalConImpuestos' => [
                [
                    'codigo' => '2', // IVA
                    'codigoPorcentaje' => '2', // IVA 12%
                    'baseImponible' => number_format($invoice->subtotal, 2, '.', ''),
                    'valor' => number_format($invoice->taxAmount, 2, '.', ''),
                ],
            ],
            'importeTotal' => number_format($invoice->totalAmount, 2, '.', ''),
            'moneda' => 'USD',
        ];

        // Formatear la fecha según requisitos del SRI (dd/MM/yyyy)
        $formattedDate = $invoice->issueDate->format('d/m/Y');

        return [
            'claveAcceso' => $invoice->sriAccessKey,
            'ambiente' => config('services.sri.environment', '1'), // 1: Pruebas, 2: Producción
            'tipoEmision' => '1', // Emisión normal
            'razonSocial' => $sellerInfo['razonSocial'],
            'nombreComercial' => $sellerInfo['nombreComercial'],
            'ruc' => $sellerInfo['ruc'],
            'codDoc' => '01', // Factura
            'estab' => substr(config('services.sri.series', '001001'), 0, 3),
            'ptoEmi' => substr(config('services.sri.series', '001001'), 3, 3),
            'secuencial' => str_pad($invoice->id, 9, '0', STR_PAD_LEFT),
            'dirMatriz' => $sellerInfo['direccionMatriz'],
            'fechaEmision' => $formattedDate,
            'dirEstablecimiento' => $sellerInfo['direccionEstablecimiento'],
            'contribuyenteEspecial' => $sellerInfo['contribuyenteEspecial'] ?? 'NO',
            'obligadoContabilidad' => $sellerInfo['obligadoContabilidad'] ?? 'SI',
            'tipoIdentificacionComprador' => $buyerInfo['tipoIdentificacion'],
            'razonSocialComprador' => $buyerInfo['razonSocial'],
            'identificacionComprador' => $buyerInfo['identificacion'],
            'direccionComprador' => $buyerInfo['direccion'],
            'totalSinImpuestos' => number_format($invoice->subtotal, 2, '.', ''),
            'totalDescuento' => '0.00',
            'totalConImpuestos' => $totals['totalConImpuestos'],
            'propina' => '0.00',
            'importeTotal' => number_format($invoice->totalAmount, 2, '.', ''),
            'moneda' => 'USD',
            'detalles' => $items,
            'infoAdicional' => [
                [
                    'nombre' => 'Email',
                    'valor' => $buyerInfo['email'],
                ],
                [
                    'nombre' => 'Teléfono',
                    'valor' => $buyerInfo['telefono'] ?? 'N/A',
                ],
                [
                    'nombre' => 'Dirección',
                    'valor' => $buyerInfo['direccion'],
                ],
            ],
        ];
    }

    /**
     * Obtiene información del vendedor para la factura
     */
    private function getSellerInfo(int $sellerId): array
    {
        // Obtenemos el vendedor (aquí deberías adaptar esto para obtener la información real)
        // Esto es solo un ejemplo estático:
        return [
            'razonSocial' => config('services.sri.razon_social', 'EMPRESA DEMO S.A.'),
            'nombreComercial' => config('services.sri.nombre_comercial', 'EMPRESA DEMO'),
            'ruc' => config('services.sri.ruc', '0999999999001'),
            'direccionMatriz' => config('services.sri.direccion_matriz', 'Av. Principal 123'),
            'direccionEstablecimiento' => config('services.sri.direccion_establecimiento', 'Av. Principal 123'),
            'contribuyenteEspecial' => config('services.sri.contribuyente_especial', 'NO'),
            'obligadoContabilidad' => config('services.sri.obligado_contabilidad', 'SI'),
        ];
    }

    /**
     * Obtiene información del comprador para la factura
     */
    private function getBuyerInfo(int $userId): array
    {
        // En un caso real, deberías obtener esta información de la base de datos
        // Aquí usamos información ficticia:
        $user = \App\Models\User::find($userId);

        if (! $user) {
            return [
                'tipoIdentificacion' => '05', // Cédula
                'razonSocial' => 'CONSUMIDOR FINAL',
                'identificacion' => '9999999999',
                'direccion' => 'N/A',
                'email' => 'consumer@example.com',
                'telefono' => 'N/A',
            ];
        }

        // Determinar tipo de identificación (cédula, RUC, pasaporte)
        $tipoIdentificacion = strlen($user->dni ?? '') == 13 ? '04' : '05'; // 04: RUC, 05: Cédula

        return [
            'tipoIdentificacion' => $tipoIdentificacion,
            'razonSocial' => $user->name.' '.($user->last_name ?? ''),
            'identificacion' => $user->dni ?? '9999999999',
            'direccion' => $user->address ?? 'N/A',
            'email' => $user->email,
            'telefono' => $user->phone ?? 'N/A',
        ];
    }
}
