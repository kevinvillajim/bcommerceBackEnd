<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\NotificationService;
use App\Services\OrderCompletionHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageRatingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ratings:manage 
                            {--auto-complete : Auto-completar órdenes entregadas}
                            {--send-reminders : Enviar recordatorios de valoración}
                            {--days=7 : Días después de entrega para auto-completar}
                            {--reminder-days=7 : Días después de completar para recordatorio}';

    /**
     * The console command description.
     */
    protected $description = 'Gestionar auto-completado de órdenes y recordatorios de valoración';

    private OrderCompletionHandler $orderHandler;

    private NotificationService $notificationService;

    public function __construct(
        OrderCompletionHandler $orderHandler,
        NotificationService $notificationService
    ) {
        parent::__construct();
        $this->orderHandler = $orderHandler;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando gestión de valoraciones...');

        $autoComplete = $this->option('auto-complete');
        $sendReminders = $this->option('send-reminders');
        $days = (int) $this->option('days');
        $reminderDays = (int) $this->option('reminder-days');

        // Si no se especifican opciones, ejecutar ambas por defecto
        if (! $autoComplete && ! $sendReminders) {
            $autoComplete = true;
            $sendReminders = true;
        }

        if ($autoComplete) {
            $this->handleAutoCompletion($days);
        }

        if ($sendReminders) {
            $this->handleRatingReminders($reminderDays);
        }

        $this->info('✅ Gestión de valoraciones completada');
    }

    /**
     * Manejar auto-completado de órdenes
     */
    private function handleAutoCompletion(int $days): void
    {
        $this->info("📦 Auto-completando órdenes entregadas hace más de {$days} días...");

        try {
            $completedCount = $this->orderHandler->autoCompleteDeliveredOrders($days);

            if ($completedCount > 0) {
                $this->info("✅ Se auto-completaron {$completedCount} órdenes");
                Log::info('Comando: Órdenes auto-completadas', ['count' => $completedCount]);
            } else {
                $this->info('ℹ️ No hay órdenes para auto-completar');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error en auto-completado: '.$e->getMessage());
            Log::error('Error en comando auto-completado: '.$e->getMessage());
        }
    }

    /**
     * Manejar envío de recordatorios de valoración
     */
    private function handleRatingReminders(int $reminderDays): void
    {
        $this->info("📧 Enviando recordatorios de valoración para órdenes completadas hace {$reminderDays} días...");

        try {
            $remindersCount = 0;

            // 🔧 CORREGIDO: Usar modelo Eloquent directamente
            $ordersToRemind = $this->findOrdersNeedingRatingReminder($reminderDays);

            $this->info('🔍 Encontradas '.count($ordersToRemind).' órdenes candidatas para recordatorio');

            foreach ($ordersToRemind as $order) {
                try {
                    // Verificar si el usuario ya ha recibido un recordatorio recientemente
                    if ($this->hasRecentRatingReminder($order->user_id, $order->id)) {
                        continue;
                    }

                    // Enviar recordatorio
                    $sent = $this->notificationService->sendRatingReminderNotification(
                        $order->user_id,
                        $order->id,
                        $order->order_number
                    );

                    if ($sent) {
                        $remindersCount++;
                        $this->line("📤 Recordatorio enviado: Orden #{$order->order_number}");
                    }

                    // Pausa pequeña para no sobrecargar
                    usleep(100000); // 0.1 segundos
                } catch (\Exception $e) {
                    $this->warn("⚠️ Error enviando recordatorio para orden {$order->id}: ".$e->getMessage());
                }
            }

            if ($remindersCount > 0) {
                $this->info("✅ Se enviaron {$remindersCount} recordatorios de valoración");
                Log::info('Comando: Recordatorios de valoración enviados', ['count' => $remindersCount]);
            } else {
                $this->info('ℹ️ No se enviaron recordatorios (ya valoradas o recordatorios recientes)');
            }
        } catch (\Exception $e) {
            $this->error('❌ Error enviando recordatorios: '.$e->getMessage());
            Log::error('Error en comando recordatorios: '.$e->getMessage());
        }
    }

    /**
     * 🔧 CORREGIDO: Encontrar órdenes que necesitan recordatorio de valoración
     */
    private function findOrdersNeedingRatingReminder(int $reminderDays): array
    {
        try {
            // Buscar órdenes completadas hace X días
            $completedSince = now()->subDays($reminderDays);

            $orders = \App\Models\Order::where('status', 'completed')
                ->where('updated_at', '<=', $completedSince)
                ->with('user')
                ->get();

            // Filtrar órdenes que no han sido valoradas
            $ordersNeedingReminder = [];

            foreach ($orders as $order) {
                // Verificar si hay valoraciones para esta orden
                $hasRatings = \App\Models\Rating::where('order_id', $order->id)
                    ->where('user_id', $order->user_id)
                    ->exists();

                if (! $hasRatings && $order->user) {
                    $ordersNeedingReminder[] = $order;
                }
            }

            return $ordersNeedingReminder;
        } catch (\Exception $e) {
            Log::error('Error buscando órdenes para recordatorio: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Verificar si un usuario ha recibido un recordatorio reciente
     */
    private function hasRecentRatingReminder(int $userId, int $orderId): bool
    {
        try {
            // Verificar notificaciones de recordatorio en los últimos 3 días
            $recentReminder = \App\Models\Notification::where('user_id', $userId)
                ->where('type', 'rating_reminder')
                ->where('data->order_id', $orderId)
                ->where('created_at', '>', now()->subDays(3))
                ->exists();

            return $recentReminder;
        } catch (\Exception $e) {
            Log::error('Error verificando recordatorios recientes: '.$e->getMessage());

            return false; // En caso de error, asumir que no hay recordatorio reciente
        }
    }
}
