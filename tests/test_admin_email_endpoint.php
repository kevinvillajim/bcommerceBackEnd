<?php

require_once __DIR__.'/vendor/autoload.php';

echo "🧪 TESTING ADMIN EMAIL ENDPOINT - Simulating AdminUsersPage\n";
echo '='.str_repeat('=', 60)."\n";

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

try {
    echo "🔍 Step 1: Environment Check\n";
    echo '- Environment: '.config('app.env')."\n";
    echo '- App URL: '.config('app.url')."\n";
    echo '- Debug: '.(config('app.debug') ? 'ON' : 'OFF')."\n";

    echo "\n👤 Step 2: Finding Admin User\n";
    $admin = \App\Models\User::where('is_admin', true)->first();
    if (! $admin) {
        echo "❌ No admin user found\n";
        exit(1);
    }
    echo "✅ Admin found: {$admin->name} ({$admin->email})\n";

    echo "\n👤 Step 3: Finding Target User\n";
    $targetUser = \App\Models\User::where('email', 'kevinvillajim@hotmail.com')->first();
    if (! $targetUser) {
        echo "❌ Target user not found, creating one...\n";
        $targetUser = \App\Models\User::create([
            'name' => 'Kevin Villacreses',
            'first_name' => 'Kevin',
            'last_name' => 'Villacreses',
            'email' => 'kevinvillajim@hotmail.com',
            'password' => bcrypt('test123'),
            'email_verified_at' => now(),
        ]);
    }
    echo "✅ Target user: {$targetUser->name} ({$targetUser->email})\n";

    echo "\n🔑 Step 4: JWT Authentication\n";
    $token = auth('api')->login($admin);
    if (! $token) {
        echo "❌ JWT token generation failed\n";
        exit(1);
    }
    echo '✅ JWT token generated: '.substr($token, 0, 20)."...\n";

    echo "\n🛡️ Step 5: Route Check\n";
    $routes = app('router')->getRoutes();
    $found = false;

    foreach ($routes as $route) {
        if ($route->uri() === 'api/admin/configurations/mail/send-custom' &&
            in_array('POST', $route->methods())) {
            echo "✅ Route found: POST /api/admin/configurations/mail/send-custom\n";
            echo '🛡️ Middleware: '.implode(', ', $route->middleware())."\n";
            echo '🎯 Action: '.$route->getActionName()."\n";
            $found = true;
            break;
        }
    }

    if (! $found) {
        echo "❌ Route NOT FOUND! This is the problem.\n";
        echo "🔧 Check if routes/api.php was properly deployed\n";
        exit(1);
    }

    echo "\n📧 Step 6: Testing Email Endpoint Directly\n";

    // Simulate the exact request from AdminUsersPage
    $emailData = [
        'user_id' => $targetUser->id,
        'subject' => '🧪 ENDPOINT TEST - BCommerce Admin Panel',
        'message' => "¡Hola Kevin!\n\nEste es un test directo del endpoint de email admin.\n\nSi recibes este email, significa que:\n✅ El endpoint funciona correctamente\n✅ El middleware está bien configurado\n✅ La autenticación JWT funciona\n✅ El sistema de emails está operativo\n\n¡Saludos!\nSistema de Testing BCommerce",
        'email_type' => 'endpoint_test',
    ];

    echo "📋 Email data:\n";
    echo "- User ID: {$emailData['user_id']}\n";
    echo "- Subject: {$emailData['subject']}\n";
    echo "- Email Type: {$emailData['email_type']}\n";

    echo "\n🌐 Step 7: Making HTTP Request (Internal)\n";

    // Create a fake request to simulate the frontend call
    $request = \Illuminate\Http\Request::create(
        '/api/admin/configurations/mail/send-custom',
        'POST',
        $emailData,
        [], // cookies
        [], // files
        ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']
    );

    // Get the controller
    $controller = new \App\Http\Controllers\Auth\EmailVerificationController(
        app(\App\Services\EmailVerificationService::class),
        app(\App\Services\MailService::class)
    );

    // Set the authenticated user in the request
    $request->setUserResolver(function () use ($admin) {
        return $admin;
    });

    echo "📤 Calling sendCustomEmail method...\n";

    $response = $controller->sendCustomEmail($request);

    echo "\n📡 Step 8: Response Analysis\n";
    $responseData = $response->getData(true);
    $statusCode = $response->getStatusCode();

    echo "- Status Code: {$statusCode}\n";
    echo '- Response: '.json_encode($responseData, JSON_PRETTY_PRINT)."\n";

    if ($statusCode === 200 && ($responseData['status'] ?? '') === 'success') {
        echo "\n🎉 SUCCESS: Email sent successfully!\n";
        echo "📧 Check kevinvillajim@hotmail.com for the test email\n";
        echo "✅ The AdminUsersPage should work in production\n";
    } else {
        echo "\n❌ FAILED: Email was not sent\n";
        echo "📋 Check the response above for error details\n";

        if (isset($responseData['message'])) {
            echo "💬 Error message: {$responseData['message']}\n";
        }
    }

} catch (\Exception $e) {
    echo "\n💥 ERROR: ".$e->getMessage()."\n";
    echo '📄 File: '.$e->getFile().':'.$e->getLine()."\n";

    // Check specific error types
    if (strpos($e->getMessage(), 'Connection') !== false) {
        echo "\n🔧 SMTP CONNECTION ISSUE:\n";
        echo "This is likely the mail server problem you suspected.\n";
        echo "- Check if 'mail.comersia.app' is accessible from production server\n";
        echo "- Verify SMTP credentials in production environment\n";
        echo "- Test SMTP connection manually\n";

    } elseif (strpos($e->getMessage(), 'Auth') !== false) {
        echo "\n🔧 AUTHENTICATION ISSUE:\n";
        echo "- Check JWT configuration\n";
        echo "- Verify admin user permissions\n";

    } elseif (strpos($e->getMessage(), 'Route') !== false) {
        echo "\n🔧 ROUTING ISSUE:\n";
        echo "- Check if api.php routes were deployed correctly\n";
        echo "- Clear route cache: php artisan route:clear\n";
    }
}

echo "\n".str_repeat('=', 60)."\n";
echo "🏁 ENDPOINT TEST COMPLETED\n";
