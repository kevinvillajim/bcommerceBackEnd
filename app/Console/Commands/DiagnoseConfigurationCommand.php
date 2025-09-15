<?php

namespace App\Console\Commands;

use App\Services\ConfigurationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DiagnoseConfigurationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'config:diagnose {--test-all} {--fix}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose configuration system issues and optionally fix them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== CONFIGURATION SYSTEM DIAGNOSTICS ===');

        // 1. Check environment
        $this->info("\n1. Environment Check:");
        $this->table(
            ['Setting', 'Value'],
            [
                ['APP_ENV', config('app.env')],
                ['APP_DEBUG', config('app.debug') ? 'true' : 'false'],
                ['DB_CONNECTION', config('database.default')],
                ['CACHE_DRIVER', config('cache.default')],
                ['QUEUE_CONNECTION', config('queue.default')],
            ]
        );

        // 2. Test database connection
        $this->info("\n2. Database Connection Test:");
        $dbAvailable = false;
        try {
            DB::connection()->getPdo();
            $this->info('✓ Database connection successful');
            $dbAvailable = true;

            // Check configurations table
            $configCount = DB::table('configurations')->count();
            $this->info("  Configurations in database: {$configCount}");

            // Show sample configurations
            if ($configCount > 0) {
                $samples = DB::table('configurations')->limit(5)->get(['key', 'value']);
                $this->info('  Sample configurations:');
                foreach ($samples as $sample) {
                    $value = strlen($sample->value) > 50
                        ? substr($sample->value, 0, 50).'...'
                        : $sample->value;
                    $this->line("    - {$sample->key}: {$value}");
                }
            }
        } catch (\Exception $e) {
            $this->error('✗ Database connection failed: '.$e->getMessage());
        }

        // 3. Test cache system
        $this->info("\n3. Cache System Test:");
        $cacheAvailable = false;
        try {
            $testKey = 'config_diagnose_test_'.time();
            Cache::put($testKey, 'test_value', 60);
            $retrieved = Cache::get($testKey);

            if ($retrieved === 'test_value') {
                $this->info('✓ Cache system working properly');
                $cacheAvailable = true;
                Cache::forget($testKey);
            } else {
                $this->warn('⚠ Cache system available but not working as expected');
            }
        } catch (\Exception $e) {
            $this->error('✗ Cache system failed: '.$e->getMessage());
        }

        // 4. Test ConfigurationService
        $this->info("\n4. ConfigurationService Test:");
        try {
            $configService = app(ConfigurationService::class);
            $this->info('✓ ConfigurationService instantiated');

            // Get diagnostics
            $diagnostics = $configService->getDiagnostics();
            $this->table(
                ['Diagnostic', 'Status'],
                [
                    ['Database Available', $diagnostics['database_available'] ? '✓ Yes' : '✗ No'],
                    ['Cache Available', $diagnostics['cache_available'] ? '✓ Yes' : '✗ No'],
                    ['Cache Driver', $diagnostics['cache_driver']],
                    ['Memory Cache Count', $diagnostics['memory_cache_count']],
                    ['Defaults Count', $diagnostics['defaults_count']],
                ]
            );

            // Test getting a configuration
            $this->info("\n  Testing configuration retrieval:");
            $testKeys = [
                'email.smtpHost',
                'email.senderEmail',
                'security.passwordMinLength',
                'business.taxRate',
            ];

            foreach ($testKeys as $key) {
                $value = $configService->getConfig($key, 'NOT_SET');
                $source = $this->determineSource($key, $value, $diagnostics);
                $this->line("    {$key}: {$value} (from {$source})");
            }

        } catch (\Exception $e) {
            $this->error('✗ ConfigurationService failed: '.$e->getMessage());
        }

        // 5. Test all configurations if requested
        if ($this->option('test-all')) {
            $this->info("\n5. Testing All Configuration Keys:");
            $this->testAllConfigurations();
        }

        // 6. Fix issues if requested
        if ($this->option('fix')) {
            $this->info("\n6. Attempting to Fix Issues:");
            $this->fixIssues($dbAvailable, $cacheAvailable);
        }

        // 7. Recommendations
        $this->info("\n7. Recommendations:");
        $this->provideRecommendations($dbAvailable, $cacheAvailable);

        $this->info("\n=== END DIAGNOSTICS ===\n");

        return Command::SUCCESS;
    }

    /**
     * Test all configuration keys
     */
    protected function testAllConfigurations(): void
    {
        $configService = app(ConfigurationService::class);

        // List of all known configuration keys
        $allKeys = [
            // Email
            'email.smtpHost', 'email.smtpPort', 'email.smtpUsername',
            'email.smtpEncryption', 'email.senderEmail', 'email.senderName',
            'email.supportEmail', 'email.verificationTimeout',

            // Security
            'security.passwordMinLength', 'security.passwordRequireSpecial',
            'security.passwordRequireUppercase', 'security.passwordRequireNumbers',

            // Business
            'business.taxRate', 'business.taxName', 'business.shippingCost',
            'business.freeShippingThreshold', 'business.platformCommissionRate',

            // System
            'system.maintenanceMode', 'system.debugMode', 'system.timezone',
        ];

        $results = [];
        foreach ($allKeys as $key) {
            try {
                $value = $configService->getConfig($key);
                $results[] = [
                    'key' => $key,
                    'value' => $this->formatValue($value),
                    'status' => '✓',
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'key' => $key,
                    'value' => 'ERROR',
                    'status' => '✗',
                ];
            }
        }

        $this->table(['Key', 'Value', 'Status'], $results);
    }

    /**
     * Fix identified issues
     */
    protected function fixIssues(bool $dbAvailable, bool $cacheAvailable): void
    {
        $fixed = 0;

        // Clear cache if available
        if ($cacheAvailable) {
            try {
                Cache::flush();
                $this->info('✓ Cache cleared');
                $fixed++;
            } catch (\Exception $e) {
                $this->error('✗ Could not clear cache: '.$e->getMessage());
            }
        }

        // Clear configuration cache
        try {
            $this->call('config:clear');
            $this->info('✓ Configuration cache cleared');
            $fixed++;
        } catch (\Exception $e) {
            $this->error('✗ Could not clear config cache: '.$e->getMessage());
        }

        // Reset ConfigurationService cache
        try {
            $configService = app(ConfigurationService::class);
            $configService->clearCache();
            $this->info('✓ ConfigurationService cache cleared');
            $fixed++;
        } catch (\Exception $e) {
            $this->error('✗ Could not clear ConfigurationService cache: '.$e->getMessage());
        }

        // Create default configurations in database if missing
        if ($dbAvailable) {
            try {
                $defaults = [
                    'email.smtpHost' => env('MAIL_HOST', 'mail.comersia.app'),
                    'email.smtpPort' => env('MAIL_PORT', 465),
                    'email.smtpUsername' => env('MAIL_USERNAME', 'info@comersia.app'),
                    'email.smtpEncryption' => env('MAIL_ENCRYPTION', 'ssl'),
                    'email.senderEmail' => env('MAIL_FROM_ADDRESS', 'info@comersia.app'),
                    'email.senderName' => env('MAIL_FROM_NAME', 'Comersia App'),
                ];

                foreach ($defaults as $key => $value) {
                    $exists = DB::table('configurations')->where('key', $key)->exists();
                    if (! $exists) {
                        DB::table('configurations')->insert([
                            'key' => $key,
                            'value' => $value,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $this->info("✓ Created default configuration: {$key}");
                        $fixed++;
                    }
                }
            } catch (\Exception $e) {
                $this->error('✗ Could not create default configurations: '.$e->getMessage());
            }
        }

        $this->info("\nFixed {$fixed} issue(s)");
    }

    /**
     * Provide recommendations based on diagnostics
     */
    protected function provideRecommendations(bool $dbAvailable, bool $cacheAvailable): void
    {
        $recommendations = [];

        if (! $dbAvailable) {
            $recommendations[] = '⚠ Database is not available. Check your database credentials and connection.';
            $recommendations[] = '  The system will use environment variables and defaults as fallback.';
        }

        if (! $cacheAvailable) {
            $recommendations[] = '⚠ Cache system is not working properly.';
            $recommendations[] = '  Consider using Redis or Memcached for better performance.';
        }

        if (config('app.env') === 'production') {
            if (config('app.debug')) {
                $recommendations[] = '⚠ Debug mode is ON in production. Set APP_DEBUG=false';
            }

            if (config('cache.default') === 'array') {
                $recommendations[] = "⚠ Using 'array' cache driver in production. Use Redis or file cache.";
            }
        }

        if (env('MAIL_USE_ENV_ONLY') !== 'true' && ! $dbAvailable) {
            $recommendations[] = '⚠ Consider setting MAIL_USE_ENV_ONLY=true to avoid database dependency for mail.';
        }

        if (empty($recommendations)) {
            $this->info('✓ No issues detected. System is configured correctly.');
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line($recommendation);
            }
        }
    }

    /**
     * Determine the source of a configuration value
     */
    protected function determineSource(string $key, $value, array $diagnostics): string
    {
        // Check if it's from environment
        $envMappings = [
            'email.smtpHost' => 'MAIL_HOST',
            'email.smtpPort' => 'MAIL_PORT',
            'email.senderEmail' => 'MAIL_FROM_ADDRESS',
        ];

        if (isset($envMappings[$key]) && env($envMappings[$key]) == $value) {
            return 'env';
        }

        // Check if database is available
        if ($diagnostics['database_available']) {
            try {
                $dbValue = DB::table('configurations')->where('key', $key)->value('value');
                if ($dbValue == $value) {
                    return 'database';
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return 'default';
    }

    /**
     * Format value for display
     */
    protected function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_null($value)) {
            return 'null';
        }

        $stringValue = (string) $value;

        // Hide passwords
        if (strpos($stringValue, 'password') !== false) {
            return '***HIDDEN***';
        }

        // Truncate long values
        if (strlen($stringValue) > 50) {
            return substr($stringValue, 0, 50).'...';
        }

        return $stringValue;
    }
}
