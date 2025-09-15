<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Log::info('🔧 Iniciando migración para corregir pricing breakdown de órdenes existentes');

        // Buscar órdenes con campos de pricing breakdown incorrectos (0.00)
        $brokenOrders = DB::table('orders')
            ->where(function ($query) {
                $query->where('original_total', '=', 0.00)
                    ->orWhere('subtotal_products', '=', 0.00)
                    ->orWhere('iva_amount', '=', 0.00)
                    ->orWhere('shipping_cost', '=', 0.00);
            })
            ->where('total', '>', 0) // Solo órdenes con total válido
            ->select('id', 'total', 'created_at')
            ->get();

        Log::info("🔍 Encontradas {$brokenOrders->count()} órdenes con pricing breakdown incorrecto");

        if ($brokenOrders->count() === 0) {
            Log::info('✅ No se encontraron órdenes para corregir');

            return;
        }

        $fixedCount = 0;
        $errorCount = 0;

        foreach ($brokenOrders as $orderData) {
            try {
                $this->fixOrderPricingBreakdown($orderData->id, $orderData->total);
                $fixedCount++;

                if ($fixedCount % 10 === 0) {
                    Log::info("✅ Progreso: {$fixedCount} órdenes corregidas...");
                }

            } catch (\Exception $e) {
                $errorCount++;
                Log::error("❌ Error corrigiendo orden {$orderData->id}: ".$e->getMessage());
            }
        }

        Log::info("🎉 Migración completada: {$fixedCount} órdenes corregidas, {$errorCount} errores");
    }

    /**
     * Arreglar pricing breakdown de una orden específica
     */
    private function fixOrderPricingBreakdown(int $orderId, float $totalAmount): void
    {
        // 1. Obtener items de la orden para calcular subtotal de productos
        $orderItems = DB::table('order_items')
            ->where('order_id', $orderId)
            ->get();

        $subtotalProducts = $orderItems->sum('subtotal');

        // 2. Usar configuración por defecto para shipping y tax
        $shippingThreshold = 50.0;  // Configuración típica
        $defaultShippingCost = 5.0; // Configuración típica
        $taxRate = 0.15; // 15% IVA típico

        // 3. Calcular shipping cost
        $shippingCost = ($subtotalProducts >= $shippingThreshold) ? 0.0 : $defaultShippingCost;

        // 4. Calcular IVA sobre (productos + shipping)
        $taxableAmount = $subtotalProducts + $shippingCost;
        $ivaAmount = $taxableAmount * $taxRate;

        // 5. Calcular valores reconstruidos
        $originalTotal = $subtotalProducts; // Antes de descuentos
        $reconstructedTotal = $subtotalProducts + $shippingCost + $ivaAmount;

        // 6. Calcular descuentos totales basado en diferencia
        $totalDiscounts = max(0, $originalTotal + $shippingCost + $ivaAmount - $totalAmount);

        // 7. Ajustar si hay diferencia significativa (usar total real como referencia)
        if (abs($reconstructedTotal - $totalAmount) > 0.1) {
            // Usar total real y recalcular IVA hacia atrás
            $remainingAfterShipping = $totalAmount - $shippingCost;
            $subtotalWithIva = $remainingAfterShipping;
            $calculatedSubtotal = $subtotalWithIva / (1 + $taxRate);
            $calculatedIva = $subtotalWithIva - $calculatedSubtotal;

            // Usar valores calculados hacia atrás
            $ivaAmount = round($calculatedIva, 2);
            $originalTotal = round($calculatedSubtotal, 2);
        }

        // 8. Actualizar la orden con valores corregidos
        DB::table('orders')
            ->where('id', $orderId)
            ->update([
                'original_total' => round($originalTotal, 2),
                'subtotal_products' => round($subtotalProducts, 2),
                'iva_amount' => round($ivaAmount, 2),
                'shipping_cost' => round($shippingCost, 2),
                'total_discounts' => round($totalDiscounts, 2),
                'free_shipping' => $shippingCost == 0,
                'free_shipping_threshold' => $shippingThreshold,
            ]);

        Log::debug("✅ Orden {$orderId} corregida", [
            'original_total' => $originalTotal,
            'subtotal_products' => $subtotalProducts,
            'iva_amount' => $ivaAmount,
            'shipping_cost' => $shippingCost,
            'total_discounts' => $totalDiscounts,
            'total_final' => $totalAmount,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Log::warning('⚠️ ADVERTENCIA: No se puede revertir la corrección de pricing breakdown');
        Log::warning('Los valores originales incorrectos (0.00) se han perdido');

        // No hacer nada - no podemos revertir a valores incorrectos
        // Los valores corregidos son mejores que los originales (0.00)
    }
};
