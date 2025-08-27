<?php

namespace App\UseCases\Accounting;

use App\Domain\Entities\AccountingEntryEntity;
use App\Domain\Entities\AccountingTransactionEntity;
use App\Domain\Entities\InvoiceEntity;
use App\Domain\Entities\InvoiceItemEntity;
use App\Domain\Interfaces\SriServiceInterface;
use App\Domain\Repositories\AccountingRepositoryInterface;
use App\Domain\Repositories\InvoiceRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\Services\ConfigurationService;
use DateTime;

class GenerateInvoiceUseCase
{
    private $invoiceRepository;

    private $accountingRepository;

    private $orderRepository;

    private $productRepository;

    private $sriService;
    
    private ConfigurationService $configService;

    public function __construct(
        InvoiceRepositoryInterface $invoiceRepository,
        AccountingRepositoryInterface $accountingRepository,
        OrderRepositoryInterface $orderRepository,
        ProductRepositoryInterface $productRepository,
        SriServiceInterface $sriService,
        ConfigurationService $configService
    ) {
        $this->invoiceRepository = $invoiceRepository;
        $this->accountingRepository = $accountingRepository;
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->sriService = $sriService;
        $this->configService = $configService;
    }

    public function execute(int $orderId): InvoiceEntity
    {
        // Verificar si ya existe una factura para esta orden
        $existingInvoice = $this->invoiceRepository->getInvoiceByOrderId($orderId);
        if ($existingInvoice) {
            return $existingInvoice;
        }

        // Obtener la orden
        $order = $this->orderRepository->findById($orderId);
        if (! $order) {
            throw new \Exception("Order not found with ID: {$orderId}");
        }

        // Crear la transacción contable
        $transaction = $this->createAccountingTransaction($order);

        // Crear la factura
        $invoice = $this->createInvoice($order, $transaction->id);

        // Emitir la factura en el SRI
        $sriResponse = $this->sriService->generateInvoice($invoice);

        // Si la emisión fue exitosa, marcar la transacción como publicada
        if ($sriResponse['success'] ?? false) {
            $transaction->isPosted = true;
            $this->accountingRepository->createTransaction($transaction);
        }

        return $invoice;
    }

    private function createAccountingTransaction($order): AccountingTransactionEntity
    {
        $orderId = $order->getId();
        $userId = $order->getUserId();

        // Crear la transacción principal
        $transaction = new AccountingTransactionEntity(
            referenceNumber: 'INV-'.$orderId.'-'.time(),
            transactionDate: new DateTime,
            description: "Factura por venta de orden #{$orderId}",
            type: 'SALE',
            userId: $userId,
            orderId: $orderId,
            isPosted: false
        );

        // Calcular los impuestos dinámicamente
        $totalAmount = $order->getTotal();
        // ✅ COMPLETAMENTE DINÁMICO: Sin fallback hardcoded
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate');
        
        if ($taxRatePercentage === null) {
            throw new \Exception('Tax rate no configurado en BD - Configuración requerida para facturación');
        }
        $taxRate = $taxRatePercentage / 100; // Convertir % a decimal
        $taxAmount = $totalAmount * $taxRate; // IVA dinámico
        $subtotal = $totalAmount - $taxAmount;

        // Asiento de débito a Cuentas por Cobrar (Activo)
        $transaction->addEntry(new AccountingEntryEntity(
            accountId: $this->getAccountId('ACCOUNTS_RECEIVABLE'),
            debitAmount: $totalAmount,
            creditAmount: 0,
            notes: "Cuenta por cobrar por orden #{$orderId}"
        ));

        // Asiento de crédito a Ingresos por Ventas (Ingresos)
        $transaction->addEntry(new AccountingEntryEntity(
            accountId: $this->getAccountId('SALES_REVENUE'),
            debitAmount: 0,
            creditAmount: $subtotal,
            notes: "Ingreso por venta de orden #{$orderId}"
        ));

        // Asiento de crédito a Impuestos por Pagar (Pasivo)
        $transaction->addEntry(new AccountingEntryEntity(
            accountId: $this->getAccountId('TAX_PAYABLE'),
            debitAmount: 0,
            creditAmount: $taxAmount,
            notes: "IVA por venta de orden #{$orderId}"
        ));

        // Guardar la transacción y sus asientos
        return $this->accountingRepository->createTransaction($transaction);
    }

    private function createInvoice($order, int $transactionId): InvoiceEntity
    {
        $orderId = $order->getId();
        $userId = $order->getUserId();
        // Use getSellerId if available, otherwise default to userId
        $sellerId = method_exists($order, 'getSellerId') && $order->getSellerId() ? $order->getSellerId() : $userId;
        $total = $order->getTotal();

        // Calculamos tax y subtotal basados en el total - IVA dinámico
        // ✅ COMPLETAMENTE DINÁMICO: Sin fallback hardcoded
        $taxRatePercentage = $this->configService->getConfig('payment.taxRate');
        
        if ($taxRatePercentage === null) {
            throw new \Exception('Tax rate no configurado en BD - Configuración requerida para facturación');
        }
        $taxRate = $taxRatePercentage / 100; // Convertir % a decimal
        $tax = $total * $taxRate; // IVA dinámico
        $subtotal = $total - $tax;

        // Generar un número de factura único
        $invoiceNumber = 'FACT-'.date('Ymd').'-'.str_pad($orderId, 5, '0', STR_PAD_LEFT);

        // Crear la entidad de factura
        $invoice = new InvoiceEntity(
            invoiceNumber: $invoiceNumber,
            orderId: $orderId,
            userId: $userId,
            sellerId: $sellerId,
            transactionId: $transactionId,
            issueDate: new DateTime,
            subtotal: $subtotal,
            taxAmount: $tax,
            totalAmount: $total,
            status: 'DRAFT'
        );

        // Agregar los items de la factura (productos de la orden)
        foreach ($order->getItems() as $orderItem) {
            $product = $this->productRepository->findById($orderItem->product_id);

            $taxRate = $this->configService->getConfig('payment.taxRate', 15.0); // IVA dinámico desde configuración
            $unitPrice = $orderItem->price;
            $quantity = $orderItem->quantity;
            $discount = $orderItem->discount ?? 0;
            $itemSubtotal = ($unitPrice * $quantity) - $discount;
            $taxAmount = $itemSubtotal * ($taxRate / 100);

            $invoiceItem = new InvoiceItemEntity(
                productId: $orderItem->product_id,
                description: $product->getName(),
                quantity: $quantity,
                unitPrice: $unitPrice,
                discount: $discount,
                taxRate: $taxRate,
                taxAmount: $taxAmount,
                total: $itemSubtotal + $taxAmount,
                sriProductCode: $product->getSku() ?? null
            );

            $invoice->addItem($invoiceItem);
        }

        // Guardar la factura y sus items
        return $this->invoiceRepository->createInvoice($invoice);
    }

    /**
     * Obtiene el ID de la cuenta contable según su código
     */
    private function getAccountId(string $accountCode): int
    {
        $account = $this->accountingRepository->getAccountByCode($accountCode);
        if (! $account) {
            throw new \Exception("Accounting account not found with code: {$accountCode}");
        }

        return $account->id;
    }
}
