<?php

namespace Tests\Unit\UseCases\Accounting;

use App\Domain\Entities\AccountingTransactionEntity;
use App\Domain\Entities\InvoiceEntity;
use App\Domain\Interfaces\SriServiceInterface;
use App\Domain\Repositories\AccountingRepositoryInterface;
use App\Domain\Repositories\InvoiceRepositoryInterface;
use App\UseCases\Accounting\CancelInvoiceUseCase;
use DateTime;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CancelInvoiceUseCaseTest extends TestCase
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
        $this->sriService = Mockery::mock(SriServiceInterface::class);

        // Crear el caso de uso con las dependencias mockeadas
        $this->useCase = new CancelInvoiceUseCase(
            $this->invoiceRepository,
            $this->accountingRepository,
            $this->sriService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_cancels_invoice_and_creates_reverse_transaction()
    {
        // Arrange
        $invoiceId = 1;
        $reason = 'Cliente solicitó cancelación';

        // Crear una factura para cancelar
        $invoice = new InvoiceEntity(
            id: $invoiceId,
            invoiceNumber: 'FACT-20250401-00001',
            orderId: 1,
            userId: 1,
            sellerId: 1,
            transactionId: 1,
            issueDate: new DateTime,
            subtotal: 100,
            taxAmount: 12,
            totalAmount: 112,
            status: 'ISSUED'
        );

        // Crear una transacción original
        $originalTransaction = new AccountingTransactionEntity(
            id: 1,
            referenceNumber: 'INV-1-12345',
            transactionDate: new DateTime,
            description: 'Factura por venta de orden #1',
            type: 'SALE',
            userId: 1,
            orderId: 1,
            isPosted: true
        );

        // Configurar mocks
        $this->invoiceRepository->shouldReceive('getInvoiceById')
            ->once()
            ->with($invoiceId)
            ->andReturn($invoice);

        $this->sriService->shouldReceive('cancelInvoice')
            ->once()
            ->with($invoice, $reason)
            ->andReturn(['success' => true]);

        $this->accountingRepository->shouldReceive('getTransactionById')
            ->once()
            ->with($invoice->transactionId)
            ->andReturn($originalTransaction);

        $this->accountingRepository->shouldReceive('createTransaction')
            ->once()
            ->andReturn(new AccountingTransactionEntity(id: 2));

        $this->invoiceRepository->shouldReceive('cancelInvoice')
            ->once()
            ->with($invoiceId, $reason)
            ->andReturn(true);

        // Act
        $result = $this->useCase->execute($invoiceId, $reason);

        // Assert
        $this->assertTrue($result);
    }

    #[Test]
    public function it_throws_exception_if_invoice_not_found()
    {
        // Arrange
        $invoiceId = 999;
        $reason = 'Cliente solicitó cancelación';

        // Configurar mocks
        $this->invoiceRepository->shouldReceive('getInvoiceById')
            ->once()
            ->with($invoiceId)
            ->andReturnNull();

        // Assert & Act
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Invoice not found with ID: {$invoiceId}");

        $this->useCase->execute($invoiceId, $reason);
    }

    #[Test]
    public function it_throws_exception_if_invoice_already_cancelled()
    {
        // Arrange
        $invoiceId = 1;
        $reason = 'Cliente solicitó cancelación';

        // Crear una factura ya cancelada
        $invoice = new InvoiceEntity(
            id: $invoiceId,
            invoiceNumber: 'FACT-20250401-00001',
            orderId: 1,
            userId: 1,
            sellerId: 1,
            transactionId: 1,
            issueDate: new DateTime,
            subtotal: 100,
            taxAmount: 12,
            totalAmount: 112,
            status: 'CANCELLED'
        );

        // Configurar mocks
        $this->invoiceRepository->shouldReceive('getInvoiceById')
            ->once()
            ->with($invoiceId)
            ->andReturn($invoice);

        // Assert & Act
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoice is already cancelled');

        $this->useCase->execute($invoiceId, $reason);
    }

    #[Test]
    public function it_returns_false_if_sri_cancellation_fails()
    {
        // Arrange
        $invoiceId = 1;
        $reason = 'Cliente solicitó cancelación';

        // Crear una factura para cancelar
        $invoice = new InvoiceEntity(
            id: $invoiceId,
            invoiceNumber: 'FACT-20250401-00001',
            orderId: 1,
            userId: 1,
            sellerId: 1,
            transactionId: 1,
            issueDate: new DateTime,
            subtotal: 100,
            taxAmount: 12,
            totalAmount: 112,
            status: 'ISSUED'
        );

        // Configurar mocks
        $this->invoiceRepository->shouldReceive('getInvoiceById')
            ->once()
            ->with($invoiceId)
            ->andReturn($invoice);

        $this->sriService->shouldReceive('cancelInvoice')
            ->once()
            ->with($invoice, $reason)
            ->andReturn(['success' => false]);

        // Act
        $result = $this->useCase->execute($invoiceId, $reason);

        // Assert
        $this->assertFalse($result);
    }
}
