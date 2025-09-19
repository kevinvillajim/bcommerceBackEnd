<?php

namespace App\UseCases\Accounting;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceFromOrderUseCase
{
    /**
     * Genera una factura automáticamente cuando se crea una orden
     */
    public function execute(Order $order): Invoice
    {
        Log::info('Iniciando generación de factura para orden', ['order_id' => $order->id]);

        // Cargar relación user para obtener email real
        $order->load('user');

        try {
            return DB::transaction(function () use ($order) {
                // ✅ Validación inicial: orden debe tener items
                if ($order->items()->count() === 0) {
                    throw new Exception("No se puede generar factura: la orden {$order->id} está vacía");
                }

                // ✅ Validar que la orden tenga totales válidos
                if ($order->total <= 0) {
                    throw new Exception("No se puede generar factura: el total de la orden {$order->id} debe ser mayor a 0");
                }

                // ✅ Validar que la orden tenga subtotal válido (subtotal SIN IVA)
                if ($order->subtotal_products <= 0) {
                    throw new Exception("No se puede generar factura: el subtotal de la orden {$order->id} debe ser mayor a 0");
                }

                // ✅ Extraer datos del cliente desde shipping_data o billing_data
                $customerData = $this->extractCustomerData($order);

                // ✅ Generar número secuencial de factura con validación de duplicados
                $invoiceNumber = $this->generateInvoiceNumber();

                // ✅ VALIDACIÓN ADICIONAL: Verificar que el número no esté en uso
                $maxRetries = 3;
                for ($retry = 0; $retry < $maxRetries; $retry++) {
                    if (! Invoice::where('invoice_number', $invoiceNumber)->exists()) {
                        break; // Número válido, continuar
                    }

                    Log::warning('Número de factura duplicado detectado, regenerando', [
                        'invoice_number' => $invoiceNumber,
                        'retry' => $retry + 1,
                    ]);

                    $invoiceNumber = $this->generateInvoiceNumber();

                    if ($retry === $maxRetries - 1) {
                        throw new Exception("No se pudo generar un número de factura único después de {$maxRetries} intentos");
                    }
                }

                // ✅ Crear la factura
                $invoice = Invoice::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'invoice_number' => $invoiceNumber,
                    'issue_date' => now(),

                    // ✅ Totales desde la orden (calculados por PricingCalculatorService)
                    'subtotal' => $order->subtotal_products, // Subtotal SIN IVA con descuentos aplicados
                    'tax_amount' => $order->iva_amount, // Monto del IVA 15%
                    'total_amount' => $order->total, // Total final CON IVA
                    'currency' => 'DOLAR',

                    // ✅ Datos del cliente (extraídos dinámicamente)
                    'customer_identification' => $customerData['identification'],
                    'customer_identification_type' => $customerData['identification_type'],
                    'customer_name' => $customerData['name'],
                    'customer_email' => $customerData['email'],
                    'customer_address' => $customerData['address'],
                    'customer_phone' => $customerData['phone'],

                    // ✅ Estado inicial
                    'status' => Invoice::STATUS_DRAFT,
                    'retry_count' => 0,
                    'created_via' => 'checkout',
                ]);

                // ✅ Crear items de la factura con validaciones robustas
                $this->createInvoiceItems($invoice, $order);

                Log::info('Factura generada exitosamente', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'order_id' => $order->id,
                    'total_amount' => $invoice->total_amount,
                ]);

                return $invoice;
            });
        } catch (Exception $e) {
            Log::error('Error generando factura', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * ✅ Extrae datos del cliente desde shipping_data o billing_data
     */
    private function extractCustomerData(Order $order): array
    {
        $shippingData = $order->shipping_data;
        $billingData = $order->billing_data;
        $userData = $order->user;

        // ✅ Si use_same_address es false, usar billing_data
        // ✅ Si es true o no está definido, usar shipping_data
        $useSameAddress = $shippingData['use_same_address'] ?? true;

        // 🧾 LOG: Extrayendo datos de cliente para SRI
        Log::info('🧾 SRI: Extrayendo datos de cliente', [
            'order_id' => $order->id,
            'has_billing_data' => ! empty($order->billing_data),
            'has_shipping_data' => ! empty($order->shipping_data),
            'use_same_address' => $useSameAddress,
            'source_data_from' => $useSameAddress ? 'shipping' : 'billing',
        ]);

        $sourceData = $useSameAddress ? $shippingData : $billingData;

        // ✅ Validar que tengamos identificación (crítico para SRI)
        if (empty($sourceData['identification'])) {
            throw new Exception('No se puede generar factura: falta la cédula/RUC del cliente');
        }

        $identification = $sourceData['identification'];

        // 🧾 LOG: Confirmación de identificación extraída
        Log::info('🧾 SRI: Identificación extraída exitosamente', [
            'order_id' => $order->id,
            'extracted_identification' => $identification,
            'source_data_from' => $useSameAddress ? 'shipping' : 'billing',
            'customer_name' => $sourceData['name'] ?? 'NO_NAME',
            'customer_email' => $sourceData['email'] ?? 'NO_EMAIL',
        ]);

        // ✅ Validar formato de identificación
        if (! preg_match('/^\d{10}(\d{3})?$/', $identification)) {
            throw new Exception("Formato de cédula/RUC inválido: {$identification}");
        }

        // ✅ Determinar tipo de identificación dinámicamente
        $identificationType = $this->determineIdentificationType($identification);

        // ✅ Construir dirección completa (sin postal_code si está vacío)
        $addressParts = [];

        if (! empty($sourceData['address'])) {
            $addressParts[] = $sourceData['address'];
        }

        if (! empty($sourceData['city'])) {
            $addressParts[] = $sourceData['city'];
        }

        if (! empty($sourceData['state'])) {
            $addressParts[] = $sourceData['state'];
        }

        // ✅ Solo agregar postal_code si NO está vacío
        if (! empty($sourceData['postal_code'])) {
            $addressParts[] = $sourceData['postal_code'];
        }

        if (! empty($sourceData['country'])) {
            $addressParts[] = $sourceData['country'];
        } else {
            $addressParts[] = 'Ecuador'; // Default
        }

        $address = implode(', ', $addressParts);

        // ✅ Si no hay partes de dirección, usar texto por defecto
        if (empty($addressParts)) {
            $address = 'Sin dirección especificada';
        }

        // ✅ Construir nombre completo: usar el campo 'name' que envía el frontend
        $fullName = $sourceData['name'] ?? '';

        // ✅ Si no hay nombre en shipping_data, usar el nombre del usuario como fallback
        if (empty($fullName)) {
            $fullName = $userData->name ?? '';
        }

        // ✅ Email prioritario: del formulario, luego del usuario
        $email = $sourceData['email'] ?? $userData->email ?? '';

        if (empty($email)) {
            throw new Exception('No se puede generar factura: falta el email del cliente');
        }

        return [
            'identification' => $identification,
            'identification_type' => $identificationType,
            'name' => trim($fullName) ?: 'Cliente Sin Nombre',
            'email' => $email,
            'address' => $address,
            'phone' => $sourceData['phone'] ?? 'Sin teléfono',
        ];
    }

    /**
     * ✅ Determina el tipo de identificación según SRI
     */
    private function determineIdentificationType(string $identification): string
    {
        $length = strlen($identification);

        if ($length === 10) {
            return '05'; // Cédula
        } elseif ($length === 13 && substr($identification, -3) === '001') {
            return '04'; // RUC
        }

        return '05'; // Default cédula
    }

    /**
     * ✅ Genera número de factura secuencial de 9 dígitos
     */
    private function generateInvoiceNumber(): string
    {
        // ✅ CORRECCIÓN: Ordenamiento numérico en lugar de lexicográfico
        // Usar CAST para ordenar por valor numérico, no alfabéticamente
        $lastInvoice = Invoice::orderByRaw('CAST(invoice_number AS UNSIGNED) DESC')->first();

        if (! $lastInvoice) {
            return '000000001'; // Primera factura
        }

        // ✅ Incrementar secuencial
        $lastNumber = (int) $lastInvoice->invoice_number;
        $nextNumber = $lastNumber + 1;

        // ✅ Formatear a 9 dígitos con ceros a la izquierda
        return str_pad((string) $nextNumber, 9, '0', STR_PAD_LEFT);
    }

    /**
     * ✅ Crea los items de la factura con validaciones robustas
     */
    private function createInvoiceItems(Invoice $invoice, Order $order): void
    {
        $orderItems = $order->items()->with('product')->get();

        if ($orderItems->isEmpty()) {
            throw new Exception('No se pueden crear items de factura: la orden no tiene productos');
        }

        foreach ($orderItems as $orderItem) {
            // ✅ Validaciones críticas por item
            if ($orderItem->quantity <= 0) {
                throw new Exception("Item inválido: la cantidad debe ser mayor a 0 (producto ID: {$orderItem->product_id})");
            }

            if ($orderItem->price <= 0) {
                throw new Exception("Item inválido: el precio debe ser mayor a 0 (producto ID: {$orderItem->product_id})");
            }

            if ($orderItem->subtotal <= 0) {
                throw new Exception("Item inválido: el subtotal debe ser mayor a 0 (producto ID: {$orderItem->product_id})");
            }

            $product = $orderItem->product;

            if (! $product) {
                throw new Exception("Producto no encontrado para item de orden (producto ID: {$orderItem->product_id})");
            }

            // ✅ Validar que el producto tenga slug (crítico para SRI)
            if (empty($product->slug)) {
                throw new Exception("El producto '{$product->name}' no tiene slug definido (requerido para SRI)");
            }

            // ✅ Calcular IVA (15%)
            $taxRate = 15.00;
            $taxAmount = ($orderItem->subtotal * $taxRate) / 100;

            // ✅ Crear item de factura
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'product_code' => $product->slug, // ✅ Usar slug como código único
                'product_name' => $product->name,
                'quantity' => $orderItem->quantity,
                'unit_price' => $orderItem->price,
                'discount' => 0.00, // ✅ Siempre 0 por ahora
                'subtotal' => $orderItem->subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
            ]);
        }

        Log::info('Items de factura creados', [
            'invoice_id' => $invoice->id,
            'items_count' => $orderItems->count(),
        ]);
    }
}
