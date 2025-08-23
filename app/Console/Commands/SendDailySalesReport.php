<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\NotificationService;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailySalesReport extends Command
{
    protected $signature = 'app:send-daily-sales-report {--hour=18 : Hora del día para enviar el reporte (0-23)}';

    protected $description = 'Envía un reporte diario de ventas a todos los administradores';

    public function handle(NotificationService $notificationService)
    {
        $configuredHour = (int) $this->option('hour');
        $currentHour = (int) now()->format('H');

        // Solo ejecutar si es la hora configurada
        if ($currentHour !== $configuredHour) {
            return;
        }

        $this->info('Enviando reporte de ventas diarias a los administradores...');

        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        try {
            // Obtener todas las facturas del día
            $invoices = Invoice::whereBetween('issue_date', [$today, $todayEnd])
                ->where('status', 'ISSUED')
                ->get();

            // Calcular totales
            $totalAmount = $invoices->sum('total_amount');
            $count = $invoices->count();
            $bySeller = $invoices->groupBy('seller_id')
                ->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total' => $group->sum('total_amount'),
                    ];
                });

            // Preparar datos para la notificación
            $salesData = [
                'date' => now()->toDateString(),
                'total' => $totalAmount,
                'count' => $count,
                'by_seller' => $bySeller,
            ];

            // Enviar notificación a todos los administradores
            $notifications = $notificationService->notifyAdminDailySales($salesData);

            // Usar count() en array en lugar de llamar a método count()
            $this->info('Se enviaron '.count($notifications).' notificaciones a administradores.');
        } catch (\Exception $e) {
            Log::error('Error al enviar reporte de ventas diarias: '.$e->getMessage());
            $this->error('Error al enviar reporte de ventas diarias: '.$e->getMessage());
        }
    }
}
