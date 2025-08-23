<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShowConfigurations extends Command
{
    protected $signature = 'config:show {group?}';

    protected $description = 'Show configurations by group';

    public function handle()
    {
        $group = $this->argument('group') ?? 'security';

        $configs = DB::table('configurations')
            ->where('group', $group)
            ->get(['key', 'value', 'type']);

        $this->info("Configuraciones del grupo: {$group}");
        $this->line('');

        foreach ($configs as $config) {
            $this->line("{$config->key} = {$config->value} ({$config->type})");
        }

        return 0;
    }
}
