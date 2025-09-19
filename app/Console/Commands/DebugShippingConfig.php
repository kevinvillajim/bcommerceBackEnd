<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebugShippingConfig extends Command
{
    protected $signature = 'debug:shipping-config';
    protected $description = 'Debug shipping configuration in database';

    public function handle()
    {
        $this->info('=== DIAGNÓSTICO: Configuraciones de Shipping ===');
        $this->newLine();

        $this->info('1. Consultando configuraciones de shipping en BD:');
        $this->info('================================================');

        try {
            $configs = DB::table('configurations')
                ->where('key', 'LIKE', 'shipping.%')
                ->get(['key', 'value', 'type', 'description']);

            if ($configs->isEmpty()) {
                $this->error('❌ NO se encontraron configuraciones de shipping en la BD');
            } else {
                foreach ($configs as $config) {
                    $this->line("- {$config->key}: '{$config->value}' ({$config->type})");
                    if ($config->description) {
                        $this->line("  Descripción: {$config->description}");
                    }
                }
            }

            $this->newLine();
            $this->info('2. Verificando configuraciones específicas:');
            $this->info('==========================================');

            $specificKeys = ['shipping.enabled', 'shipping.free_threshold', 'shipping.default_cost'];

            foreach ($specificKeys as $key) {
                $config = DB::table('configurations')->where('key', $key)->first();
                if ($config) {
                    $this->line("✅ {$key}: '{$config->value}' ({$config->type})");
                } else {
                    $this->error("❌ {$key}: NO ENCONTRADO en BD");
                }
            }

            $this->newLine();
            $this->info('3. Todas las configuraciones relacionadas con shipping:');
            $this->info('====================================================');

            $allShippingConfigs = DB::table('configurations')
                ->where('key', 'LIKE', '%shipping%')
                ->orWhere('key', 'LIKE', '%envio%')
                ->get(['key', 'value', 'type']);

            if ($allShippingConfigs->isEmpty()) {
                $this->warn('No se encontraron configuraciones relacionadas con shipping');
            } else {
                foreach ($allShippingConfigs as $config) {
                    $this->line("- {$config->key}: '{$config->value}'");
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ ERROR al consultar BD: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('=== FIN DEL DIAGNÓSTICO ===');
    }
}