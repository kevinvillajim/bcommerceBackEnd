<?php

require_once __DIR__.'/vendor/autoload.php';

echo "🧪 REAL EMAIL TEST - No shortcuts, testing actual system\n";
echo '='.str_repeat('=', 60)."\n";

// Bootstrap Laravel application
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

try {
    // Step 1: Find or create admin user
    echo "👤 Step 1: Setting up admin user...\n";

    $admin = \App\Models\User::where('is_admin', true)->first();
    if (! $admin) {
        $admin = \App\Models\User::create([
            'name' => 'Test Admin',
            'email' => 'admin@bcommerce.test',
            'password' => bcrypt('password123'),
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);
        echo "✅ Created admin user: {$admin->email}\n";
    } else {
        echo "✅ Found existing admin: {$admin->email}\n";
    }

    // Step 2: Find or create target user (Kevin)
    echo "👤 Step 2: Setting up target user...\n";

    $kevin = \App\Models\User::where('email', 'kevinvillajim@hotmail.com')->first();
    if (! $kevin) {
        $kevin = \App\Models\User::create([
            'name' => 'Kevin Villacreses',
            'first_name' => 'Kevin',
            'last_name' => 'Villacreses',
            'email' => 'kevinvillajim@hotmail.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);
        echo "✅ Created Kevin user: {$kevin->email}\n";
    } else {
        echo "✅ Found existing Kevin: {$kevin->email}\n";
    }

    // Step 3: Generate JWT token for admin
    echo "🔑 Step 3: Generating JWT token...\n";

    $token = auth('api')->login($admin);
    if (! $token) {
        throw new Exception('Failed to generate JWT token for admin');
    }
    echo "✅ JWT token generated successfully\n";

    // Step 4: Prepare email data (exactly like AdminUsersPage.tsx)
    echo "📧 Step 4: Preparing email data...\n";

    $emailData = [
        'user_id' => $kevin->id,
        'subject' => '🧪 REAL TEST - BCommerce Admin Panel Email',
        'message' => "¡Hola Kevin!\n\nEste es un email REAL enviado desde el sistema de testing de BCommerce.\n\nSi recibes este email, significa que:\n✅ El endpoint está funcionando correctamente\n✅ El middleware de admin está operativo\n✅ El sistema de envío de emails funciona\n✅ La configuración de correo está correcta\n\nEste email fue enviado automáticamente por el script de testing real.\n\n¡Saludos!\nSistema BCommerce",
        'email_type' => 'test_real',
    ];

    echo "📋 Email data prepared:\n";
    echo "- To: {$kevin->email}\n";
    echo "- Subject: {$emailData['subject']}\n";
    echo "- Type: {$emailData['email_type']}\n";

    // Step 5: Make actual HTTP request to the endpoint
    echo "\n🌐 Step 5: Making HTTP request to endpoint...\n";

    // Create HTTP client
    $client = new \GuzzleHttp\Client([
        'base_uri' => config('app.url').'/api/',
        'timeout' => 30,
        'verify' => false, // For local testing
    ]);

    $response = $client->post('admin/configurations/mail/send-custom', [
        'headers' => [
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'json' => $emailData,
    ]);

    $statusCode = $response->getStatusCode();
    $responseBody = json_decode($response->getBody()->getContents(), true);

    echo "📡 HTTP Response received:\n";
    echo "- Status Code: {$statusCode}\n";
    echo '- Response: '.json_encode($responseBody, JSON_PRETTY_PRINT)."\n";

    // Step 6: Analyze results
    echo "\n🔍 Step 6: Analyzing results...\n";

    if ($statusCode === 200 && isset($responseBody['status']) && $responseBody['status'] === 'success') {
        echo "🎉 SUCCESS: Email sent successfully!\n";
        echo "📧 Check your inbox at kevinvillajim@hotmail.com\n";
        echo "📮 Also check spam/junk folder\n";

        if (isset($responseBody['data'])) {
            echo "📋 Email details:\n";
            echo '- Recipient: '.json_encode($responseBody['data']['recipient'] ?? [])."\n";
            echo '- Sent at: '.($responseBody['data']['sent_at'] ?? 'unknown')."\n";
        }
    } else {
        echo "❌ FAILED: Email was not sent\n";
        echo "📋 Response details:\n";
        echo json_encode($responseBody, JSON_PRETTY_PRINT)."\n";
    }

} catch (\GuzzleHttp\Exception\ClientException $e) {
    $response = $e->getResponse();
    $statusCode = $response->getStatusCode();
    $errorBody = json_decode($response->getBody()->getContents(), true);

    echo "❌ HTTP CLIENT ERROR ({$statusCode}):\n";
    echo json_encode($errorBody, JSON_PRETTY_PRINT)."\n";

} catch (\GuzzleHttp\Exception\RequestException $e) {
    echo '❌ REQUEST ERROR: '.$e->getMessage()."\n";

} catch (\Exception $e) {
    echo '💥 SYSTEM ERROR: '.$e->getMessage()."\n";
    echo '📄 File: '.$e->getFile().':'.$e->getLine()."\n";

    // Check common issues
    echo "\n🔍 Debugging common issues:\n";

    // Check mail configuration
    echo '📧 Mail driver: '.config('mail.default')."\n";
    echo '📧 SMTP host: '.config('mail.mailers.smtp.host')."\n";
    echo '📧 SMTP port: '.config('mail.mailers.smtp.port')."\n";
    echo '📧 From address: '.config('mail.from.address')."\n";
}

echo "\n".str_repeat('=', 60)."\n";
echo "🏁 REAL EMAIL TEST COMPLETED\n";
echo "\nIf no email arrived, we have a real problem to solve!\n";
