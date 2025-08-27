<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CheckRealConfigs extends Command
{
    protected $signature = 'debug:check-real-configs';
    protected $description = 'Check real configurations and create test coupon';

    public function handle()
    {
        $this->info('🔍 REVISANDO CONFIGURACIONES REALES DE LA BD');
        $this->newLine();

        // 1. Configuraciones de descuentos por volumen
        $this->info('📦 CONFIGURACIONES DE DESCUENTOS POR VOLUMEN:');
        $volumeConfigs = DB::table('configurations')
            ->where('key', 'LIKE', '%volume%')
            ->orWhere('key', 'LIKE', '%discount%')
            ->get();

        if ($volumeConfigs->count() > 0) {
            foreach ($volumeConfigs as $config) {
                $this->line("   {$config->key}: {$config->value}");
            }
        } else {
            $this->warn('   No se encontraron configuraciones de volumen en tabla configurations');
        }
        $this->newLine();

        // 2. Ver todas las configuraciones disponibles
        $this->info('⚙️ TODAS LAS CONFIGURACIONES DISPONIBLES:');
        $allConfigs = DB::table('configurations')->get();
        foreach ($allConfigs as $config) {
            $this->line("   {$config->key}: {$config->value}");
        }
        $this->newLine();

        // 3. Verificar usuario admin
        $this->info('👤 VERIFICANDO USUARIO ADMIN:');
        $admin = DB::table('users')->where('email', 'admin@admin.com')->first();
        if ($admin) {
            $this->info('   ✅ Usuario admin encontrado - ID: ' . $admin->id);
        } else {
            $this->warn('   ⚠️ Usuario admin no encontrado');
        }
        $this->newLine();

        // 4. Crear cupón de prueba
        $this->info('🎫 CREANDO CUPÓN DE PRUEBA:');
        
        if ($admin) {
            // Verificar si ya existe
            $existingCoupon = DB::table('discount_codes')->where('code', 'TEST5')->first();
            
            if ($existingCoupon) {
                $this->info('   ✅ Cupón TEST5 ya existe');
            } else {
                // Crear nuevo cupón
                $couponId = DB::table('discount_codes')->insertGetId([
                    'code' => 'TEST5',
                    'discount_percentage' => 5.00,
                    'usage_limit' => 100,
                    'used_count' => 0,
                    'expires_at' => Carbon::now()->addMonths(6),
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);
                
                $this->info("   ✅ Cupón TEST5 creado con ID: {$couponId}");
            }
        }
        $this->newLine();

        // 5. Revisar tabla discount_codes existentes
        $this->info('🎫 CUPONES EXISTENTES:');
        $coupons = DB::table('discount_codes')->where('is_active', true)->get();
        if ($coupons->count() > 0) {
            foreach ($coupons as $coupon) {
                $this->line("   {$coupon->code}: {$coupon->discount_percentage}% (usado {$coupon->used_count}/{$coupon->usage_limit})");
            }
        } else {
            $this->warn('   No hay cupones activos');
        }
        $this->newLine();

        // 6. Verificar estructura de tabla configurations para entender descuentos
        $this->info('🗄️ ESTRUCTURA TABLA CONFIGURATIONS:');
        $configStructure = DB::select("DESCRIBE configurations");
        foreach ($configStructure as $column) {
            $this->line("   {$column->Field}: {$column->Type}");
        }

        return 0;
    }
}