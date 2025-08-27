<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "🔍 DEBUGGING PRODUCTION EMAIL ISSUE\n";
echo "=" . str_repeat("=", 50) . "\n";

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "🌍 Environment: " . config('app.env') . "\n";
echo "🔗 App URL: " . config('app.url') . "\n";
echo "🐛 Debug Mode: " . (config('app.debug') ? 'ON' : 'OFF') . "\n";

echo "\n📧 MAIL CONFIGURATION CHECK:\n";
echo "- Driver: " . config('mail.default') . "\n";
echo "- Host: " . config('mail.mailers.smtp.host') . "\n";
echo "- Port: " . config('mail.mailers.smtp.port') . "\n";
echo "- Encryption: " . config('mail.mailers.smtp.encryption') . "\n";
echo "- Username: " . (config('mail.mailers.smtp.username') ? '***SET***' : 'NOT SET') . "\n";
echo "- Password: " . (config('mail.mailers.smtp.password') ? '***SET***' : 'NOT SET') . "\n";
echo "- From Address: " . config('mail.from.address') . "\n";

echo "\n🛡️ ROUTE PROTECTION CHECK:\n";

// Check if route is properly registered
$routes = app('router')->getRoutes();
$adminEmailRoute = null;

foreach ($routes as $route) {
    if ($route->uri() === 'api/admin/configurations/mail/send-custom' && 
        in_array('POST', $route->methods())) {
        $adminEmailRoute = $route;
        break;
    }
}

if ($adminEmailRoute) {
    echo "✅ Route found: POST /api/admin/configurations/mail/send-custom\n";
    echo "🛡️ Middleware: " . implode(', ', $adminEmailRoute->middleware()) . "\n";
    echo "🎯 Controller: " . $adminEmailRoute->getActionName() . "\n";
} else {
    echo "❌ Route NOT found: POST /api/admin/configurations/mail/send-custom\n";
    echo "🔍 This could be the problem!\n";
}

echo "\n🔒 JWT CONFIGURATION CHECK:\n";
echo "- JWT Secret: " . (config('jwt.secret') ? '***SET***' : 'NOT SET') . "\n";
echo "- JWT TTL: " . config('jwt.ttl', 'default') . " minutes\n";

echo "\n📋 DATABASE CONNECTION CHECK:\n";
try {
    $dbConnection = \Illuminate\Support\Facades\DB::connection();
    $dbConnection->getPdo();
    echo "✅ Database connection: OK\n";
    
    // Check if admin users exist
    $adminCount = \Illuminate\Support\Facades\DB::table('users')->where('is_admin', true)->count();
    echo "👤 Admin users found: {$adminCount}\n";
    
} catch (\Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n🌐 CORS CONFIGURATION CHECK:\n";
$corsConfig = config('cors');
if ($corsConfig) {
    echo "✅ CORS config loaded\n";
    echo "- Paths: " . implode(', ', $corsConfig['paths'] ?? []) . "\n";
    echo "- Allowed Origins: " . implode(', ', $corsConfig['allowed_origins'] ?? []) . "\n";
    echo "- Allowed Methods: " . implode(', ', $corsConfig['allowed_methods'] ?? []) . "\n";
} else {
    echo "❌ CORS config not found\n";
}

echo "\n🧪 TESTING EMAIL SEND DIRECTLY:\n";

try {
    // Try to send a test email directly
    echo "📤 Attempting to send test email...\n";
    
    use Illuminate\Support\Facades\Mail;
    
    Mail::raw('Test email from production debug script', function ($message) {
        $message->to('kevinvillajim@hotmail.com')
                ->subject('🔍 Production Debug Test')
                ->from(config('mail.from.address'), 'BCommerce Debug');
    });
    
    echo "✅ Email sent successfully via Mail facade\n";
    echo "📧 Check kevinvillajim@hotmail.com for test email\n";
    
} catch (\Exception $e) {
    echo "❌ Email sending failed: " . $e->getMessage() . "\n";
    echo "📄 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    if (strpos($e->getMessage(), 'Connection') !== false) {
        echo "\n🔧 SMTP CONNECTION ISSUE:\n";
        echo "- Check if mail server 'mail.comersia.app' is accessible from production server\n";
        echo "- Verify firewall allows SMTP connections on port 465\n";
        echo "- Confirm SMTP credentials are correct\n";
    }
}

echo "\n🔍 POSSIBLE CAUSES OF NETWORK ERROR:\n";
echo "1. Route not properly registered after deployment\n";
echo "2. Middleware blocking the request\n";
echo "3. CORS configuration issue\n";
echo "4. JWT authentication failing\n";
echo "5. Server timeout during email sending\n";
echo "6. SMTP server connection issue\n";

echo "\n💡 IMMEDIATE DEBUGGING STEPS:\n";
echo "1. Check Laravel logs: storage/logs/laravel.log\n";
echo "2. Check web server logs (Apache/Nginx)\n";
echo "3. Test route directly with curl/Postman\n";
echo "4. Verify JWT token is valid\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "🏁 PRODUCTION DEBUG COMPLETED\n";