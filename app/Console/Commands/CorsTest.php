<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CorsTest extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cors:test {--url=http://localhost:8000 : Base URL to test}';

    /**
     * The console command description.
     */
    protected $description = 'Exhaustive CORS testing for BCommerce API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseUrl = $this->option('url');

        $this->info('🔍 CORS Exhaustive Test - BCommerce');
        $this->info('=====================================');

        // Test 1: Configuration display
        $this->showCorsConfiguration();

        // Test 2: Preflight OPTIONS requests
        $this->testPreflightRequests($baseUrl);

        // Test 3: Actual CORS requests
        $this->testCorsRequests($baseUrl);

        // Test 4: Check middleware stack
        $this->checkMiddlewareStack();

        // Test 5: Test with curl commands
        $this->generateCurlTests($baseUrl);

        return Command::SUCCESS;
    }

    private function showCorsConfiguration()
    {
        $this->info("\n📋 Current CORS Configuration:");

        $corsConfig = config('cors');

        $this->table(['Setting', 'Value'], [
            ['paths', implode(', ', $corsConfig['paths'])],
            ['allowed_methods', implode(', ', $corsConfig['allowed_methods'])],
            ['allowed_origins', implode(', ', $corsConfig['allowed_origins'])],
            ['allowed_origins_patterns', implode(', ', $corsConfig['allowed_origins_patterns'])],
            ['allowed_headers', implode(', ', $corsConfig['allowed_headers'])],
            ['exposed_headers', implode(', ', $corsConfig['exposed_headers'])],
            ['max_age', $corsConfig['max_age']],
            ['supports_credentials', $corsConfig['supports_credentials'] ? 'true' : 'false'],
        ]);
    }

    private function test_preflight_requests($baseUrl)
    {
        $this->info("\n✈️ Testing Preflight OPTIONS Requests:");

        $testCases = [
            [
                'url' => "$baseUrl/api/products",
                'origin' => 'http://localhost:3000',
                'method' => 'GET',
            ],
            [
                'url' => "$baseUrl/api/auth/login",
                'origin' => 'https://comersia.app',
                'method' => 'POST',
            ],
            [
                'url' => "$baseUrl/api/cart",
                'origin' => 'http://127.0.0.1:3000',
                'method' => 'POST',
            ],
        ];

        foreach ($testCases as $test) {
            $this->testSinglePreflight($test['url'], $test['origin'], $test['method']);
        }
    }

    private function test_single_preflight($url, $origin, $method)
    {
        try {
            $response = Http::withHeaders([
                'Origin' => $origin,
                'Access-Control-Request-Method' => $method,
                'Access-Control-Request-Headers' => 'Content-Type, Authorization',
            ])->options($url);

            $headers = $response->headers();

            $this->info("\n🎯 Testing: $url (Origin: $origin)");
            $this->info('Status: '.$response->status());

            $corsHeaders = [
                'Access-Control-Allow-Origin' => $headers['Access-Control-Allow-Origin'][0] ?? 'NOT SET',
                'Access-Control-Allow-Methods' => $headers['Access-Control-Allow-Methods'][0] ?? 'NOT SET',
                'Access-Control-Allow-Headers' => $headers['Access-Control-Allow-Headers'][0] ?? 'NOT SET',
                'Access-Control-Allow-Credentials' => $headers['Access-Control-Allow-Credentials'][0] ?? 'NOT SET',
            ];

            foreach ($corsHeaders as $header => $value) {
                if ($value === 'NOT SET') {
                    $this->error("  ❌ $header: NOT SET");
                } else {
                    $this->line("  ✅ $header: $value");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Failed to test $url: ".$e->getMessage());
        }
    }

    private function test_cors_requests($baseUrl)
    {
        $this->info("\n🌐 Testing Actual CORS Requests:");

        $testRequests = [
            [
                'method' => 'GET',
                'url' => "$baseUrl/api/products",
                'origin' => 'http://localhost:3000',
            ],
            [
                'method' => 'POST',
                'url' => "$baseUrl/api/auth/login",
                'origin' => 'https://comersia.app',
                'body' => ['email' => 'test@test.com', 'password' => 'password'],
            ],
        ];

        foreach ($testRequests as $test) {
            $this->testSingleRequest($test);
        }
    }

    private function test_single_request($test)
    {
        try {
            $request = Http::withHeaders([
                'Origin' => $test['origin'],
                'Content-Type' => 'application/json',
            ]);

            if ($test['method'] === 'GET') {
                $response = $request->get($test['url']);
            } else {
                $response = $request->post($test['url'], $test['body'] ?? []);
            }

            $this->info("\n🌍 {$test['method']} {$test['url']} (Origin: {$test['origin']})");
            $this->info('Status: '.$response->status());

            $corsHeaders = $response->headers();
            $allowOrigin = $corsHeaders['Access-Control-Allow-Origin'][0] ?? 'NOT SET';

            if ($allowOrigin === 'NOT SET') {
                $this->error('  ❌ Access-Control-Allow-Origin: NOT SET');
            } else {
                $this->line("  ✅ Access-Control-Allow-Origin: $allowOrigin");
            }

        } catch (\Exception $e) {
            $this->error('❌ Request failed: '.$e->getMessage());
        }
    }

    private function checkMiddlewareStack()
    {
        $this->info("\n🔧 Middleware Stack Analysis:");

        // Check global middleware
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $reflection = new \ReflectionClass($kernel);

        try {
            $middlewareProperty = $reflection->getProperty('middleware');
            $middlewareProperty->setAccessible(true);
            $globalMiddleware = $middlewareProperty->getValue($kernel);

            $this->info('Global Middleware:');
            foreach ($globalMiddleware as $middleware) {
                if (str_contains($middleware, 'HandleCors')) {
                    $this->line("  ✅ $middleware (CORS FOUND)");
                } else {
                    $this->line("  - $middleware");
                }
            }

            // Check API middleware group
            $middlewareGroupsProperty = $reflection->getProperty('middlewareGroups');
            $middlewareGroupsProperty->setAccessible(true);
            $middlewareGroups = $middlewareGroupsProperty->getValue($kernel);

            $this->info("\nAPI Middleware Group:");
            if (isset($middlewareGroups['api'])) {
                foreach ($middlewareGroups['api'] as $middleware) {
                    $this->line("  - $middleware");
                }
            }

        } catch (\Exception $e) {
            $this->error('Could not analyze middleware stack: '.$e->getMessage());
        }
    }

    private function generateCurlTests($baseUrl)
    {
        $this->info("\n🧪 Curl Commands for Manual Testing:");
        $this->info('Copy and paste these commands in terminal:');

        $curlCommands = [
            // Preflight test
            "curl -X OPTIONS '$baseUrl/api/products' \\",
            "  -H 'Origin: http://localhost:3000' \\",
            "  -H 'Access-Control-Request-Method: GET' \\",
            "  -H 'Access-Control-Request-Headers: Content-Type, Authorization' \\",
            '  -v',
            '',
            // Actual request test
            "curl -X GET '$baseUrl/api/products' \\",
            "  -H 'Origin: http://localhost:3000' \\",
            "  -H 'Content-Type: application/json' \\",
            '  -v',
            '',
            // POST request test
            "curl -X POST '$baseUrl/api/auth/login' \\",
            "  -H 'Origin: https://comersia.app' \\",
            "  -H 'Content-Type: application/json' \\",
            "  -d '{\"email\":\"test@test.com\",\"password\":\"password\"}' \\",
            '  -v',
        ];

        foreach ($curlCommands as $command) {
            $this->line($command);
        }

        $this->info("\n💡 Look for these headers in the response:");
        $this->line('  - Access-Control-Allow-Origin');
        $this->line('  - Access-Control-Allow-Methods');
        $this->line('  - Access-Control-Allow-Headers');
        $this->line('  - Access-Control-Allow-Credentials');
    }
}
