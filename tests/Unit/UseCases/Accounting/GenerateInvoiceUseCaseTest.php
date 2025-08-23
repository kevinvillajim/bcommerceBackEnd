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

class GenerateInvoiceUseCaseTest extends TestCase
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

        // Crear mocks para todas las dependencias
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
    public function it_returns_existing_invoice_if_already_generated()
    {
        // Arrange
        $orderId = 1;
        $existingInvoice = new InvoiceEntity(
            id: 1,
            invoiceNumber: 'FACT-20250401-00001',
            orderId: $orderId,
            userId: 1,
            sellerId: 1,
            issueDate: new DateTime,
            subtotal: 100,
            taxAmount: 12,
            totalAmount: 112,
            status: 'ISSUED'
        );

        // Expectativas
        $this->invoiceRepository->shouldReceive('getInvoiceByOrderId')
            ->once()
            ->with($orderId)
            ->andReturn($existingInvoice);

        // Act
        $result = $this->useCase->execute($orderId);

        // Assert
        $this->assertSame($existingInvoice, $result);
    }

    #[Test]
    public function it_generates_new_invoice_with_accounting_transaction()
    {
        // Arrange
        $orderId = 1;
        $userId = 1;
        $sellerId = 1;

        // Define order items
        $orderItem = (object) [
            'product_id' => 1,
            'price' => 100,
            'quantity' => 1,
            'discount' => 0,
        ];
        $orderItems = [$orderItem];

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
            orderNumber: 'ORD-001'
        );

        // Create a mock for ProductEntity
        $productMock = Mockery::mock(ProductEntity::class);
        $productMock->shouldReceive('getName')->andReturn('Producto de Prueba');
        $productMock->shouldReceive('getSku')->andReturn('SKU001');

        // Create AccountingAccountEntity instances
        $account1 = new AccountingAccountEntity(id: 1, code: 'ACCOUNTS_RECEIVABLE', name: 'Cuentas por Cobrar');
        $account2 = new AccountingAccountEntity(id: 2, code: 'SALES_REVENUE', name: 'Ingresos por Ventas');
        $account3 = new AccountingAccountEntity(id: 3, code: 'TAX_PAYABLE', name: 'Impuestos por Pagar');

        // Create transaction instance
        $transaction = new AccountingTransactionEntity(id: 1);

        // Create invoice instance
        $invoice = new InvoiceEntity(
            id: 1,
            invoiceNumber: 'FACT-20250401-00001',
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

        // Configure repository expectations
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

        // Important fix: The createTransaction is called exactly once in the createAccountingTransaction
        // method, and a second time conditionally if SRI generation is successful
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

        // When SRI validation is successful, there's a second call to createTransaction
        // Let's set success to false to avoid this second call
        $this->sriService->shouldReceive('generateInvoice')
            ->once()
            ->andReturn(['success' => false]);

        // Act
        $result = $this->useCase->execute($orderId);

        // Assert
        $this->assertInstanceOf(InvoiceEntity::class, $result);
        $this->assertEquals($orderId, $result->orderId);
        $this->assertEquals('DRAFT', $result->status);
    }

    #[Test]
    public function it_throws_exception_when_order_not_found()
    {
        // Arrange
        $orderId = 999;

        // Expectativas
        $this->invoiceRepository->shouldReceive('getInvoiceByOrderId')
            ->once()
            ->with($orderId)
            ->andReturnNull();

        $this->orderRepository->shouldReceive('findById')
            ->once()
            ->with($orderId)
            ->andReturnNull();

        // Assert & Act
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Order not found with ID: {$orderId}");

        $this->useCase->execute($orderId);
    }
}
