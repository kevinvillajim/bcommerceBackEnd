<?php

require_once __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Mail;

echo "🧪 SMTP CONNECTION TEST - Testing email system directly\n";
echo '='.str_repeat('=', 60)."\n";

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

try {
    echo "⚙️  SMTP Configuration:\n";
    echo '- Driver: '.config('mail.default')."\n";
    echo '- Host: '.config('mail.mailers.smtp.host')."\n";
    echo '- Port: '.config('mail.mailers.smtp.port')."\n";
    echo '- Encryption: '.config('mail.mailers.smtp.encryption')."\n";
    echo '- Username: '.(config('mail.mailers.smtp.username') ? '***set***' : 'not_set')."\n";
    echo '- Password: '.(config('mail.mailers.smtp.password') ? '***set***' : 'not_set')."\n";
    echo '- From Address: '.config('mail.from.address')."\n";
    echo '- From Name: '.config('mail.from.name')."\n";

    echo "\n📧 Testing direct email sending with Laravel Mail facade...\n";

    $testEmailContent = '¡Hola Kevin!

Este es un test REAL del sistema SMTP de BCommerce.

Si recibes este email, significa que:
✅ La configuración SMTP está correcta
✅ Los servidores de email están funcionando
✅ Laravel puede enviar emails correctamente
✅ El AdminUsersPage funcionará en producción

Detalles técnicos:
- Enviado desde: '.config('mail.from.address').'
- Servidor SMTP: '.config('mail.mailers.smtp.host').'
- Puerto: '.config('mail.mailers.smtp.port').'
- Tiempo: '.now()->format('Y-m-d H:i:s T').'

¡Saludos!
Sistema de Testing BCommerce';

    // Send email using Laravel Mail facade directly
    Mail::raw($testEmailContent, function ($message) {
        $message->to('kevinvillajim@hotmail.com', 'Kevin Villacreses')
            ->subject('🧪 SMTP TEST - BCommerce Email System')
            ->from(config('mail.from.address'), config('mail.from.name'));
    });

    echo "📤 Email sent via Laravel Mail facade\n";
    echo "🎯 Target: kevinvillajim@hotmail.com\n";
    echo "📧 Subject: 🧪 SMTP TEST - BCommerce Email System\n";

    echo "\n🎉 SUCCESS: Email sending completed!\n";
    echo "📮 Check your inbox at kevinvillajim@hotmail.com\n";
    echo "📮 Also check spam/junk folder\n";
    echo "⏰ Email should arrive within 1-2 minutes\n";

    echo "\n✅ CONCLUSION:\n";
    echo "If you receive this email, it confirms that:\n";
    echo "1. SMTP configuration is working correctly\n";
    echo "2. The AdminUsersPage email functionality will work in production\n";
    echo "3. The issue was just the local database connection\n";
    echo "4. You can deploy the changes with confidence\n";

} catch (\Exception $e) {
    echo '💥 ERROR: '.$e->getMessage()."\n";
    echo '📄 File: '.$e->getFile().':'.$e->getLine()."\n";

    echo "\n❌ SMTP CONNECTION FAILED\n";
    echo "This indicates a real email configuration problem:\n\n";

    $errorMessage = $e->getMessage();

    if (strpos($errorMessage, 'Connection refused') !== false) {
        echo "🔧 ISSUE: Cannot connect to SMTP server\n";
        echo "- Check if mail.comersia.app is reachable\n";
        echo "- Verify port 465 is open\n";
        echo "- Confirm SSL/TLS settings\n";

    } elseif (strpos($errorMessage, 'Authentication failed') !== false) {
        echo "🔧 ISSUE: SMTP Authentication failed\n";
        echo "- Check SMTP username and password\n";
        echo "- Verify credentials with hosting provider\n";

    } elseif (strpos($errorMessage, 'timeout') !== false) {
        echo "🔧 ISSUE: Connection timeout\n";
        echo "- Check network connectivity\n";
        echo "- Verify firewall settings\n";

    } else {
        echo "🔧 ISSUE: General SMTP error\n";
        echo "- Check all SMTP configuration values\n";
        echo "- Contact hosting provider\n";
    }

    echo "\n📋 Troubleshooting steps:\n";
    echo "1. Verify SMTP credentials with hosting provider\n";
    echo "2. Test SMTP connection from server\n";
    echo "3. Check firewall and security groups\n";
    echo "4. Review Laravel logs for detailed errors\n";
}

echo "\n".str_repeat('=', 60)."\n";
echo "🏁 SMTP TEST COMPLETED\n";
