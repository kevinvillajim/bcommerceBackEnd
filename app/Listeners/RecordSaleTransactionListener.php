<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\AccountingAccount;
use App\Models\AccountingTransaction;
use App\Models\AccountingEntry;
use App\Models\Order;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RecordSaleTransactionListener
{
    /**
     * ✅ Maneja el evento OrderCreated para crear automáticamente transacciones contables
     */
    public function handle(OrderCreated $event): void
    {
        Log::info('🧮 ACCOUNTING: RecordSaleTransactionListener iniciado', [
            'order_id' => $event->orderId,
            'user_id' => $event->userId,
            'seller_id' => $event->sellerId,
            'order_data' => $event->orderData
        ]);

        try {
            // Usar transacción de base de datos para atomicidad
            DB::transaction(function () use ($event) {
                $this->createSaleAccountingTransaction($event);
            });

            Log::info('✅ ACCOUNTING: Transacción contable creada exitosamente', [
                'order_id' => $event->orderId
            ]);

        } catch (Exception $e) {
            Log::error('❌ ACCOUNTING: Error creando transacción contable', [
                'order_id' => $event->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // No lanzar la excepción para no afectar el flujo principal
            // El error ya queda registrado en los logs para revisión
        }
    }

    /**
     * ✅ CORREGIDO: Crea la transacción contable usando datos del evento y campos reales de Order
     */
    private function createSaleAccountingTransaction(OrderCreated $event): void
    {
        // Obtener la orden con datos básicos (sin necesidad de relaciones complejas)
        $order = Order::find($event->orderId);

        if (!$order) {
            throw new Exception("Orden {$event->orderId} no encontrada para contabilización");
        }

        // Verificar si ya existe una transacción contable para esta orden
        $existingTransaction = AccountingTransaction::where('order_id', $event->orderId)->first();
        if ($existingTransaction) {
            Log::info('⚠️ ACCOUNTING: Transacción contable ya existe para esta orden', [
                'order_id' => $event->orderId,
                'transaction_id' => $existingTransaction->id
            ]);
            return;
        }

        // Obtener cuentas contables (crear si no existen)
        $accounts = $this->getOrCreateAccountingAccounts();

        // ✅ CORREGIDO: Usar datos del evento y campos reales de la orden (sin recalcular)
        $totals = $this->extractOrderTotalsFromEventAndDB($event, $order);

        Log::info('💰 ACCOUNTING: Totales extraídos del evento y BD para contabilización', [
            'order_id' => $event->orderId,
            'totals' => $totals,
            'source' => 'OrderCreated event + Order DB fields'
        ]);

        // Crear la transacción contable principal
        $transaction = AccountingTransaction::create([
            'reference_number' => "SALE-{$order->order_number}",
            'transaction_date' => $order->created_at->format('Y-m-d'),
            'description' => "Venta - Orden #{$order->order_number}",
            'type' => 'SALE', // ✅ CORREGIDO: Usar enum correcto
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'is_posted' => true, // Marcar como contabilizada automáticamente
        ]);

        // Crear los asientos contables (sistema de partida doble)
        $this->createAccountingEntries($transaction, $accounts, $totals);

        Log::info('📝 ACCOUNTING: Asientos contables creados', [
            'order_id' => $event->orderId,
            'transaction_id' => $transaction->id,
            'reference_number' => $transaction->reference_number
        ]);
    }

    /**
     * ✅ Obtiene o crea las cuentas contables necesarias
     */
    private function getOrCreateAccountingAccounts(): array
    {
        $accounts = [];

        // Definir cuentas básicas necesarias para ventas
        $accountDefinitions = [
            'cash' => [
                'code' => '1101',
                'name' => 'Efectivo y Equivalentes',
                'type' => 'Activo',
                'description' => 'Dinero en efectivo y cuentas bancarias'
            ],
            'accounts_receivable' => [
                'code' => '1201',
                'name' => 'Cuentas por Cobrar',
                'type' => 'Activo',
                'description' => 'Cuentas pendientes de cobro a clientes'
            ],
            'sales_revenue' => [
                'code' => '4101',
                'name' => 'Ingresos por Ventas',
                'type' => 'Ingreso',
                'description' => 'Ingresos generados por ventas de productos'
            ],
            'vat_payable' => [
                'code' => '2301',
                'name' => 'IVA por Pagar',
                'type' => 'Pasivo',
                'description' => 'Impuesto al Valor Agregado por pagar al SRI'
            ],
            'shipping_revenue' => [
                'code' => '4201',
                'name' => 'Ingresos por Envío',
                'type' => 'Ingreso',
                'description' => 'Ingresos por servicios de envío'
            ]
        ];

        foreach ($accountDefinitions as $key => $definition) {
            $account = AccountingAccount::firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'description' => $definition['description'],
                    'is_active' => true
                ]
            );

            $accounts[$key] = $account;
        }

        return $accounts;
    }

    /**
     * ✅ CORREGIDO: Extrae totales del evento OrderCreated y campos reales de Order (sin recalcular)
     */
    private function extractOrderTotalsFromEventAndDB(OrderCreated $event, Order $order): array
    {
        // ✅ Usar datos del evento cuando estén disponibles
        $finalTotal = $event->orderData['total'] ?? $order->total;

        // ✅ Usar campos reales de la orden (ya calculados correctamente por ProcessCheckoutUseCase)
        $subtotalProducts = $order->subtotal_products ?? 0; // Subtotal con descuentos aplicados
        $ivaAmount = $order->iva_amount ?? 0; // IVA calculado (15%)
        $shippingCost = $order->shipping_cost ?? 0; // Costo de envío
        $totalDiscounts = $order->total_discounts ?? 0; // Total de descuentos aplicados

        // ✅ Calcular base gravable (subtotal + shipping - esto es lo que se grava con IVA)
        $taxableBase = $subtotalProducts + $shippingCost;

        // ✅ Ventas sin IVA = base gravable (lo que va a ingresos por ventas)
        $salesWithoutVat = $taxableBase;

        Log::debug('🧮 ACCOUNTING: Totales extraídos detallados', [
            'order_id' => $order->id,
            'event_total' => $event->orderData['total'] ?? 'N/A',
            'order_total' => $order->total,
            'final_total_used' => $finalTotal,
            'subtotal_products' => $subtotalProducts,
            'iva_amount' => $ivaAmount,
            'shipping_cost' => $shippingCost,
            'total_discounts' => $totalDiscounts,
            'taxable_base' => $taxableBase,
            'sales_without_vat' => $salesWithoutVat,
            'verification' => [
                'subtotal + shipping + iva' => $subtotalProducts + $shippingCost + $ivaAmount,
                'should_equal_final_total' => $finalTotal,
                'difference' => abs(($subtotalProducts + $shippingCost + $ivaAmount) - $finalTotal)
            ]
        ]);

        return [
            'subtotal_products' => $subtotalProducts,
            'total_discounts' => $totalDiscounts,
            'subtotal_after_discounts' => $subtotalProducts, // Ya incluye descuentos
            'shipping_cost' => $shippingCost,
            'taxable_base' => $taxableBase,
            'sales_without_vat' => $salesWithoutVat,
            'vat_amount' => $ivaAmount,
            'final_total' => $finalTotal
        ];
    }

    /**
     * ✅ Crea los asientos contables usando el sistema de partida doble
     */
    private function createAccountingEntries(
        AccountingTransaction $transaction,
        array $accounts,
        array $totals
    ): void {
        $entries = [];

        // DEBE: Efectivo/Cuentas por Cobrar (Total de la venta)
        // Asumimos efectivo para pagos procesados, pero esto se puede mejorar según el método de pago
        $entries[] = [
            'transaction_id' => $transaction->id,
            'account_id' => $accounts['cash']->id,
            'debit_amount' => $totals['final_total'],
            'credit_amount' => 0,
            'notes' => "Cobro venta orden #{$transaction->order_id} - Total recibido"
        ];

        // HABER: Ingresos por Ventas (Ventas sin IVA)
        if ($totals['sales_without_vat'] > 0) {
            $entries[] = [
                'transaction_id' => $transaction->id,
                'account_id' => $accounts['sales_revenue']->id,
                'debit_amount' => 0,
                'credit_amount' => $totals['sales_without_vat'],
                'notes' => "Venta productos orden #{$transaction->order_id} - Base gravable"
            ];
        }

        // HABER: IVA por Pagar (Si hay IVA)
        if ($totals['vat_amount'] > 0) {
            $entries[] = [
                'transaction_id' => $transaction->id,
                'account_id' => $accounts['vat_payable']->id,
                'debit_amount' => 0,
                'credit_amount' => $totals['vat_amount'],
                'notes' => "IVA 15% orden #{$transaction->order_id} - Por pagar al SRI"
            ];
        }

        // Crear todas las entradas
        foreach ($entries as $entryData) {
            AccountingEntry::create($entryData);
        }

        // Verificar que la transacción esté balanceada
        $this->verifyTransactionBalance($transaction);
    }

    /**
     * ✅ Verifica que la transacción esté balanceada (DEBE = HABER)
     */
    private function verifyTransactionBalance(AccountingTransaction $transaction): void
    {
        $totalDebits = AccountingEntry::where('transaction_id', $transaction->id)
            ->sum('debit_amount');

        $totalCredits = AccountingEntry::where('transaction_id', $transaction->id)
            ->sum('credit_amount');

        if (abs($totalDebits - $totalCredits) > 0.01) { // Tolerancia para decimales
            Log::error('❌ ACCOUNTING: Transacción no balanceada', [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'difference' => $totalDebits - $totalCredits
            ]);

            throw new Exception("Transacción contable no balanceada. Débitos: {$totalDebits}, Créditos: {$totalCredits}");
        }

        Log::info('✅ ACCOUNTING: Transacción balanceada correctamente', [
            'transaction_id' => $transaction->id,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits
        ]);
    }
}