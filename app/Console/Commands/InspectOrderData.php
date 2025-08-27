<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectOrderData extends Command
{
    protected $signature = 'debug:inspect-order {orderId}';
    protected $description = 'Inspecciona datos de una orden específica';

    public function handle()
    {
        $orderId = $this->argument('orderId');
        
        $this->info("=== AUDITORIA DE ORDEN $orderId ===");
        
        // 1. Datos de la tabla orders
        $order = DB::table('orders')->where('id', $orderId)->first();
        if (!$order) {
            $this->error("Orden $orderId no encontrada");
            return;
        }
        
        $this->info("\n1. TABLA ORDERS:");
        $this->table(['Campo', 'Valor'], [
            ['total', $order->total],
            ['original_total', $order->original_total ?? 'NULL'],
            ['subtotal_products', $order->subtotal_products ?? 'NULL'],
            ['iva_amount', $order->iva_amount ?? 'NULL'],
            ['shipping_cost', $order->shipping_cost ?? 'NULL'],
            ['total_discounts', $order->total_discounts ?? 'NULL'],
            ['volume_discount_savings', $order->volume_discount_savings ?? 'NULL'],
            ['seller_discount_savings', $order->seller_discount_savings ?? 'NULL'],
        ]);
        
        // 2. Datos de seller_orders
        $sellerOrders = DB::table('seller_orders')->where('order_id', $orderId)->get();
        $this->info("\n2. TABLA SELLER_ORDERS:");
        if ($sellerOrders->count() > 0) {
            foreach ($sellerOrders as $so) {
                $this->table(['Campo', 'Valor'], [
                    ['id', $so->id],
                    ['seller_id', $so->seller_id],
                    ['total', $so->total],
                ]);
            }
        } else {
            $this->warn("No hay registros en seller_orders para orden $orderId");
        }
        
        // 3. Datos de order_items
        $items = DB::table('order_items as oi')
            ->leftJoin('products as p', 'oi.product_id', '=', 'p.id')
            ->where('oi.order_id', $orderId)
            ->select('oi.*', 'p.name as product_name')
            ->get();
            
        $this->info("\n3. TABLA ORDER_ITEMS:");
        foreach ($items as $item) {
            $this->table(['Campo', 'Valor'], [
                ['product', $item->product_name ?? 'N/A'],
                ['quantity', $item->quantity],
                ['price', $item->price],
                ['original_price', $item->original_price ?? 'NULL'],
                ['subtotal', $item->subtotal],
                ['volume_savings', $item->volume_savings ?? 'NULL'],
                ['seller_order_id', $item->seller_order_id ?? 'NULL'],
            ]);
        }
        
        // 4. Análisis de cálculos
        $this->info("\n4. ANÁLISIS DE CÁLCULOS:");
        $itemsTotal = $items->sum('subtotal');
        $this->table(['Cálculo', 'Valor'], [
            ['Suma de order_items.subtotal', $itemsTotal],
            ['orders.total (actual)', $order->total],
            ['Diferencia', $order->total - $itemsTotal],
        ]);
        
        // 5. Recomendación
        $this->info("\n5. RECOMENDACIONES:");
        if ($order->original_total == 0 || $order->iva_amount == 0) {
            $this->warn("❌ Los campos de breakdown en orders están incompletos");
            $this->warn("💡 Necesita arreglarse: original_total, iva_amount, shipping_cost");
        }
        
        if ($sellerOrders->count() == 0) {
            $this->error("❌ No hay seller_orders - problema crítico para vendedores");
        }
    }
}