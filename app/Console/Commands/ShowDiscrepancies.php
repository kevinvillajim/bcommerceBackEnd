<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\PriceVerificationService;
use App\Domain\Services\PricingCalculatorService;

class ShowDiscrepancies extends Command
{
    protected $signature = 'debug:show-discrepancies';
    protected $description = 'Show EXACT discrepancies between manual calculation and PricingCalculatorService';

    public function handle()
    {
        $this->info('🔍 ANÁLISIS DE DISCREPANCIAS - CÁLCULO MANUAL VS PRICINGCALCULATORSERVICE');
        $this->newLine();

        $product = DB::table('products')->whereNotNull('seller_id')->first();
        $pricingService = app(PricingCalculatorService::class);

        $this->info("📦 PRODUCTO BASE:");
        $this->line("   ID: {$product->id}");
        $this->line("   Precio: \${$product->price}");
        $this->line("   Descuento seller: " . ($product->discount_percentage ?? 0) . '%');
        $this->line("   Seller ID: {$product->seller_id}");
        $this->newLine();

        // ESCENARIO 1: Solo descuento seller
        $this->info('🧮 ESCENARIO 1: SOLO DESCUENTO SELLER (1 item)');
        
        // Mi cálculo manual
        $manualPrice = $product->price - ($product->price * (($product->discount_percentage ?? 0) / 100));
        $this->line("   🤓 MI CÁLCULO MANUAL: \${$manualPrice}");
        
        // PricingCalculatorService real
        $items1 = [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'seller_id' => $product->seller_id
            ]
        ];
        
        $serverResult1 = $pricingService->calculateCartTotals($items1, 1);
        $serverPrice1 = 0;
        if (isset($serverResult1['processed_items'][0])) {
            $serverItem = $serverResult1['processed_items'][0];
            // CORRECCIÓN: Usar la key correcta
            $serverPrice1 = $serverItem['final_price'] ?? 0;
        }
        
        $this->line("   🤖 PRICINGCALCULATORSERVICE: \${$serverPrice1}");
        $this->line("   📊 DIFERENCIA: \$" . abs($manualPrice - $serverPrice1));
        
        if (abs($manualPrice - $serverPrice1) > 0.01) {
            $this->error("   ❌ DISCREPANCIA DETECTADA!");
            $this->line("   🔍 Estructura completa del item calculado:");
            print_r($serverResult1['items'][0] ?? ['ERROR' => 'No item found']);
        } else {
            $this->info("   ✅ COINCIDEN");
        }
        $this->newLine();

        // ESCENARIO 2: Descuento seller + volumen (5 items = 5% según config real)
        $this->info('🧮 ESCENARIO 2: SELLER + VOLUMEN (5 items = 5%)');
        
        // Mi cálculo manual - USAR CONFIGURACIÓN REAL: 5 items = 5% (tier 3+)
        $step1 = $product->price; // Precio base
        $step2 = $step1 - ($step1 * (($product->discount_percentage ?? 0) / 100)); // Descuento seller
        $step3 = $step2 - ($step2 * 0.05); // Descuento volumen 5% para 5 items (tier 3+)
        
        $this->line("   🤓 MI CÁLCULO MANUAL:");
        $this->line("      Paso 1 - Precio base: \${$step1}");
        $this->line("      Paso 2 - Después seller: \${$step2}");
        $this->line("      Paso 3 - Después volumen 5%: \${$step3}");
        
        // PricingCalculatorService real
        $items2 = [
            [
                'product_id' => $product->id,
                'quantity' => 5, // 5 items para activar descuento por volumen
                'seller_id' => $product->seller_id
            ]
        ];
        
        $serverResult2 = $pricingService->calculateCartTotals($items2, 1);
        $serverPrice2 = 0;
        if (isset($serverResult2['processed_items'][0])) {
            $serverItem = $serverResult2['processed_items'][0];
            // CORRECCIÓN: Usar la key correcta
            $serverPrice2 = $serverItem['final_price'] ?? 0;
            
            $this->line("   🤖 PRICINGCALCULATORSERVICE:");
            $this->line("      Estructura completa del resultado:");
            $this->line("      - seller_discounted_price: " . ($serverItem['seller_discounted_price'] ?? 'N/A'));
            $this->line("      - volume_discount_percentage: " . ($serverItem['volume_discount_percentage'] ?? 'N/A'));
            $this->line("      - volume_discount_amount: " . ($serverItem['volume_discount_amount'] ?? 'N/A'));
            $this->line("      - final_price: " . ($serverItem['final_price'] ?? 'N/A'));
        }
        
        $this->line("   📊 MI RESULTADO: \${$step3}");
        $this->line("   📊 SERVER RESULTADO: \${$serverPrice2}");
        $this->line("   📊 DIFERENCIA: \$" . abs($step3 - $serverPrice2));
        
        if (abs($step3 - $serverPrice2) > 0.01) {
            $this->error("   ❌ DISCREPANCIA DETECTADA!");
            $this->line("   🔍 FULL SERVER RESULT:");
            print_r($serverResult2);
        } else {
            $this->info("   ✅ COINCIDEN");
        }
        $this->newLine();

        // ESCENARIO 3: Con cupón
        $this->info('🧮 ESCENARIO 3: SELLER + CUPÓN 5%');
        
        // Mi cálculo manual con cupón
        $cupStep1 = $product->price;
        $cupStep2 = $cupStep1 - ($cupStep1 * (($product->discount_percentage ?? 0) / 100)); // Seller
        $cupStep3 = $cupStep2 - ($cupStep2 * 0.05); // Cupón 5%
        
        $this->line("   🤓 MI CÁLCULO MANUAL CON CUPÓN:");
        $this->line("      Paso 1 - Precio base: \${$cupStep1}");
        $this->line("      Paso 2 - Después seller: \${$cupStep2}");
        $this->line("      Paso 3 - Después cupón 5%: \${$cupStep3}");
        
        // PricingCalculatorService con cupón
        $items3 = [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'seller_id' => $product->seller_id
            ]
        ];
        
        $serverResult3 = $pricingService->calculateCartTotals($items3, 1, 'TEST5');
        
        $this->line("   🤖 SERVER RESULT WITH COUPON:");
        $this->line("      subtotal_original: " . ($serverResult3['subtotal_original'] ?? 'N/A'));
        $this->line("      subtotal_after_coupon: " . ($serverResult3['subtotal_after_coupon'] ?? 'N/A'));
        $this->line("      coupon_discount_amount: " . ($serverResult3['coupon_discount_amount'] ?? 'N/A'));
        
        $this->newLine();
        
        // CONCLUSIONES
        $this->info('📊 CONCLUSIONES:');
        $this->line('1. Necesito entender EXACTAMENTE cómo calcula el PricingCalculatorService');
        $this->line('2. Ver si usa configuración desde BD para descuentos por volumen');
        $this->line('3. Verificar la secuencia correcta de aplicación de descuentos');
        
        return 0;
    }
}