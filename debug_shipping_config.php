<?php

require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;

echo "=== DIAGNÓSTICO: Configuraciones de Shipping ===\n\n";

echo "1. Consultando configuraciones de shipping en BD:\n";
echo "================================================\n";

try {
    $configs = DB::table('configurations')
        ->where('key', 'LIKE', 'shipping.%')
        ->get(['key', 'value', 'type', 'description']);

    if ($configs->isEmpty()) {
        echo "❌ NO se encontraron configuraciones de shipping en la BD\n";
    } else {
        foreach ($configs as $config) {
            echo "- {$config->key}: '{$config->value}' ({$config->type})\n";
            if ($config->description) {
                echo "  Descripción: {$config->description}\n";
            }
        }
    }

    echo "\n2. Verificando configuraciones específicas:\n";
    echo "==========================================\n";

    $specificKeys = ['shipping.enabled', 'shipping.free_threshold', 'shipping.default_cost'];

    foreach ($specificKeys as $key) {
        $config = DB::table('configurations')->where('key', $key)->first();
        if ($config) {
            echo "✅ {$key}: '{$config->value}' ({$config->type})\n";
        } else {
            echo "❌ {$key}: NO ENCONTRADO en BD\n";
        }
    }

    echo "\n3. Todas las configuraciones relacionadas con shipping:\n";
    echo "====================================================\n";

    $allShippingConfigs = DB::table('configurations')
        ->where('key', 'LIKE', '%shipping%')
        ->orWhere('key', 'LIKE', '%envio%')
        ->get(['key', 'value', 'type']);

    foreach ($allShippingConfigs as $config) {
        echo "- {$config->key}: '{$config->value}'\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR al consultar BD: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";