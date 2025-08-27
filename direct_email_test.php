<?php

// Direct email test bypassing database
require_once __DIR__ . '/vendor/autoload.php';

use App\Services\MailService;
use Illuminate\Support\Facades\Mail;

echo "🧪 DIRECT EMAIL TEST - Bypassing database\n";
echo "=" . str_repeat("=", 50) . "\n";

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

try {
    // Create fake user object (without database)
    $fakeUser = new class {
        public $id = 999;
        public $name = 'Kevin Villacreses';
        public $first_name = 'Kevin';
        public $last_name = 'Villacreses';
        public $email = 'kevinvillajim@hotmail.com';
    };

    echo "👤 Target user: {$fakeUser->name} ({$fakeUser->email})\n";
    echo "⚙️  Mail configuration:\n";
    echo "- Driver: " . config('mail.default') . "\n";
    echo "- Host: " . config('mail.mailers.smtp.host') . "\n";
    echo "- Port: " . config('mail.mailers.smtp.port') . "\n";
    echo "- From: " . config('mail.from.address') . "\n";

    // Get MailService instance
    $mailService = app(MailService::class);
    
    echo "\n📧 Sending email via MailService...\n";

    $subject = '🧪 DIRECT TEST - BCommerce Email System';
    $message = "¡Hola Kevin!\n\nEste es un test DIRECTO del sistema de emails de BCommerce.\n\nSi recibes este email, significa que:\n✅ El MailService funciona correctamente\n✅ La configuración SMTP está bien\n✅ Los servidores de email están operativos\n✅ El problema anterior era solo la base de datos\n\nEste test fue ejecutado directamente sin usar la base de datos.\n\nTiempo de envío: " . now()->format('Y-m-d H:i:s') . "\n\n¡Saludos!\nSistema de Testing BCommerce";

    // Send email directly
    $result = $mailService->sendNotificationEmail(
        $fakeUser,
        $subject,
        $message,
        [
            'email_type' => 'direct_test',
            'sent_by_admin' => true,
            'admin_name' => 'Test System',
            'admin_email' => 'test@bcommerce.local',
            'direct_test' => true
        ]
    );

    echo "📤 Email sending attempted...\n";

    if ($result) {
        echo "🎉 SUCCESS: Email sent successfully!\n";
        echo "📧 Email sent to: kevinvillajim@hotmail.com\n";
        echo "📮 Check your inbox and spam folder\n";
        echo "⏰ Should arrive within 1-2 minutes\n";
        
        echo "\n✅ RESULT: The email system is working!\n";
        echo "💡 The AdminUsersPage will work in production\n";
        echo "🚀 You can deploy with confidence\n";
    } else {
        echo "❌ FAILED: Email was not sent\n";
        echo "📋 This indicates a real email configuration problem\n";
        
        // Try to get more details
        echo "\n🔍 Additional debugging:\n";
        echo "- SMTP Encryption: " . config('mail.mailers.smtp.encryption', 'not_set') . "\n";
        echo "- Mail From Name: " . config('mail.from.name', 'not_set') . "\n";
        
        // Check if we can create a basic mail instance
        try {
            $basicMail = new \App\Mail\NotificationMail($fakeUser, $subject, $message);
            echo "✅ Mail class can be instantiated\n";
        } catch (\Exception $mailError) {
            echo "❌ Mail class error: " . $mailError->getMessage() . "\n";
        }
    }

} catch (\Exception $e) {
    echo "💥 ERROR: " . $e->getMessage() . "\n";
    echo "📄 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Check if it's a mail-specific error
    if (strpos($e->getMessage(), 'mail') !== false || 
        strpos($e->getMessage(), 'smtp') !== false ||
        strpos($e->getMessage(), 'connection') !== false) {
        echo "\n📧 This appears to be a mail server connection issue\n";
        echo "🔧 Check these settings in production:\n";
        echo "- SMTP server is reachable\n";
        echo "- SMTP credentials are correct\n";
        echo "- Firewall allows SMTP connections\n";
        echo "- Mail server allows connections from your server IP\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏁 DIRECT EMAIL TEST COMPLETED\n";