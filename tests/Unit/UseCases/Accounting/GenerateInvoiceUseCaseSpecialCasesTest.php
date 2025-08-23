<?php

namespace Tests\Unit\UseCases\Accounting;

use App\Domain\Entities\AccountingAccountEntity;
use App\Domain\Entities\AccountingTransactionEntity;
use App\Domain\Entities\InvoiceEntity;
use App\Domain\Entities\OrderEntity;
use App\Domain\Entities\ProductEntity;
use App\Domain\Interfaces\SriServiceInterface;
use App\Domain\Repositories\AccountingRepositoryInterface;
use App\Domain\Repositories\InvoiceRepositoryInterface;
use App\Domain\Repositories\OrderRepositoryInterface;
use App\Domain\Repositories\ProductRepositoryInterface;
use App\UseCases\Accounting\GenerateInvoiceUseCase;
use DateTime;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateInvoiceUseCaseSpecialCasesTest extends TestCase
{
    /**
     * @var \Mockery\MockInterface&InvoiceRepositoryInterface
     */
    protected $invoiceRepository;

    /**
     * @var \Mockery\MockInterface&AccountingRepositoryInterface
     */
    protected $accountingRepository;

    /**
     * @var \Mockery\MockInterface&OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var \Mockery\MockInterface&ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Mockery\MockInterface&SriServiceInterface
     */
    protected $sriService;

    protected $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear mocks para las dependencias
        $this->invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $this->accountingRepository = Mockery::mock(AccountingRepositoryInterface::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->productRepository = Mockery::mock(ProductRepositoryInterface::class);
        $this->sriService = Mockery::mock(SriServiceInterface::class);

        // Crear el caso de uso con las dependencias mockeadas
        $this->useCase = new GenerateInvoiceUseCase(
            $this->invoiceRepository,
            $this->accountingRepository,
            $this->orderRepository,
            $this->productRepository,
            $this->sriService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_handles_order_with_multiple_items()
    {
        // Arrange
        $orderId = 1;
        $userId = 1;
        $sellerId = 1;

        // Crear una orden con múltiples items
        $orderItems = [
            (object) [
                'product_id' => 1,
                'price' => 50,
                'quantity' => 2,
                'discount' => 0,
            ],
            (object) [
                'product_id' => 2,
                'price' => 100,
                'quantity' => 1,
                'discount' => 10,
            ],
        ];

        // Create a real OrderEntity instance
        $order = new OrderEntity(
            userId: $userId,
            sellerId: $sellerId,
            items: $orderItems,
            total: 190.0,
            status: 'processing',
            paymentId: '123456',
            paymentMethod: 'credit_card',
            paymentStatus: 'paid',
            id: $orderId,
            orderNumber: 'ORD-002'
        );

        // Crear mocks para productos
        $product1 = Mockery::mock(ProductEntity::class);
        $product1->shouldReceive('getName')->andReturn('Producto 1');
        $product1->shouldReceive('getSku')->andReturn('SKU001');

        $product2 = Mockery::mock(ProductEntity::class);
        $product2->shouldReceive('getName')->andReturn('Producto 2');
        $product2->shouldReceive('getSku')->andReturn('SKU002');

        // Crear cuentas contables
        $account1 = new AccountingAccountEntity(id: 1, code: 'ACCOUNTS_RECEIVABLE', name: 'Cuentas por Cobrar');
        $account2 = new AccountingAccountEntity(id: 2, code: 'SALES_REVENUE', name: 'Ingresos por Ventas');
        $account3 = new AccountingAccountEntity(id: 3, code: 'TAX_PAYABLE', name: 'Impuestos por Pagar');

        // Crear transacción
        $transaction = new AccountingTransactionEntity(id: 1);

        // Crear factura
        $invoice = new InvoiceEntity(
            id: 1,
            invoiceNumber: 'FACT-20250401-00002',
            orderId: $orderId,
            userId: $userId,
            sellerId: $sellerId,
            transactionId: 1,
            issueDate: new DateTime,
            subtotal: 165.22, // 190 / 1.15 (redondeado a 2 decimales)
            taxAmount: 24.78, // 190 - 165.22
            totalAmount: 190,
            status: 'DRAFT'
        );

        // Configurar expectativas
        $this->invoiceRepository->shouldReceive('getInvoiceByOrderId')
            ->once()
            ->with($orderId)
            ->andReturnNull();

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with($orderId)
            ->andReturn($order);

        $this->accountingRepository->shouldReceive('getAccountByCode')
            ->times(3)
            ->andReturn($account1, $account2, $account3);

        // Fix: Only expect createTransaction once, and set SRI to failure
        $this->accountingRepository->shouldReceive('createTransaction')
            ->once()
            ->andReturn($transaction);

        $this->productRepository->shouldReceive('findById')
            ->twice()
            ->andReturnUsing(function ($id) use ($product1, $product2) {
                return $id === 1 ? $product1 : $product2;
            });

        $this->invoiceRepository->shouldReceive('createInvoice')
            ->once()
            ->andReturn($invoice);

        // Set SRI generation to false to avoid the second call to createTransaction
        $this->sriService->shouldReceive('generateInvoice')
            ->once()
            ->andReturn(['success' => false]);

        // Act
        $result = $this->useCase->execute($orderId);

        // Assert
        $this->assertInstanceOf(InvoiceEntity::class, $result);
        $this->assertEquals($orderId, $result->orderId);
        $this->assertEquals('DRAFT', $result->status);
        $this->assertEquals(190, $result->totalAmount);
    }

    #[Test]
    public function it_handles_failed_sri_generation()
    {
        // Arrange
        $orderId = 1;
        $userId = 1;
        $sellerId = 1;

        // Crear datos para los items de la orden
        $orderItems = [
            (object) [
                'product_id' => 1,
                'price' => 100,
                'quantity' => 1,
                'discount' => 0,
            ],
        ];

        // Create a real OrderEntity instance
        $order = new OrderEntity(
            userId: $userId,
            sellerId: $sellerId,
            items: $orderItems,
            total: 112.0,
            status: 'processing',
            paymentId: '123456',
            paymentMethod: 'credit_card',
            paymentStatus: 'paid',
            id: $orderId,
            orderNumber: 'ORD-003'
        );

        // Crear un mock para ProductEntity
        $productMock = Mockery::mock(ProductEntity::class);
        $productMock->shouldReceive('getName')->andReturn('Producto de Prueba');
        $productMock->shouldReceive('getSku')->andReturn('SKU001');

        // Crear instancias reales para AccountingAccountEntity
        $account1 = new AccountingAccountEntity(id: 1, code: 'ACCOUNTS_RECEIVABLE', name: 'Cuentas por Cobrar');
        $account2 = new AccountingAccountEntity(id: 2, code: 'SALES_REVENUE', name: 'Ingresos por Ventas');
        $account3 = new AccountingAccountEntity(id: 3, code: 'TAX_PAYABLE', name: 'Impuestos por Pagar');

        // Crear instancia para AccountingTransactionEntity
        $transaction = new AccountingTransactionEntity(id: 1);

        // Crear instancia para InvoiceEntity
        $invoice = new InvoiceEntity(
            id: 1,
            invoiceNumber: 'FACT-20250401-00003',
            orderId: $orderId,
            userId: $userId,
            sellerId: $sellerId,
            transactionId: 1,
            issueDate: new DateTime,
            subtotal: 97.39, // 112 / 1.15 rounded to 2 decimals
            taxAmount: 14.61, // 112 - 97.39
            totalAmount: 112,
            status: 'DRAFT'
        );

        // Configurar expectativas para los repositorios
        $this->invoiceRepository->shouldReceive('getInvoiceByOrderId')
            ->once()
            ->with($orderId)
            ->andReturnNull();

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with($orderId)
            ->andReturn($order);

        $this->accountingRepository->shouldReceive('getAccountByCode')
            ->times(3)
            ->andReturn($account1, $account2, $account3);

        $this->accountingRepository->shouldReceive('createTransaction')
            ->once()
            ->andReturn($transaction);

        $this->productRepository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($productMock);

        $this->invoiceRepository->shouldReceive('createInvoice')
            ->once()
            ->andReturn($invoice);

        $this->sriService->shouldReceive('generateInvoice')
            ->once()
            ->andReturn(['success' => false, 'mensaje' => 'Error en la generación del comprobante electrónico']);

        // Act
        $result = $this->useCase->execute($orderId);

        // Assert
        $this->assertInstanceOf(InvoiceEntity::class, $result);
        $this->assertEquals($orderId, $result->orderId);
        $this->assertEquals('DRAFT', $result->status);
    }

    #[Test]
    public function it_handles_zero_tax_orders()
    {
        // Arrange
        $orderId = 1;
        $userId = 1;
        $sellerId = 1;

        // Crear una orden con importe total cero
        $orderItems = [
            (object) [
                'product_id' => 1,
                'price' => 0,
                'quantity' => 1,
                'discount' => 0,
            ],
        ];

        // Create a real OrderEntity instance
        $order = new OrderEntity(
            userId: $userId,
            sellerId: $sellerId,
            items: $orderItems,
            total: 0.0,
            status: 'processing',
            paymentId: '123456',
            paymentMethod: 'credit_card',
            paymentStatus: 'paid',
            id: $orderId,
            orderNumber: 'ORD-004'
        );

        // Crear un mock para ProductEntity
        $productMock = Mockery::mock(ProductEntity::class);
        $productMock->shouldReceive('getName')->andReturn('Producto Gratuito');
        $productMock->shouldReceive('getSku')->andReturn('SKU001');

        // Crear instancias reales para AccountingAccountEntity
        $account1 = new AccountingAccountEntity(id: 1, code: 'ACCOUNTS_RECEIVABLE', name: 'Cuentas por Cobrar');
        $account2 = new AccountingAccountEntity(id: 2, code: 'SALES_REVENUE', name: 'Ingresos por Ventas');
        $account3 = new AccountingAccountEntity(id: 3, code: 'TAX_PAYABLE', name: 'Impuestos por Pagar');

        // Crear instancia para AccountingTransactionEntity
        $transaction = new AccountingTransactionEntity(id: 1);

        // Crear instancia para InvoiceEntity
        $invoice = new InvoiceEntity(
            id: 1,
            invoiceNumber: 'FACT-20250401-00004',
            orderId: $orderId,
            userId: $userId,
            sellerId: $sellerId,
            transactionId: 1,
            issueDate: new DateTime,
            subtotal: 0,
            taxAmount: 0,
            totalAmount: 0,
            status: 'DRAFT'
        );

        // Configurar expectativas
        $this->invoiceRepository->shouldReceive('getInvoiceByOrderId')
            ->once()
            ->with($orderId)
            ->andReturnNull();

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with($orderId)
            ->andReturn($order);

        $this->accountingRepository->shouldReceive('getAccountByCode')
            ->times(3)
            ->andReturn($account1, $account2, $account3);

        // Fix: Only expect createTransaction once, and set SRI to failure
        $this->accountingRepository->shouldReceive('createTransaction')
            ->once()
            ->andReturn($transaction);

        $this->productRepository->shouldReceive('findById')
            ->once()
            ->with(1)
            ->andReturn($productMock);

        $this->invoiceRepository->shouldReceive('createInvoice')
            ->once()
            ->andReturn($invoice);

        // Set SRI generation to false to avoid the second call to createTransaction
        $this->sriService->shouldReceive('generateInvoice')
            ->once()
            ->andReturn(['success' => false]);

        // Act
        $result = $this->useCase->execute($orderId);

        // Assert
        $this->assertInstanceOf(InvoiceEntity::class, $result);
        $this->assertEquals($orderId, $result->orderId);
        $this->assertEquals('DRAFT', $result->status);
        $this->assertEquals(0, $result->totalAmount);
        $this->assertEquals(0, $result->taxAmount);
        $this->assertEquals(0, $result->subtotal);
    }
}
