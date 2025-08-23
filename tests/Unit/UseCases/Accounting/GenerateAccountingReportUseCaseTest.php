<?php

namespace Tests\Unit\UseCases\Accounting;

use App\Domain\Repositories\AccountingRepositoryInterface;
use App\UseCases\Accounting\GenerateAccountingReportUseCase;
use DateTime;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateAccountingReportUseCaseTest extends TestCase
{
    /**
     * @var \Mockery\MockInterface&AccountingRepositoryInterface
     */
    protected $accountingRepository;

    protected $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear mocks para las dependencias
        $this->accountingRepository = Mockery::mock(AccountingRepositoryInterface::class);

        // Crear el caso de uso con las dependencias mockeadas
        $this->useCase = new GenerateAccountingReportUseCase(
            $this->accountingRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_generates_balance_sheet_report()
    {
        // Arrange
        $date = new DateTime;
        $expectedReport = [
            'as_of' => $date->format('Y-m-d'),
            'assets' => [
                ['id' => 1, 'code' => 'CASH', 'name' => 'Efectivo', 'balance' => 1000],
                ['id' => 2, 'code' => 'ACCOUNTS_RECEIVABLE', 'name' => 'Cuentas por Cobrar', 'balance' => 2000],
            ],
            'liabilities' => [
                ['id' => 3, 'code' => 'ACCOUNTS_PAYABLE', 'name' => 'Cuentas por Pagar', 'balance' => 500],
                ['id' => 4, 'code' => 'TAX_PAYABLE', 'name' => 'Impuestos por Pagar', 'balance' => 250],
            ],
            'equity' => [
                ['id' => 5, 'code' => 'CAPITAL', 'name' => 'Capital', 'balance' => 2000],
                ['id' => 6, 'code' => 'RETAINED_EARNINGS', 'name' => 'Ganancias Retenidas', 'balance' => 250],
            ],
            'total_assets' => 3000,
            'total_liabilities' => 750,
            'total_equity' => 2250,
        ];

        // Configurar mocks
        $this->accountingRepository->shouldReceive('getBalanceSheet')
            ->once()
            ->with($date)
            ->andReturn($expectedReport);

        // Act
        $report = $this->useCase->executeBalanceSheet($date);

        // Assert
        $this->assertEquals($expectedReport, $report);
        $this->assertEquals(3000, $report['total_assets']);
        $this->assertEquals(750, $report['total_liabilities']);
        $this->assertEquals(2250, $report['total_equity']);
    }

    #[Test]
    public function it_generates_income_statement_report()
    {
        // Arrange
        $startDate = new DateTime('2025-01-01');
        $endDate = new DateTime('2025-03-31');
        $expectedReport = [
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
            'revenue' => [
                ['id' => 7, 'code' => 'SALES_REVENUE', 'name' => 'Ingresos por Ventas', 'balance' => 10000],
                ['id' => 8, 'code' => 'SERVICE_REVENUE', 'name' => 'Ingresos por Servicios', 'balance' => 5000],
            ],
            'expenses' => [
                ['id' => 9, 'code' => 'COST_OF_GOODS_SOLD', 'name' => 'Costo de Ventas', 'balance' => 6000],
                ['id' => 10, 'code' => 'SALARIES', 'name' => 'Salarios', 'balance' => 3000],
                ['id' => 11, 'code' => 'RENT', 'name' => 'Alquiler', 'balance' => 1000],
            ],
            'total_revenue' => 15000,
            'total_expenses' => 10000,
            'net_income' => 5000,
        ];

        // Configurar mocks
        $this->accountingRepository->shouldReceive('getIncomeStatement')
            ->once()
            ->with($startDate, $endDate)
            ->andReturn($expectedReport);

        // Act
        $report = $this->useCase->executeIncomeStatement($startDate, $endDate);

        // Assert
        $this->assertEquals($expectedReport, $report);
        $this->assertEquals(15000, $report['total_revenue']);
        $this->assertEquals(10000, $report['total_expenses']);
        $this->assertEquals(5000, $report['net_income']);
    }

    #[Test]
    public function it_generates_account_ledger_report()
    {
        // Arrange
        $accountId = 1;
        $startDate = new DateTime('2025-01-01');
        $endDate = new DateTime('2025-03-31');
        $initialBalance = 1000;
        $entries = [
            [
                'transaction_id' => 1,
                'reference_number' => 'INV-1-12345',
                'date' => '2025-01-15',
                'description' => 'Factura por venta de orden #1',
                'debit' => 112,
                'credit' => 0,
                'balance' => 0, // Será recalculado
                'notes' => 'Cuenta por cobrar por orden #1',
            ],
            [
                'transaction_id' => 2,
                'reference_number' => 'PAY-1-67890',
                'date' => '2025-02-15',
                'description' => 'Pago recibido para la factura FACT-20250115-00001',
                'debit' => 0,
                'credit' => 112,
                'balance' => 0, // Será recalculado
                'notes' => 'Pago recibido',
            ],
        ];

        // Configurar mocks
        $this->accountingRepository->shouldReceive('getAccountBalance')
            ->once()
            ->with($accountId, $startDate)
            ->andReturn($initialBalance);

        $this->accountingRepository->shouldReceive('getAccountLedger')
            ->once()
            ->with($accountId, $startDate, $endDate)
            ->andReturn($entries);

        // Act
        $ledger = $this->useCase->executeAccountLedger($accountId, $startDate, $endDate);

        // Assert
        $this->assertCount(3, $ledger); // 2 entradas más el saldo inicial
        $this->assertEquals('INICIAL', $ledger[0]['reference_number']);
        $this->assertEquals($initialBalance, $ledger[0]['balance']);
        $this->assertEquals($initialBalance + $entries[0]['debit'] - $entries[0]['credit'], $ledger[1]['balance']);
        $this->assertEquals($ledger[1]['balance'] + $entries[1]['debit'] - $entries[1]['credit'], $ledger[2]['balance']);
    }
}
