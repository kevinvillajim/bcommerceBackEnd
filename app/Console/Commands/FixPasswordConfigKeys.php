<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPasswordConfigKeys extends Command
{
    protected $signature = 'fix:password-config-keys';

    protected $description = 'Fix password configuration keys inconsistency';

    public function handle()
    {
        $this->info('Fixing password configuration keys...');

        // Get the values from snake_case keys
        $snakeValues = [
            'password_min_length' => DB::table('configurations')->where('key', 'security.password_min_length')->value('value'),
            'password_require_special' => DB::table('configurations')->where('key', 'security.password_require_special')->value('value'),
            'password_require_uppercase' => DB::table('configurations')->where('key', 'security.password_require_uppercase')->value('value'),
            'password_require_numbers' => DB::table('configurations')->where('key', 'security.password_require_numbers')->value('value'),
        ];

        $this->info('Current values from snake_case keys:');
        foreach ($snakeValues as $key => $value) {
            $this->line("  $key = $value");
        }

        // Update camelCase keys with the correct values
        if ($snakeValues['password_min_length']) {
            DB::table('configurations')
                ->where('key', 'security.passwordMinLength')
                ->update(['value' => $snakeValues['password_min_length']]);
            $this->info('✓ Updated passwordMinLength to '.$snakeValues['password_min_length']);
        }

        if ($snakeValues['password_require_special']) {
            DB::table('configurations')
                ->where('key', 'security.passwordRequireSpecial')
                ->update(['value' => $snakeValues['password_require_special']]);
            $this->info('✓ Updated passwordRequireSpecial to '.$snakeValues['password_require_special']);
        }

        if ($snakeValues['password_require_uppercase']) {
            DB::table('configurations')
                ->where('key', 'security.passwordRequireUppercase')
                ->update(['value' => $snakeValues['password_require_uppercase']]);
            $this->info('✓ Updated passwordRequireUppercase to '.$snakeValues['password_require_uppercase']);
        }

        if ($snakeValues['password_require_numbers']) {
            DB::table('configurations')
                ->where('key', 'security.passwordRequireNumbers')
                ->update(['value' => $snakeValues['password_require_numbers']]);
            $this->info('✓ Updated passwordRequireNumbers to '.$snakeValues['password_require_numbers']);
        }

        // Delete the duplicate snake_case keys
        $this->info("\nDeleting duplicate snake_case keys...");
        $deleted = DB::table('configurations')
            ->whereIn('key', [
                'security.password_min_length',
                'security.password_require_special',
                'security.password_require_uppercase',
                'security.password_require_numbers',
            ])
            ->delete();

        $this->info("✓ Deleted $deleted duplicate keys");

        // Clear cache if exists
        if (function_exists('cache')) {
            cache()->flush();
            $this->info('✓ Cache cleared');
        }

        $this->info("\n=== PASSWORD CONFIG KEYS FIXED ===");

        return 0;
    }
}
