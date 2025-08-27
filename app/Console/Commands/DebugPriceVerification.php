<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PriceVerificationService;

class DebugPriceVerification extends Command
{
    protected $signature = 'debug:price-verification';
    protected $description = 'Show EXACTLY what the price verification is doing - no maquillaje';

    public function handle()
    {
        $this->info('🔍 ANÁLISIS REAL DEL PRICE VERIFICATION - SIN MAQUILLAJE');
        $this->newLine();

        // 1. Mostrar producto real usado
        $product = DB::table('products')->whereNotNull('seller_id')->first();
        $this->info('📦 PRODUCTO REAL USADO:');
        $this->line("   ID: {$product->id}");
        $this->line("   Precio base: \${$product->price}");
        $this->line("   Seller ID: {$product->seller_id}");
        $this->line("   Descuento seller: " . ($product->discount_percentage ?? 0) . '%');
        
        // 2. Calcular precio esperado manualmente
        $discountAmount = $product->price * (($product->discount_percentage ?? 0) / 100);
        $expectedPrice = $product->price - $discountAmount;
        $this->line("   Precio después descuento seller: \$" . round($expectedPrice, 2));
        $this->newLine();

        // 3. Test real - caso válido
        $this->info('🧪 TEST 1: PRECIO CORRECTO');
        $service = app(PriceVerificationService::class);
        
        $validItem = [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'seller_id' => $product->seller_id,
                'price' => round($expectedPrice, 2)
            ]
        ];
        
        $this->line('   Item enviado:');
        $this->line('   - Product ID: ' . $validItem[0]['product_id']);
        $this->line('   - Quantity: ' . $validItem[0]['quantity']); 
        $this->line('   - Seller ID: ' . $validItem[0]['seller_id']);
        $this->line('   - Price: $' . $validItem[0]['price']);

        // Habilitar logs temporalmente para ver qué pasa internamente
        $originalLogLevel = config('logging.level');
        config(['logging.level' => 'debug']);

        $validResult = $service->verifyItemPrices($validItem, 1);
        
        $this->line('   🔍 Resultado: ' . ($validResult ? '✅ ACEPTADO' : '❌ RECHAZADO'));
        $this->newLine();

        // 4. Test real - caso tampering
        $this->info('🚨 TEST 2: PRECIO MANIPULADO (TAMPERING)');
        $tamperedItem = [
            [
                'product_id' => $product->id,
                'quantity' => 1,
                'seller_id' => $product->seller_id,
                'price' => 0.01 // Precio obviamente manipulado
            ]
        ];
        
        $this->line('   Item manipulado:');
        $this->line('   - Product ID: ' . $tamperedItem[0]['product_id']);
        $this->line('   - Quantity: ' . $tamperedItem[0]['quantity']); 
        $this->line('   - Seller ID: ' . $tamperedItem[0]['seller_id']);
        $this->line('   - Price: $' . $tamperedItem[0]['price'] . ' (MANIPULADO)');

        $tamperedResult = $service->verifyItemPrices($tamperedItem, 1);
        
        $this->line('   🔍 Resultado: ' . ($tamperedResult ? '❌ INCORRECTAMENTE ACEPTADO' : '✅ CORRECTAMENTE RECHAZADO'));
        $this->newLine();

        // 5. Test con descuentos por volumen
        $this->info('🛒 TEST 3: DESCUENTOS POR VOLUMEN (5 items = 8% adicional)');
        
        // Calcular precio con descuento por volumen manualmente
        $baseAfterSeller = $expectedPrice;
        $volumeDiscount = $baseAfterSeller * 0.08; // 8% para 5 items
        $priceWithVolume = $baseAfterSeller - $volumeDiscount;
        
        $volumeItem = [
            [
                'product_id' => $product->id,
                'quantity' => 5, // 5 items = 8% descuento volumen
                'seller_id' => $product->seller_id,
                'price' => round($priceWithVolume, 2)
            ]
        ];
        
        $this->line('   Precio esperado con volumen (5 items, 8%): $' . round($priceWithVolume, 2));
        
        $volumeResult = $service->verifyItemPrices($volumeItem, 1);
        $this->line('   🔍 Resultado: ' . ($volumeResult ? '✅ ACEPTADO' : '❌ RECHAZADO'));
        
        if (!$volumeResult) {
            $this->warn('   ⚠️  RECHAZADO - Esto indica que el PricingCalculatorService está calculando diferente');
            $this->line('       o que los descuentos por volumen no se están aplicando correctamente.');
        }
        
        $this->newLine();
        
        // Mostrar conclusiones
        $this->info('📊 CONCLUSIONES:');
        $this->line('1. Productos válidos con seller_id: ' . ($validResult ? 'FUNCIONAN ✅' : 'FALLAN ❌'));
        $this->line('2. Detección de tampering: ' . (!$tamperedResult ? 'FUNCIONA ✅' : 'FALLA ❌'));
        $this->line('3. Descuentos por volumen: ' . ($volumeResult ? 'FUNCIONAN ✅' : 'NECESITAN REVISIÓN ⚠️'));
        
        $this->newLine();
        $this->info('💡 Esta es la realidad sin maquillaje del sistema.');

        return 0;
    }
}