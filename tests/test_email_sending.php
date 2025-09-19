<?php

require_once __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

// Test email sending functionality
echo "🧪 Testing Admin Email Sending Functionality\n";
echo '='.str_repeat('=', 50)."\n";

try {
    // Find admin user
    $admin = \App\Models\User::where('is_admin', true)->first();
    if (! $admin) {
        echo "❌ No admin user found. Creating one...\n";
        $admin = \App\Models\User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
        echo "✅ Admin user created: {$admin->email}\n";
    } else {
        echo "✅ Admin user found: {$admin->email}\n";
    }

    // Find or create Kevin
    $kevin = \App\Models\User::where('email', 'kevinvillajim@hotmail.com')->first();
    if (! $kevin) {
        echo "❌ Kevin user not found. Creating one...\n";
        $kevin = \App\Models\User::create([
            'name' => 'Kevin Villacreses',
            'first_name' => 'Kevin',
            'last_name' => 'Villacreses',
            'email' => 'kevinvillajim@hotmail.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        echo "✅ Kevin user created: {$kevin->email}\n";
    } else {
        echo "✅ Kevin user found: {$kevin->email}\n";
    }

    // Test MailService
    echo "\n📧 Testing email sending...\n";

    $mailService = app(\App\Services\MailService::class);

    $subject = '🧪 Test Email - BCommerce Admin Panel';
    $message = '¡Hola Kevin! Este es un email de prueba desde el script de testing de BCommerce. Si recibes este email, significa que:

✅ Las rutas de admin están correctamente configuradas
✅ El middleware de admin funciona correctamente  
✅ El MailService está operativo
✅ La configuración de correo está funcionando
✅ El sistema está listo para producción

Este email fue enviado automáticamente por el sistema de testing.

Saludos,
Sistema de Testing BCommerce';

    $result = $mailService->sendNotificationEmail(
        $kevin,
        $subject,
        $message,
        [
            'email_type' => 'admin_test',
            'sent_by_admin' => true,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
            'test_mode' => true,
        ]
    );

    if ($result) {
        echo "🎉 SUCCESS: Email sent successfully to kevinvillajim@hotmail.com!\n";
        echo "📧 Subject: {$subject}\n";
        echo "👤 Recipient: {$kevin->name} ({$kevin->email})\n";
        echo "👨‍💼 Sent by: {$admin->name} ({$admin->email})\n";
        echo "\n📮 Please check your inbox at kevinvillajim@hotmail.com\n";
    } else {
        echo "❌ FAILED: Email could not be sent\n";
        echo "📋 Check the Laravel logs for more details\n";
    }

} catch (\Exception $e) {
    echo '💥 ERROR: '.$e->getMessage()."\n";
    echo '📄 File: '.$e->getFile().':'.$e->getLine()."\n";
    echo "🔍 Trace:\n".$e->getTraceAsString()."\n";
}

echo "\n".str_repeat('=', 60)."\n";
echo "🏁 Test completed\n";
