<?php

namespace App\Services;

use App\Models\Invoice;
use Exception;

class SriDataMapperService
{
    /**
     * ✅ Convierte una factura en el formato exacto requerido por la API del SRI
     */
    public function mapInvoiceToSriFormat(Invoice $invoice): array
    {
        // ✅ Validar que la factura tenga items
        $invoiceItems = $invoice->items()->with('product')->get();

        if ($invoiceItems->isEmpty()) {
            throw new Exception("No se puede mapear factura sin items para SRI (Invoice ID: {$invoice->id})");
        }

        // ✅ Mapear items con formato SRI exacto
        $detalles = [];

        foreach ($invoiceItems as $item) {
            if (! $item->product) {
                throw new Exception("Item de factura sin producto asociado (Item ID: {$item->id})");
            }

            $detalles[] = [
                'codigoPrincipal' => $item->product_code, // slug del producto
                'descripcion' => $item->product_name,
                'cantidad' => (string) $item->quantity,
                'precioUnitario' => number_format($item->subtotal / $item->quantity, 2, '.', ''),
                'descuento' => '0.00', // Siempre 0 por ahora
                'precioTotalSinImpuesto' => number_format($item->subtotal, 2, '.', ''),
                'impuestos' => [
                    [
                        'codigo' => '4', // ✅ Código IVA 15%
                        'codigoPorcentaje' => '3', // ✅ Código para 15%
                        'tarifa' => '15.00',
                        'baseImponible' => number_format($item->subtotal, 2, '.', ''),
                        'valor' => number_format($item->tax_amount, 2, '.', ''),
                    ],
                ],
            ];
        }

        // ✅ Calcular totales
        $totalSinImpuestos = number_format($invoice->subtotal, 2, '.', '');
        $totalIva = number_format($invoice->tax_amount, 2, '.', '');
        $importeTotal = number_format($invoice->total_amount, 2, '.', '');

        // ✅ Construir estructura completa para API SRI
        return [
            'infoTributaria' => [
                // ✅ La API maneja automáticamente la info de la empresa vía autenticación
                // No enviamos datos de la empresa aquí
            ],
            'infoFactura' => [
                'fechaEmision' => $invoice->issue_date->format('d/m/Y'), // Formato DD/MM/YYYY
                'obligadoContabilidad' => 'SI', // Empresas siempre obligadas
                'tipoIdentificacionComprador' => $invoice->customer_identification_type, // "05" o "04"
                'identificacionComprador' => $invoice->customer_identification,
                'razonSocialComprador' => $invoice->customer_name,
                'direccionComprador' => $invoice->customer_address,
                'totalSinImpuestos' => $totalSinImpuestos,
                'totalDescuento' => '0.00', // Siempre 0 por ahora
                'totalConImpuestos' => [
                    [
                        'codigo' => '4', // IVA
                        'codigoPorcentaje' => '3', // 15%
                        'baseImponible' => $totalSinImpuestos,
                        'valor' => $totalIva,
                    ],
                ],
                'propina' => '0.00',
                'importeTotal' => $importeTotal,
                'moneda' => 'DOLAR', // Ecuador usa dólares
            ],
            'detalles' => $detalles,
        ];
    }

    /**
     * ✅ Genera la clave de acceso para el SRI
     * Formato: DDMMYYYYTTTTEEEEEEENNNNNNNNCV
     */
    public function generateAccessKey(Invoice $invoice): string
    {
        $fecha = $invoice->issue_date->format('dmY'); // DDMMYYYY
        $tipoComprobante = '01'; // Factura

        // ✅ Obtener RUC de configuración (debe venir del .env)
        $ruc = config('sri.company_ruc');
        if (! $ruc) {
            throw new Exception('RUC de la empresa no configurado en SRI_COMPANY_RUC');
        }

        $ambiente = config('sri.environment', '1'); // 1=Pruebas, 2=Producción
        $serie = '001001'; // Establecimiento + Punto de emisión
        $numeroComprobante = $invoice->invoice_number; // 9 dígitos
        $codigoNumerico = '12345678'; // Número aleatorio de 8 dígitos

        // ✅ Construir clave sin dígito verificador
        $claveSinDigito = $fecha.$tipoComprobante.$ruc.$ambiente.$serie.$numeroComprobante.$codigoNumerico;

        // ✅ Calcular dígito verificador
        $digitoVerificador = $this->calculateVerificationDigit($claveSinDigito);

        return $claveSinDigito.$digitoVerificador;
    }

    /**
     * ✅ Calcula el dígito verificador según algoritmo del SRI
     */
    private function calculateVerificationDigit(string $clave): string
    {
        $multiplicadores = [2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7, 2, 3, 4, 5, 6, 7];

        $suma = 0;
        $longitud = strlen($clave);

        for ($i = 0; $i < $longitud; $i++) {
            $suma += (int) $clave[$i] * $multiplicadores[$i];
        }

        $residuo = $suma % 11;

        if ($residuo == 0) {
            return '0';
        } elseif ($residuo == 1) {
            return '1';
        } else {
            return (string) (11 - $residuo);
        }
    }

    /**
     * ✅ Valida que todos los campos requeridos estén presentes
     */
    public function validateInvoiceForSri(Invoice $invoice): array
    {
        $errors = [];

        // ✅ Validaciones básicas
        if (empty($invoice->customer_identification)) {
            $errors[] = 'Falta identificación del cliente';
        }

        if (empty($invoice->customer_name)) {
            $errors[] = 'Falta nombre del cliente';
        }

        if (empty($invoice->customer_address)) {
            $errors[] = 'Falta dirección del cliente';
        }

        if ($invoice->subtotal <= 0) {
            $errors[] = 'Subtotal debe ser mayor a 0';
        }

        if ($invoice->total_amount <= 0) {
            $errors[] = 'Total debe ser mayor a 0';
        }

        // ✅ Validar formato de identificación
        $identification = $invoice->customer_identification;
        if (! preg_match('/^\d{10}(\d{3})?$/', $identification)) {
            $errors[] = 'Formato de identificación inválido';
        }

        // ✅ Validar items
        $items = $invoice->items()->get();
        if ($items->isEmpty()) {
            $errors[] = 'La factura debe tener al menos un item';
        }

        foreach ($items as $index => $item) {
            if ($item->quantity <= 0) {
                $errors[] = "Item #{$index}: cantidad debe ser mayor a 0";
            }

            if (($item->subtotal / $item->quantity) <= 0) {
                $errors[] = "Item #{$index}: precio unitario debe ser mayor a 0";
            }

            if (empty($item->product_code)) {
                $errors[] = "Item #{$index}: falta código de producto";
            }

            if (empty($item->product_name)) {
                $errors[] = "Item #{$index}: falta nombre de producto";
            }
        }

        return $errors;
    }

    /**
     * ✅ Construye el payload completo para enviar al API del SRI
     */
    public function buildSriPayload(Invoice $invoice): array
    {
        // ✅ Validar antes de mapear
        $validationErrors = $this->validateInvoiceForSri($invoice);

        if (! empty($validationErrors)) {
            throw new Exception('Errores de validación para SRI: '.implode(', ', $validationErrors));
        }

        // ✅ Obtener datos del cliente
        $customerData = $this->extractCustomerFromInvoice($invoice);

        // ✅ Mapear items de la factura
        $invoiceItems = $invoice->items()->with('product')->get();
        $detalles = [];

        foreach ($invoiceItems as $item) {
            $detalles[] = [
                'codigoPrincipal' => $item->product_code, // slug del producto
                'descripcion' => $item->product_name,
                'cantidad' => (int) $item->quantity,
                'precioUnitario' => (float) ($item->subtotal / $item->quantity),
                'descuento' => 0, // Siempre 0 por ahora
                'codigoIva' => '4', // ✅ Código correcto para 15% IVA Ecuador 2025
            ];
        }

        // ✅ Formato exacto según CreateInvoiceRequest de la API SRI
        return [
            'secuencial' => str_pad($invoice->invoice_number, 9, '0', STR_PAD_LEFT), // 9 dígitos
            'fechaEmision' => $invoice->issue_date->format('Y-m-d'), // YYYY-MM-DD
            'comprador' => [
                'tipoIdentificacion' => $customerData['identification_type'], // 05 o 04
                'identificacion' => $customerData['identification'],
                'razonSocial' => $customerData['name'],
                'direccion' => $customerData['address'] ?? '',
                'telefono' => $customerData['phone'] ?? '',
                'email' => $customerData['email'] ?? '',
            ],
            'detalles' => $detalles,
            'informacionAdicional' => (object) [
                'Email' => $customerData['email'] ?? '',
                'Direccion' => $customerData['address'] ?? '',
            ],
        ];
    }

    /**
     * ✅ Extrae datos del cliente desde la factura
     */
    private function extractCustomerFromInvoice(Invoice $invoice): array
    {
        return [
            'identification' => $invoice->customer_identification,
            'identification_type' => $invoice->customer_identification_type, // Ya detecta 05/04
            'name' => $invoice->customer_name,
            'email' => $invoice->customer_email,
            'address' => $invoice->customer_address,
            'phone' => $invoice->customer_phone,
        ];
    }
}
