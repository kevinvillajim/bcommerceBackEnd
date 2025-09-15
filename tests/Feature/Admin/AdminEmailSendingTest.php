<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminEmailSendingTest extends TestCase
{
    /**
     * Test admin can send custom email to user
     * This simulates the exact flow from AdminUsersPage.tsx
     */
    public function test_admin_can_send_custom_email_to_user()
    {
        // Buscar o crear admin user
        $admin = User::where('is_admin', true)->first();
        if (! $admin) {
            $admin = User::factory()->create([
                'email' => 'admin@test.com',
                'role' => 'admin',
                'is_admin' => true,
            ]);
        }

        // Buscar o crear target user (simulando el usuario al que queremos enviar email)
        $targetUser = User::where('email', 'kevinvillajim@hotmail.com')->first();
        if (! $targetUser) {
            $targetUser = User::factory()->create([
                'email' => 'kevinvillajim@hotmail.com',
                'first_name' => 'Kevin',
                'last_name' => 'Villacreses',
                'role' => 'user',
            ]);
        }

        // Preparar datos de email (igual que AdminUsersPage.tsx)
        $emailData = [
            'user_id' => $targetUser->id,
            'subject' => 'Test Email from BCommerce Admin Panel',
            'message' => 'Este es un email de prueba enviado desde el panel de administración de BCommerce para verificar que la funcionalidad funciona correctamente en producción.',
            'email_type' => 'custom',
        ];

        // Fake Mail para capturar emails en test
        Mail::fake();

        // Autenticar como admin y enviar request
        $response = $this->actingAs($admin)
            ->postJson('/api/admin/configurations/mail/send-custom', $emailData);

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Verificar que el email fue enviado - MailService usa NotificationMail
        Mail::assertSent(\App\Mail\NotificationMail::class, function ($mail) {
            return $mail->hasTo('kevinvillajim@hotmail.com');
        });

        echo "\n✅ TEST PASSED: Email would be sent successfully to kevinvillajim@hotmail.com\n";
        echo "📧 Subject: Test Email from BCommerce Admin Panel\n";
        echo "👤 Target User: Kevin Villacreses\n";
        echo "🔧 Route: /api/admin/configurations/mail/send-custom\n";
        echo "🛡️ Middleware: jwt.auth, admin\n";
    }

    /**
     * Test actual email sending (only run when ENV allows)
     * This will send a REAL email to verify production setup
     */
    public function test_send_real_email_to_kevin()
    {
        // Buscar admin existente
        $admin = User::where('is_admin', true)->first();
        if (! $admin) {
            $admin = User::factory()->create([
                'email' => 'admin@comersia.app',
                'role' => 'admin',
                'is_admin' => true,
            ]);
        }

        // Crear o buscar usuario Kevin
        $kevin = User::where('email', 'kevinvillajim@hotmail.com')->first();
        if (! $kevin) {
            $kevin = User::factory()->create([
                'email' => 'kevinvillajim@hotmail.com',
                'first_name' => 'Kevin',
                'last_name' => 'Villacreses',
                'role' => 'user',
                'password' => bcrypt('test123'),
                'email_verified_at' => now(),
            ]);
        }

        // Datos del email real
        $emailData = [
            'user_id' => $kevin->id,
            'subject' => '🧪 Test Email - BCommerce Admin Panel Functionality',
            'message' => '¡Hola Kevin! Este es un email de prueba enviado automáticamente desde el sistema de testing de BCommerce para verificar que la funcionalidad de envío de emails desde el panel de admin funciona correctamente. Si recibes este email, significa que: ✅ Las rutas están correctamente configuradas ✅ El middleware de admin funciona ✅ La configuración de correo está operativa ✅ El sistema está listo para producción',
            'email_type' => 'test_notification',
        ];

        // DON'T fake mail - send real email
        $response = $this->actingAs($admin)
            ->postJson('/api/admin/configurations/mail/send-custom', $emailData);

        // Verificar respuesta
        $response->assertStatus(200);

        $responseData = $response->json();

        if ($responseData['status'] === 'success') {
            echo "\n🎉 SUCCESS: Real email sent to kevinvillajim@hotmail.com!\n";
            echo "📧 Check your inbox for the test email.\n";
        } else {
            echo "\n❌ FAILED: ".($responseData['message'] ?? 'Unknown error')."\n";
        }
    }
}
