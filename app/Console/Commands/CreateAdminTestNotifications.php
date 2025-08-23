<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdminTestNotifications extends Command
{
    protected $signature = 'admin:create-test-notifications {--admin-id=}';

    protected $description = 'Create test notifications for admin user to test the notification system';

    public function handle()
    {
        $adminId = $this->option('admin-id');

        // Buscar un admin o usar el especificado
        if ($adminId) {
            $admin = User::where('id', $adminId)->whereHas('admin')->first();
        } else {
            $admin = User::whereHas('admin')->first();
        }

        if (! $admin) {
            $this->error('No admin user found. Please specify --admin-id or ensure there is at least one admin user.');

            return 1;
        }

        $this->info("Creating test notifications for admin: {$admin->name} (ID: {$admin->id})");

        // Crear notificaciones de diferentes tipos para probar el sistema
        $testNotifications = [
            // Usuarios
            [
                'user_id' => $admin->id,
                'type' => 'user_registered',
                'title' => 'Nuevo usuario registrado',
                'message' => 'Un nuevo usuario se ha registrado en la plataforma',
                'data' => json_encode(['user_name' => 'Juan Pérez', 'user_email' => 'juan@example.com']),
                'read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'user_reported',
                'title' => 'Usuario reportado',
                'message' => 'Un usuario ha sido reportado por comportamiento inapropiado',
                'data' => json_encode(['reported_user' => 'Usuario123', 'reason' => 'Contenido inapropiado']),
                'read' => false,
                'created_at' => now()->subMinutes(30),
                'updated_at' => now()->subMinutes(30),
            ],

            // Sellers
            [
                'user_id' => $admin->id,
                'type' => 'seller_application',
                'title' => 'Nueva solicitud de vendedor',
                'message' => 'Una nueva solicitud de vendedor está pendiente de aprobación',
                'data' => json_encode(['applicant_name' => 'María García', 'store_name' => 'Tienda María']),
                'read' => false,
                'created_at' => now()->subHours(1),
                'updated_at' => now()->subHours(1),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'seller_issue',
                'title' => 'Problema con vendedor',
                'message' => 'Se ha reportado un problema con un vendedor',
                'data' => json_encode(['seller_name' => 'Tienda ABC', 'issue' => 'Productos de baja calidad']),
                'read' => false,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],

            // Orders
            [
                'user_id' => $admin->id,
                'type' => 'order_refund',
                'title' => 'Solicitud de reembolso',
                'message' => 'Un cliente ha solicitado un reembolso para su pedido',
                'data' => json_encode(['order_id' => '12345', 'amount' => 150.00, 'reason' => 'Producto defectuoso']),
                'read' => false,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'payment_issue',
                'title' => 'Problema de pago',
                'message' => 'Se ha detectado un problema con el procesamiento de pagos',
                'data' => json_encode(['payment_method' => 'Tarjeta de crédito', 'error' => 'Transacción denegada']),
                'read' => false,
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(4),
            ],

            // Shipping
            [
                'user_id' => $admin->id,
                'type' => 'shipping_problem',
                'title' => 'Problema de envío',
                'message' => 'Se ha reportado un problema con un envío',
                'data' => json_encode(['tracking' => 'ABC123456', 'issue' => 'Paquete perdido']),
                'read' => false,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(5),
            ],

            // Ratings
            [
                'user_id' => $admin->id,
                'type' => 'rating_reported',
                'title' => 'Valoración reportada',
                'message' => 'Una valoración ha sido reportada por contenido inapropiado',
                'data' => json_encode(['product_name' => 'Smartphone XYZ', 'reporter' => 'Cliente123']),
                'read' => false,
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'content_violation',
                'title' => 'Violación de contenido',
                'message' => 'Se ha detectado contenido que viola las políticas',
                'data' => json_encode(['type' => 'Valoración', 'violation' => 'Lenguaje ofensivo']),
                'read' => false,
                'created_at' => now()->subHours(7),
                'updated_at' => now()->subHours(7),
            ],

            // Feedback
            [
                'user_id' => $admin->id,
                'type' => 'feedback_submitted',
                'title' => 'Nuevo feedback recibido',
                'message' => 'Un cliente ha enviado feedback sobre la plataforma',
                'data' => json_encode(['customer' => 'Ana López', 'rating' => 4, 'category' => 'Usabilidad']),
                'read' => false,
                'created_at' => now()->subHours(8),
                'updated_at' => now()->subHours(8),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'complaint_received',
                'title' => 'Nueva queja recibida',
                'message' => 'Se ha recibido una queja formal de un cliente',
                'data' => json_encode(['complaint_id' => 'COMP-001', 'severity' => 'Alta']),
                'read' => false,
                'created_at' => now()->subHours(9),
                'updated_at' => now()->subHours(9),
            ],

            // System logs
            [
                'user_id' => $admin->id,
                'type' => 'system_error',
                'title' => 'Error del sistema',
                'message' => 'Se ha detectado un error en el sistema que requiere atención',
                'data' => json_encode(['error_code' => 'SYS-001', 'module' => 'Pagos', 'severity' => 'Media']),
                'read' => false,
                'created_at' => now()->subHours(10),
                'updated_at' => now()->subHours(10),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'critical_error',
                'title' => '🚨 Error crítico del sistema',
                'message' => 'Error crítico detectado que requiere atención inmediata',
                'data' => json_encode(['error_code' => 'CRIT-001', 'module' => 'Base de datos', 'severity' => 'Crítica']),
                'read' => false,
                'created_at' => now()->subHours(11),
                'updated_at' => now()->subHours(11),
            ],

            // Invoices
            [
                'user_id' => $admin->id,
                'type' => 'payment_failed',
                'title' => 'Pago fallido',
                'message' => 'Un pago ha fallado y requiere revisión',
                'data' => json_encode(['invoice_id' => 'INV-12345', 'amount' => 250.00, 'reason' => 'Tarjeta expirada']),
                'read' => false,
                'created_at' => now()->subHours(12),
                'updated_at' => now()->subHours(12),
            ],
        ];

        // Insertar todas las notificaciones
        foreach ($testNotifications as $notification) {
            Notification::create($notification);
        }

        $this->info('✅ Successfully created '.count($testNotifications).' test notifications!');
        $this->info('📊 Notification breakdown:');
        $this->info('   • Users: 2 notifications');
        $this->info('   • Sellers: 2 notifications');
        $this->info('   • Orders: 2 notifications');
        $this->info('   • Shipping: 1 notification');
        $this->info('   • Ratings: 2 notifications');
        $this->info('   • Feedback: 2 notifications');
        $this->info('   • System logs: 2 notifications');
        $this->info('   • Invoices: 1 notification');
        $this->info('');
        $this->info('🎯 Now you can test the admin notification system in the frontend!');

        return 0;
    }
}
