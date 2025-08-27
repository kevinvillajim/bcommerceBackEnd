<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;

class TestEloquentRead extends Command
{
    protected $signature = 'debug:test-eloquent-read {orderId}';
    protected $description = 'Testa qué devuelve realmente el modelo Eloquent';

    public function handle()
    {
        $orderId = $this->argument('orderId');
        
        $this->info("=== TEST LECTURA ELOQUENT ===");
        
        // 1. Leer directamente desde Eloquent
        $order = Order::find($orderId);
        
        if (!$order) {
            $this->error("Orden $orderId no encontrada");
            return;
        }
        
        $this->info("\n1. VALORES RAW DEL MODELO ELOQUENT:");
        $this->table(['Campo', 'Valor', 'Tipo'], [
            ['total', $order->total, gettype($order->total)],
            ['original_total', $order->original_total ?? 'NULL', gettype($order->original_total)],
            ['subtotal_products', $order->subtotal_products ?? 'NULL', gettype($order->subtotal_products)],
            ['iva_amount', $order->iva_amount ?? 'NULL', gettype($order->iva_amount)],
            ['shipping_cost', $order->shipping_cost ?? 'NULL', gettype($order->shipping_cost)],
            ['total_discounts', $order->total_discounts ?? 'NULL', gettype($order->total_discounts)],
        ]);
        
        $this->info("\n2. TEST OPERADOR NULL COALESCING:");
        $this->table(['Campo', 'Valor ?? 0.0', 'Es NULL?'], [
            ['subtotal_products', $order->subtotal_products ?? 0.0, is_null($order->subtotal_products) ? 'SÍ' : 'NO'],
            ['iva_amount', $order->iva_amount ?? 0.0, is_null($order->iva_amount) ? 'SÍ' : 'NO'],
            ['shipping_cost', $order->shipping_cost ?? 0.0, is_null($order->shipping_cost) ? 'SÍ' : 'NO'],
            ['total_discounts', $order->total_discounts ?? 0.0, is_null($order->total_discounts) ? 'SÍ' : 'NO'],
        ]);
        
        $this->info("\n3. ATRIBUTOS RAW (sin cast):");
        $rawAttributes = $order->getRawOriginal();
        $this->table(['Campo', 'Raw Value'], [
            ['subtotal_products', $rawAttributes['subtotal_products'] ?? 'NO EXISTE'],
            ['iva_amount', $rawAttributes['iva_amount'] ?? 'NO EXISTE'],
            ['shipping_cost', $rawAttributes['shipping_cost'] ?? 'NO EXISTE'],
            ['total_discounts', $rawAttributes['total_discounts'] ?? 'NO EXISTE'],
        ]);
        
        $this->info("\n4. TODOS LOS ATRIBUTOS:");
        $allAttributes = $order->getAttributes();
        foreach ($allAttributes as $key => $value) {
            if (str_contains($key, 'subtotal') || str_contains($key, 'iva') || str_contains($key, 'shipping') || str_contains($key, 'discount')) {
                $this->line("$key: " . ($value ?? 'NULL') . " (" . gettype($value) . ")");
            }
        }
    }
}