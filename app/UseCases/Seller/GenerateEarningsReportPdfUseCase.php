<?php

namespace App\UseCases\Seller;

use App\Models\Seller;
use App\Services\ConfigurationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class GenerateEarningsReportPdfUseCase
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Genera un reporte PDF de earnings de seller
     */
    public function execute(Seller $seller, ?string $startDate = null, ?string $endDate = null): string
    {
        Log::info('Iniciando generación de reporte PDF de earnings', [
            'seller_id' => $seller->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        try {
            // Establecer fechas por defecto (último mes)
            if (!$startDate || !$endDate) {
                $endDate = now()->format('Y-m-d');
                $startDate = now()->subMonth()->format('Y-m-d');
            }

            // Obtener métricas generales usando el mismo controlador
            $earningsController = app(\App\Http\Controllers\SellerEarningsController::class);
            $earningsData = $this->getEarningsFromController($seller->id, $startDate, $endDate);

            // Obtener desglose mensual para el período filtrado
            $monthlyData = $this->getMonthlyBreakdownFromController($seller->id, $startDate, $endDate);

            // Preparar datos para la plantilla
            $pdfData = [
                'seller' => $seller,
                'earningsData' => $earningsData,
                'monthlyData' => $monthlyData,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'formatted_start' => Carbon::parse($startDate)->format('d/m/Y'),
                    'formatted_end' => Carbon::parse($endDate)->format('d/m/Y'),
                ],
                'generatedAt' => now(),
                'commissionRate' => $this->configService->getConfig('platform.commission_rate', 10.0),
            ];

            // Generar PDF usando vista blade
            $pdf = Pdf::loadView('earnings.pdf-template', $pdfData);

            // Configurar orientación y tamaño
            $pdf->setPaper('A4', 'portrait');

            // Generar nombre único para el archivo
            $fileName = "earnings_report_{$seller->id}_" . now()->format('Y_m_d_His') . ".pdf";
            $filePath = "earnings_reports/{$fileName}";

            // Guardar el PDF en storage
            $pdfContent = $pdf->output();
            Storage::disk('public')->put($filePath, $pdfContent);

            Log::info('Reporte PDF de earnings generado exitosamente', [
                'seller_id' => $seller->id,
                'pdf_path' => $filePath,
            ]);

            return $filePath;

        } catch (Exception $e) {
            Log::error('Error generando reporte PDF de earnings', [
                'seller_id' => $seller->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Calcular datos de earnings para el período especificado
     */
    private function calculateEarningsData(int $sellerId, string $startDate, string $endDate): array
    {
        $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

        // Obtener métricas del período
        $currentMonthStart = now()->startOfMonth()->format('Y-m-d');
        $currentMonthEnd = now()->endOfMonth()->format('Y-m-d');
        $lastMonthStart = now()->subMonth()->startOfMonth()->format('Y-m-d');
        $lastMonthEnd = now()->subMonth()->endOfMonth()->format('Y-m-d');

        // Total histórico
        $totalEarnings = $this->calculateTotalEarnings($sellerId);

        // Mes actual
        $currentMonth = $this->calculatePeriodEarnings($sellerId, $currentMonthStart, $currentMonthEnd);

        // Mes anterior
        $lastMonth = $this->calculatePeriodEarnings($sellerId, $lastMonthStart, $lastMonthEnd);

        // Calcular crecimiento
        $salesGrowth = 0;
        $earningsGrowth = 0;

        if ($lastMonth['sales'] > 0) {
            $salesGrowth = (($currentMonth['sales'] - $lastMonth['sales']) / $lastMonth['sales']) * 100;
        }

        if ($lastMonth['net_earnings'] > 0) {
            $earningsGrowth = (($currentMonth['net_earnings'] - $lastMonth['net_earnings']) / $lastMonth['net_earnings']) * 100;
        }

        // Pagos pendientes
        $pendingPayments = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->where('payment_status', '!=', 'paid')
            ->whereIn('status', ['completed', 'delivered'])
            ->sum('total');

        $pendingCommission = $pendingPayments * ($commissionRate / 100);
        $pendingNetEarnings = $pendingPayments - $pendingCommission;

        return [
            'total_earnings' => $totalEarnings['total_net_earnings'],
            'pending_payments' => $pendingNetEarnings,
            'sales_this_month' => $currentMonth['sales'],
            'sales_growth' => $salesGrowth,
            'commissions_this_month' => $currentMonth['commissions'],
            'commissions_percentage' => $commissionRate,
            'net_earnings_this_month' => $currentMonth['net_earnings'],
            'earnings_growth' => $earningsGrowth,
            'current_month' => $currentMonth,
            'last_month' => $lastMonth,
            'total_sales_all_time' => $totalEarnings['total_sales'],
            'total_commissions_all_time' => $totalEarnings['total_commissions'],
        ];
    }

    /**
     * Obtener desglose mensual (últimos 12 meses)
     */
    private function getMonthlyBreakdown(int $sellerId): array
    {
        $monthlyData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthStart = $date->startOfMonth()->format('Y-m-d');
            $monthEnd = $date->endOfMonth()->format('Y-m-d');

            $periodData = $this->calculatePeriodEarnings($sellerId, $monthStart, $monthEnd);

            $monthlyData[] = [
                'month' => $date->format('M Y'),
                'month_short' => $date->format('M'),
                'year' => $date->format('Y'),
                'sales' => $periodData['sales'],
                'commissions' => $periodData['commissions'],
                'net' => $periodData['net_earnings'],
                'orders_count' => $periodData['orders_count']
            ];
        }

        return $monthlyData;
    }

    /**
     * Calcular earnings totales históricos del seller
     */
    private function calculateTotalEarnings(int $sellerId): array
    {
        $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

        $totalSales = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->whereIn('status', ['completed', 'delivered', 'paid'])
            ->sum('total');

        $totalCommissions = $totalSales * ($commissionRate / 100);
        $totalNetEarnings = $totalSales - $totalCommissions;

        // Agregar earnings de envío
        $shippingEarnings = $this->calculateTotalShippingEarnings($sellerId);

        return [
            'total_sales' => $totalSales,
            'total_commissions' => $totalCommissions,
            'total_net_earnings' => $totalNetEarnings + $shippingEarnings,
            'shipping_earnings' => $shippingEarnings
        ];
    }

    /**
     * Calcular earnings para un período específico
     */
    private function calculatePeriodEarnings(int $sellerId, string $startDate, string $endDate): array
    {
        $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

        $periodSales = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->whereIn('status', ['completed', 'delivered', 'paid'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('total');

        $periodCommissions = $periodSales * ($commissionRate / 100);
        $periodNetEarnings = $periodSales - $periodCommissions;

        $ordersCount = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->whereIn('status', ['completed', 'delivered', 'paid'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->count();

        $shippingEarnings = $this->calculatePeriodShippingEarnings($sellerId, $startDate, $endDate);

        return [
            'sales' => $periodSales,
            'commissions' => $periodCommissions,
            'net_earnings' => $periodNetEarnings + $shippingEarnings,
            'shipping_earnings' => $shippingEarnings,
            'orders_count' => $ordersCount
        ];
    }

    /**
     * Calcular earnings de envío total
     */
    private function calculateTotalShippingEarnings(int $sellerId): float
    {
        $sellerOrders = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->whereIn('status', ['completed', 'delivered', 'paid'])
            ->get();

        $totalShippingEarnings = 0;

        foreach ($sellerOrders as $sellerOrder) {
            $shippingDistribution = $this->calculateShippingDistribution($sellerOrder->order_id);
            $totalShippingEarnings += $shippingDistribution['seller_amount'] ?? 0;
        }

        return $totalShippingEarnings;
    }

    /**
     * Calcular earnings de envío para un período
     */
    private function calculatePeriodShippingEarnings(int $sellerId, string $startDate, string $endDate): float
    {
        $sellerOrders = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->whereIn('status', ['completed', 'delivered', 'paid'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();

        $totalShippingEarnings = 0;

        foreach ($sellerOrders as $sellerOrder) {
            $shippingDistribution = $this->calculateShippingDistribution($sellerOrder->order_id);
            $totalShippingEarnings += $shippingDistribution['seller_amount'] ?? 0;
        }

        return $totalShippingEarnings;
    }

    /**
     * Calcular distribución de envío
     */
    private function calculateShippingDistribution(int $orderId): array
    {
        try {
            $order = \App\Models\Order::find($orderId);
            if (!$order) {
                return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
            }

            $shippingCost = $order->shipping_cost ?? 0;

            if ($shippingCost <= 0) {
                return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
            }

            $sellerCount = \App\Models\SellerOrder::where('order_id', $orderId)->distinct('seller_id')->count();

            $enabled = $this->configService->getConfig('shipping_distribution.enabled', true);

            if (!$enabled) {
                return [
                    'seller_amount' => 0,
                    'platform_amount' => $shippingCost,
                    'total_cost' => $shippingCost,
                    'enabled' => false,
                ];
            }

            if ($sellerCount === 1) {
                $percentage = $this->configService->getConfig('shipping_distribution.single_seller_max', 80.0);
                $sellerAmount = ($shippingCost * $percentage) / 100;
                $platformAmount = $shippingCost - $sellerAmount;

                return [
                    'seller_amount' => round($sellerAmount, 2),
                    'platform_amount' => round($platformAmount, 2),
                    'total_cost' => $shippingCost,
                    'enabled' => true,
                ];
            } else {
                $percentageEach = $this->configService->getConfig('shipping_distribution.multiple_sellers_each', 40.0);
                $amountPerSeller = ($shippingCost * $percentageEach) / 100;
                $totalDistributed = $amountPerSeller * $sellerCount;
                $platformAmount = $shippingCost - $totalDistributed;

                return [
                    'seller_amount' => round($amountPerSeller, 2),
                    'platform_amount' => round($platformAmount, 2),
                    'total_cost' => $shippingCost,
                    'enabled' => true,
                ];
            }
        } catch (Exception $e) {
            Log::error("Error calculating shipping distribution for order {$orderId}: " . $e->getMessage());
            return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
        }
    }

    /**
     * Obtener datos de earnings usando el controlador (mismo cálculo que la vista web)
     */
    private function getEarningsFromController(int $sellerId, string $startDate, string $endDate): array
    {
        $controller = app(\App\Http\Controllers\SellerEarningsController::class);

        // Simular request con los parámetros de fecha
        $request = new \Illuminate\Http\Request([
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        // Usar cálculos directos en lugar de reflection
        $totalEarningsData = $this->calculateTotalEarningsForPdf($sellerId);
        $currentPeriodData = $this->calculatePeriodEarningsForPdf($sellerId, $startDate, $endDate);

        // Calcular período anterior comparable
        $startDateTime = \Carbon\Carbon::parse($startDate);
        $endDateTime = \Carbon\Carbon::parse($endDate);
        $daysDiff = $endDateTime->diffInDays($startDateTime);

        $previousStartDate = $startDateTime->copy()->subDays($daysDiff + 1)->format('Y-m-d');
        $previousEndDate = $startDateTime->copy()->subDay()->format('Y-m-d');
        $previousPeriodData = $this->calculatePeriodEarningsForPdf($sellerId, $previousStartDate, $previousEndDate);

        // Calcular crecimiento
        $salesGrowth = 0;
        $earningsGrowth = 0;

        if ($previousPeriodData['sales'] > 0) {
            $salesGrowth = (($currentPeriodData['sales'] - $previousPeriodData['sales']) / $previousPeriodData['sales']) * 100;
        }

        if ($previousPeriodData['net_earnings'] > 0) {
            $earningsGrowth = (($currentPeriodData['net_earnings'] - $previousPeriodData['net_earnings']) / $previousPeriodData['net_earnings']) * 100;
        }

        return [
            'total_earnings' => $totalEarningsData['total_net_earnings'],
            'pending_payments' => 0, // Se removió según requerimiento
            'sales_this_period' => $currentPeriodData['sales'],
            'sales_growth' => $salesGrowth,
            'commissions_this_period' => $currentPeriodData['commissions'],
            'commissions_percentage' => $this->configService->getConfig('platform.commission_rate', 10.0),
            'net_earnings_this_period' => $currentPeriodData['net_earnings'],
            'earnings_growth' => $earningsGrowth,
            'total_sales_all_time' => $totalEarningsData['total_sales'],
            'total_commissions_all_time' => $totalEarningsData['total_commissions'],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'previous_period' => [
                    'start' => $previousStartDate,
                    'end' => $previousEndDate,
                ],
            ],
        ];
    }

    /**
     * Obtener desglose mensual usando el controlador (mismo cálculo que la vista web)
     */
    private function getMonthlyBreakdownFromController(int $sellerId, string $startDate, string $endDate): array
    {
        $controller = app(\App\Http\Controllers\SellerEarningsController::class);
        $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

        // Generar datos mensuales para el período especificado
        $startDate = \Carbon\Carbon::parse($startDate);
        $endDate = \Carbon\Carbon::parse($endDate);

        $currentDate = $startDate->copy()->startOfMonth();
        $endMonth = $endDate->copy()->endOfMonth();
        $monthlyData = [];

        while ($currentDate <= $endMonth) {
            $monthStart = $currentDate->format('Y-m-d');
            $monthEnd = $currentDate->copy()->endOfMonth()->format('Y-m-d');

            // Ajustar fechas si el mes se sale del rango solicitado
            if ($currentDate->copy()->startOfMonth() < $startDate) {
                $monthStart = $startDate->format('Y-m-d');
            }
            if ($currentDate->copy()->endOfMonth() > $endDate) {
                $monthEnd = $endDate->format('Y-m-d');
            }

            $periodData = $this->calculatePeriodEarningsForPdf($sellerId, $monthStart, $monthEnd);

            $monthlyData[] = [
                'month' => $currentDate->format('M Y'),
                'month_short' => $currentDate->format('M'),
                'year' => $currentDate->format('Y'),
                'sales' => round($periodData['sales'], 2),
                'commissions' => round($periodData['commissions'], 2),
                'net' => round($periodData['net_earnings'], 2),
                'orders_count' => $periodData['orders_count']
            ];

            $currentDate->addMonth();
        }

        return $monthlyData;
    }

    /**
     * Calcular earnings totales históricos para PDF (usando filtro 'shipped')
     */
    private function calculateTotalEarningsForPdf(int $sellerId): array
    {
        $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

        $totalSales = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->where('status', 'shipped')
            ->sum('total');

        $totalCommissions = $totalSales * ($commissionRate / 100);
        $totalNetEarnings = $totalSales - $totalCommissions;

        // Agregar earnings de envío
        $shippingEarnings = $this->calculateTotalShippingEarningsForPdf($sellerId);

        return [
            'total_sales' => $totalSales,
            'total_commissions' => $totalCommissions,
            'total_net_earnings' => $totalNetEarnings + $shippingEarnings,
            'shipping_earnings' => $shippingEarnings
        ];
    }

    /**
     * Calcular earnings para un período específico para PDF (usando filtro 'shipped')
     */
    private function calculatePeriodEarningsForPdf(int $sellerId, string $startDate, string $endDate): array
    {
        $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

        $periodSales = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->where('status', 'shipped')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('total');

        $periodCommissions = $periodSales * ($commissionRate / 100);
        $periodNetEarnings = $periodSales - $periodCommissions;

        $ordersCount = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->where('status', 'shipped')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->count();

        // Agregar earnings de envío del período
        $shippingEarnings = $this->calculatePeriodShippingEarningsForPdf($sellerId, $startDate, $endDate);

        return [
            'sales' => $periodSales,
            'commissions' => $periodCommissions,
            'net_earnings' => $periodNetEarnings + $shippingEarnings,
            'shipping_earnings' => $shippingEarnings,
            'orders_count' => $ordersCount
        ];
    }

    /**
     * Calcular earnings de envío total
     */
    private function calculateTotalShippingEarningsForPdf(int $sellerId): float
    {
        $sellerOrders = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->where('status', 'delivered')
            ->get();

        $totalShippingEarnings = 0;

        foreach ($sellerOrders as $sellerOrder) {
            $shippingDistribution = $this->calculateShippingDistributionForPdf($sellerOrder->order_id);
            $totalShippingEarnings += $shippingDistribution['seller_amount'] ?? 0;
        }

        return $totalShippingEarnings;
    }

    /**
     * Calcular earnings de envío para un período
     */
    private function calculatePeriodShippingEarningsForPdf(int $sellerId, string $startDate, string $endDate): float
    {
        $sellerOrders = \App\Models\SellerOrder::where('seller_id', $sellerId)
            ->where('status', 'delivered')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->get();

        $totalShippingEarnings = 0;

        foreach ($sellerOrders as $sellerOrder) {
            $shippingDistribution = $this->calculateShippingDistributionForPdf($sellerOrder->order_id);
            $totalShippingEarnings += $shippingDistribution['seller_amount'] ?? 0;
        }

        return $totalShippingEarnings;
    }

    /**
     * Calcular distribución de envío
     */
    private function calculateShippingDistributionForPdf(int $orderId): array
    {
        try {
            // Obtener la orden principal para saber el costo de envío
            $order = \App\Models\Order::find($orderId);
            if (!$order) {
                return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
            }

            // Obtener el costo de envío de la orden
            $shippingCost = $order->shipping_cost ?? 0;

            if ($shippingCost <= 0) {
                return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
            }

            // Contar cuántos sellers únicos hay en esta orden
            $sellerCount = \App\Models\SellerOrder::where('order_id', $orderId)->distinct('seller_id')->count();

            $enabled = $this->configService->getConfig('shipping_distribution.enabled', true);

            if (!$enabled) {
                return [
                    'seller_amount' => 0,
                    'platform_amount' => $shippingCost,
                    'total_cost' => $shippingCost,
                    'enabled' => false,
                ];
            }

            if ($sellerCount === 1) {
                // Un solo seller: recibe el porcentaje máximo configurado
                $percentage = $this->configService->getConfig('shipping_distribution.single_seller_max', 80.0);
                $sellerAmount = ($shippingCost * $percentage) / 100;
                $platformAmount = $shippingCost - $sellerAmount;

                return [
                    'seller_amount' => round($sellerAmount, 2),
                    'platform_amount' => round($platformAmount, 2),
                    'total_cost' => $shippingCost,
                    'enabled' => true,
                ];
            } else {
                // Múltiples sellers: cada uno recibe el porcentaje configurado
                $percentageEach = $this->configService->getConfig('shipping_distribution.multiple_sellers_each', 40.0);
                $amountPerSeller = ($shippingCost * $percentageEach) / 100;
                $totalDistributed = $amountPerSeller * $sellerCount;
                $platformAmount = $shippingCost - $totalDistributed;

                return [
                    'seller_amount' => round($amountPerSeller, 2),
                    'platform_amount' => round($platformAmount, 2),
                    'total_cost' => $shippingCost,
                    'enabled' => true,
                ];
            }
        } catch (\Exception $e) {
            Log::error("Error calculating shipping distribution for order {$orderId}: " . $e->getMessage());
            return ['seller_amount' => 0, 'platform_amount' => 0, 'total_cost' => 0];
        }
    }
}