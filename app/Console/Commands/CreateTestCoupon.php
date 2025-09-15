<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateTestCoupon extends Command
{
    protected $signature = 'debug:create-test-coupon';

    protected $description = 'Create test coupon with correct structure';

    public function handle()
    {
        $this->info('🎫 REVISANDO ESTRUCTURA DE DISCOUNT_CODES Y CREANDO CUPÓN');
        $this->newLine();

        // 1. Ver estructura de discount_codes
        $this->info('🗄️ ESTRUCTURA DE TABLA DISCOUNT_CODES:');
        try {
            $structure = DB::select('DESCRIBE discount_codes');
            foreach ($structure as $column) {
                $this->line("   {$column->Field}: {$column->Type} ".($column->Null === 'NO' ? '(required)' : '(optional)'));
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Tabla discount_codes no existe o error: '.$e->getMessage());
        }
        $this->newLine();

        // 2. Ver cupones existentes para entender estructura
        $this->info('🎫 CUPONES EXISTENTES (para ver estructura):');
        try {
            $existingCoupons = DB::table('discount_codes')->limit(3)->get();
            if ($existingCoupons->count() > 0) {
                foreach ($existingCoupons as $coupon) {
                    $this->line('   Cupón ejemplo:');
                    foreach ((array) $coupon as $field => $value) {
                        $this->line("      {$field}: {$value}");
                    }
                    $this->newLine();
                    break; // Solo mostrar uno para ver estructura
                }
            } else {
                $this->warn('   No hay cupones existentes');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Error leyendo cupones: '.$e->getMessage());
        }

        // 3. Crear cupón con estructura mínima
        $this->info('🎫 CREANDO CUPÓN TEST5 CON ESTRUCTURA MÍNIMA:');
        try {
            // Verificar si ya existe
            $exists = DB::table('discount_codes')->where('code', 'TEST5')->first();
            if ($exists) {
                $this->info('   ✅ Cupón TEST5 ya existe');
            } else {
                // Intentar crear con campos básicos
                $couponId = DB::table('discount_codes')->insertGetId([
                    'code' => 'TEST5',
                    'discount_percentage' => 5.00,
                    'expires_at' => Carbon::now()->addMonths(6),
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $this->info("   ✅ Cupón TEST5 creado con ID: {$couponId}");
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Error creando cupón: '.$e->getMessage());

            // Intentar con estructura aún más básica
            $this->info('   🔄 Intentando con estructura básica...');
            try {
                $couponId = DB::table('discount_codes')->insertGetId([
                    'code' => 'TEST5',
                    'discount_percentage' => 5.00,
                    'is_active' => 1,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
                $this->info("   ✅ Cupón TEST5 creado (básico) con ID: {$couponId}");
            } catch (\Exception $e2) {
                $this->error('   ❌ Error también con estructura básica: '.$e2->getMessage());
            }
        }

        $this->newLine();

        // 4. Verificar que el cupón se puede usar
        $this->info('🧪 VERIFICANDO CUPÓN CREADO:');
        $testCoupon = DB::table('discount_codes')->where('code', 'TEST5')->first();
        if ($testCoupon) {
            $this->info('   ✅ Cupón TEST5 disponible:');
            foreach ((array) $testCoupon as $field => $value) {
                $this->line("      {$field}: {$value}");
            }
        } else {
            $this->warn('   ⚠️ Cupón TEST5 no encontrado después de creación');
        }

        return 0;
    }
}
