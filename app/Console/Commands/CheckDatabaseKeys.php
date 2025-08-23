<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabaseKeys extends Command
{
    protected $signature = 'check:db-keys';

    protected $description = 'Check configuration keys in database';

    public function handle()
    {
        $this->info('Checking configuration keys in database...');

        $configs = DB::table('configurations')
            ->where('group', 'security')
            ->get(['key', 'value', 'type']);

        $this->info("\nSecurity configurations in DB:");
        $this->line('');

        foreach ($configs as $config) {
            $this->line("Key: {$config->key} = {$config->value} ({$config->type})");
        }

        // Also check for password related keys specifically
        $this->info("\nPassword-related configurations:");
        $passwordConfigs = DB::table('configurations')
            ->where('key', 'like', '%password%')
            ->get(['key', 'value', 'type']);

        foreach ($passwordConfigs as $config) {
            $this->line("Key: {$config->key} = {$config->value} ({$config->type})");
        }

        return 0;
    }
}
