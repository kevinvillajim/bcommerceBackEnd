<?php

namespace App\Http\Controllers;

use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\OrderItem;
use App\Services\ConfigurationService;
use App\UseCases\Seller\GenerateEarningsReportPdfUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SellerEarningsController extends Controller
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
        $this->middleware('jwt.auth');
        $this->middleware('seller');
    }

    /**
     * Obtener métricas generales de earnings del seller
     */
    public function getEarnings(Request $request)
    {
        try {
            $userId = Auth::id();
            $seller = Seller::where('user_id', $userId)->first();

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no registrado como vendedor',
                ], 403);
            }

            $sellerId = $seller->id;

            // Parámetros de fecha (último mes por defecto)
            $startDate = $request->input('start_date', now()->subMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', now()->format('Y-m-d'));

            // Obtener configuración de comisión
            $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

            // 1. TOTAL EARNINGS (histórico)
            $totalEarningsData = $this->calculateTotalEarnings($sellerId);

            // 2. VENTAS ESTE MES
            $currentMonthStart = now()->startOfMonth()->format('Y-m-d');
            $currentMonthEnd = now()->endOfMonth()->format('Y-m-d');
            $currentMonthData = $this->calculatePeriodEarnings($sellerId, $currentMonthStart, $currentMonthEnd);

            // 3. VENTAS MES ANTERIOR (para calcular crecimiento)
            $lastMonthStart = now()->subMonth()->startOfMonth()->format('Y-m-d');
            $lastMonthEnd = now()->subMonth()->endOfMonth()->format('Y-m-d');
            $lastMonthData = $this->calculatePeriodEarnings($sellerId, $lastMonthStart, $lastMonthEnd);

            // 4. CALCULAR CRECIMIENTO
            $salesGrowth = 0;
            $earningsGrowth = 0;

            if ($lastMonthData['sales'] > 0) {
                $salesGrowth = (($currentMonthData['sales'] - $lastMonthData['sales']) / $lastMonthData['sales']) * 100;
            }

            if ($lastMonthData['net_earnings'] > 0) {
                $earningsGrowth = (($currentMonthData['net_earnings'] - $lastMonthData['net_earnings']) / $lastMonthData['net_earnings']) * 100;
            }

            // 5. PAGOS PENDIENTES (órdenes no pagadas)
            $pendingPayments = SellerOrder::where('seller_id', $sellerId)
                ->where('payment_status', '!=', 'paid')
                ->whereIn('status', ['completed', 'delivered'])
                ->sum('total');

            $pendingCommission = $pendingPayments * ($commissionRate / 100);
            $pendingNetEarnings = $pendingPayments - $pendingCommission;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_earnings' => round($totalEarningsData['total_net_earnings'], 2),
                    'pending_payments' => round($pendingNetEarnings, 2),
                    'sales_this_month' => round($currentMonthData['sales'], 2),
                    'sales_growth' => round($salesGrowth, 2),
                    'commissions_this_month' => round($currentMonthData['commissions'], 2),
                    'commissions_percentage' => $commissionRate,
                    'net_earnings_this_month' => round($currentMonthData['net_earnings'], 2),
                    'earnings_growth' => round($earningsGrowth, 2),
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'current_month' => [
                            'start' => $currentMonthStart,
                            'end' => $currentMonthEnd,
                        ],
                        'last_month' => [
                            'start' => $lastMonthStart,
                            'end' => $lastMonthEnd,
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo earnings del seller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ganancias'
            ], 500);
        }
    }

    /**
     * Obtener desglose mensual de earnings
     */
    public function getMonthlyBreakdown(Request $request)
    {
        try {
            $userId = Auth::id();
            $seller = Seller::where('user_id', $userId)->first();

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no registrado como vendedor',
                ], 403);
            }

            $sellerId = $seller->id;

            // Obtener últimos 12 meses
            $monthlyData = [];
            $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

            for ($i = 11; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $monthStart = $date->startOfMonth()->format('Y-m-d');
                $monthEnd = $date->endOfMonth()->format('Y-m-d');

                $periodData = $this->calculatePeriodEarnings($sellerId, $monthStart, $monthEnd);

                $monthlyData[] = [
                    'month' => $date->format('M Y'),
                    'month_short' => $date->format('M'),
                    'year' => $date->format('Y'),
                    'sales' => round($periodData['sales'], 2),
                    'commissions' => round($periodData['commissions'], 2),
                    'net' => round($periodData['net_earnings'], 2),
                    'orders_count' => $periodData['orders_count']
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $monthlyData
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo desglose mensual del seller', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el desglose mensual'
            ], 500);
        }
    }

    /**
     * Calcular earnings totales históricos del seller
     */
    private function calculateTotalEarnings(int $sellerId): array
    {
        $commissionRate = $this->configService->getConfig('platform.commission_rate', 10.0);

        // Obtener total de ventas completadas/pagadas
        $totalSales = SellerOrder::where('seller_id', $sellerId)
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

        // Ventas del período
        $periodSales = SellerOrder::where('seller_id', $sellerId)
            ->whereIn('status', ['completed', 'delivered', 'paid'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->sum('total');

        $periodCommissions = $periodSales * ($commissionRate / 100);
        $periodNetEarnings = $periodSales - $periodCommissions;

        // Contar órdenes del período
        $ordersCount = SellerOrder::where('seller_id', $sellerId)
            ->whereIn('status', ['completed', 'delivered', 'paid'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->count();

        // Agregar earnings de envío del período
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
        // Obtener todas las órdenes del seller completadas
        $sellerOrders = SellerOrder::where('seller_id', $sellerId)
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
        $sellerOrders = SellerOrder::where('seller_id', $sellerId)
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
     * Calcular distribución de envío (copiado del SellerOrderController)
     */
    private function calculateShippingDistribution(int $orderId): array
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
            $sellerCount = SellerOrder::where('order_id', $orderId)->distinct('seller_id')->count();

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

    /**
     * Exportar reporte de earnings en PDF
     */
    public function exportPdf(Request $request, GenerateEarningsReportPdfUseCase $generatePdfUseCase)
    {
        try {
            $userId = Auth::id();
            $seller = Seller::where('user_id', $userId)->first();

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no registrado como vendedor',
                ], 403);
            }

            // Validar parámetros de fecha
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            if ($startDate && $endDate) {
                $request->validate([
                    'start_date' => 'date',
                    'end_date' => 'date|after_or_equal:start_date',
                ]);
            }

            // Cargar relaciones necesarias
            $seller->load('user');

            // Generar PDF
            $filePath = $generatePdfUseCase->execute($seller, $startDate, $endDate);

            // Verificar que el archivo existe
            if (!Storage::disk('public')->exists($filePath)) {
                throw new \Exception('Error al generar el archivo PDF');
            }

            // Retornar la URL de descarga
            $downloadUrl = Storage::disk('public')->url($filePath);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => $downloadUrl,
                    'filename' => basename($filePath),
                    'file_path' => $filePath,
                ],
                'message' => 'Reporte PDF generado exitosamente'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Error exportando reporte PDF de earnings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte PDF'
            ], 500);
        }
    }

    /**
     * Descargar PDF de earnings
     */
    public function downloadPdf(Request $request)
    {
        try {
            $filePath = $request->input('file_path');

            if (!$filePath || !Storage::disk('public')->exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado'
                ], 404);
            }

            // Verificar que el archivo pertenece al seller autenticado
            $userId = Auth::id();
            $seller = Seller::where('user_id', $userId)->first();

            if (!$seller) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no registrado como vendedor',
                ], 403);
            }

            // Verificar que el archivo contiene el ID del seller (seguridad básica)
            if (!str_contains($filePath, "earnings_report_{$seller->id}_")) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para descargar este archivo'
                ], 403);
            }

            return Storage::disk('public')->download($filePath);

        } catch (\Exception $e) {
            Log::error('Error descargando PDF de earnings', [
                'error' => $e->getMessage(),
                'file_path' => $request->input('file_path'),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al descargar el archivo'
            ], 500);
        }
    }
}