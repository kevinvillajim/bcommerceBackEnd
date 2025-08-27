<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateCorrectCoupon extends Command
{
    protected $signature = 'debug:create-correct-coupon';
    protected $description = 'Create coupon with correct structure';

    public function handle()
    {
        $this->info('🎫 CREANDO CUPÓN CON ESTRUCTURA CORRECTA');
        $this->newLine();

        // Crear cupón con estructura correcta (sin is_active, sin usage_limit)
        $this->info('🎫 CREANDO CUPÓN TEST5:');
        try {
            // Verificar si existe
            $exists = DB::table('discount_codes')->where('code', 'TEST5')->first();
            if ($exists) {
                $this->info('   ✅ Cupón TEST5 ya existe');
            } else {
                $couponId = DB::table('discount_codes')->insertGetId([
                    'code' => 'TEST5',
                    'discount_percentage' => 5.00,
                    'is_used' => 0, // No usado aún
                    'expires_at' => Carbon::now()->addMonths(6),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
                
                $this->info("   ✅ Cupón TEST5 creado con ID: {$couponId}");
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Error: ' . $e->getMessage());
        }

        // También crear algunos cupones adicionales para testing
        $this->info('🎫 CREANDO CUPONES ADICIONALES:');
        $additionalCoupons = [
            'TEST3' => 3.00,
            'TEST10' => 10.00,
            'VOLUME5' => 5.00,
        ];

        foreach ($additionalCoupons as $code => $percentage) {
            try {
                $exists = DB::table('discount_codes')->where('code', $code)->first();
                if (!$exists) {
                    DB::table('discount_codes')->insert([
                        'code' => $code,
                        'discount_percentage' => $percentage,
                        'is_used' => 0,
                        'expires_at' => Carbon::now()->addMonths(6),
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                    $this->info("   ✅ Cupón {$code} ({$percentage}%) creado");
                } else {
                    $this->line("   ➖ Cupón {$code} ya existe");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Error creando {$code}: " . $e->getMessage());
            }
        }

        $this->newLine();
        
        // Mostrar cupones disponibles para testing
        $this->info('🎫 CUPONES DISPONIBLES PARA TESTING:');
        $availableCoupons = DB::table('discount_codes')
            ->where('is_used', 0)
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', Carbon::now());
            })
            ->get();

        foreach ($availableCoupons as $coupon) {
            $this->info("   ✅ {$coupon->code}: {$coupon->discount_percentage}%");
        }

        return 0;
    }
}