<?php

echo "🔧 TESTING MIDDLEWARE FIXES DEPLOYMENT\n";
echo '='.str_repeat('=', 50)."\n";

// Check if our changes are actually in the deployed code
$jwtMiddlewarePath = __DIR__.'/app/Http/Middleware/JwtMiddleware.php';

if (file_exists($jwtMiddlewarePath)) {
    $content = file_get_contents($jwtMiddlewarePath);

    echo "📁 JwtMiddleware file found: ✅\n";

    // Check for our CORS fix
    if (strpos($content, 'CORS FIX: Allow OPTIONS requests') !== false) {
        echo "✅ CORS FIX: OPTIONS request handling found\n";
    } else {
        echo "❌ CORS FIX: OPTIONS request handling NOT FOUND\n";
    }

    // Check for our user resolver fix
    if (strpos($content, 'setUserResolver') !== false) {
        echo "✅ USER FIX: setUserResolver found\n";
    } else {
        echo "❌ USER FIX: setUserResolver NOT FOUND\n";

        // Check if old method is still being used
        if (strpos($content, "request->merge(['authenticated_user'") !== false) {
            echo "⚠️  OLD METHOD: Still using merge() method\n";
        }
    }

    // Show the handle method
    echo "\n📋 Current JwtMiddleware handle method:\n";
    preg_match('/public function handle\(.*?\n    \}/s', $content, $matches);
    if ($matches[0]) {
        echo "```php\n".trim($matches[0])."\n```\n";
    }

} else {
    echo "❌ JwtMiddleware file NOT FOUND at: $jwtMiddlewarePath\n";
}

echo "\n💡 DEPLOYMENT VERIFICATION:\n";
echo "If any fixes show as NOT FOUND, the production server needs to be updated with:\n";
echo "1. Latest JwtMiddleware.php changes\n";
echo "2. Laravel route cache cleared (php artisan route:clear)\n";
echo "3. Application cache cleared (php artisan cache:clear)\n";

echo "\n".str_repeat('=', 60)."\n";
echo "🏁 MIDDLEWARE DEPLOYMENT TEST COMPLETED\n";
