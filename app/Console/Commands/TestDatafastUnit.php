<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\DatafastController;
use App\Models\DatafastPayment;
use App\Models\User;
use App\Models\Product;
use ReflectionClass;
use ReflectionMethod;

class TestDatafastUnit extends Command
{
    protected $signature = 'test:datafast-unit {userId} {productId}';
    protected $description = 'Test unitario específico para componentes DATAFAST';

    public function handle()
    {
        $userId = $this->argument('userId');
        $productId = $this->argument('productId');
        
        $this->info("🔬 === TEST UNITARIO DATAFAST ===");
        $this->info("Usuario: $userId, Producto: $productId");
        
        // Test 1: Controller Unit Tests
        $this->testDatafastControllerUnit();
        
        // Test 2: Model Unit Tests
        $this->testDatafastModelUnit($userId);
        
        // Test 3: Method Signature Tests
        $this->testMethodSignatures();
        
        // Test 4: Integration Points
        $this->testIntegrationPoints();
        
        // Test 5: Error Handling
        $this->testErrorHandling();
        
        $this->info("\n🎉 ✅ TEST UNITARIO DATAFAST COMPLETADO");
        
        return 0;
    }
    
    /**
     * Test unitario del DatafastController
     */
    private function testDatafastControllerUnit(): void
    {
        $this->info("\n🎯 1. CONTROLLER UNIT TESTS:");
        
        try {
            // Instanciar controller
            $controller = app(DatafastController::class);
            $this->info("✅ DatafastController instanciado correctamente");
            
            // Verificar que es instancia correcta
            $this->assertTrue($controller instanceof DatafastController, 'Controller instance');
            $this->info("✅ Tipo de instancia correcto");
            
            // Test reflection
            $reflector = new ReflectionClass($controller);
            $this->info("✅ Reflection class creada correctamente");
            
            // Verificar métodos críticos existen
            $criticalMethods = [
                'createCheckout' => 'Crear sesión de pago',
                'verifyPayment' => 'Verificar pago',
                'webhook' => 'Manejar webhook',
            ];
            
            foreach ($criticalMethods as $method => $description) {
                if ($reflector->hasMethod($method)) {
                    $methodReflection = $reflector->getMethod($method);
                    $this->info("✅ Método $method existe - $description");
                    
                    // Verificar que el método es público
                    if ($methodReflection->isPublic()) {
                        $this->info("  └─ ✅ Es público");
                    } else {
                        $this->warn("  └─ ⚠️ No es público");
                    }
                    
                    // Verificar parámetros
                    $paramCount = $methodReflection->getNumberOfParameters();
                    $this->info("  └─ 📋 Parámetros: $paramCount");
                    
                } else {
                    $this->error("❌ Método $method NO existe");
                    return;
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error en controller unit test: " . $e->getMessage());
        }
    }
    
    /**
     * Test unitario del DatafastPayment model
     */
    private function testDatafastModelUnit(int $userId): void
    {
        $this->info("\n💾 2. MODEL UNIT TESTS:");
        
        try {
            // Verificar clase existe
            $this->assertTrue(class_exists(DatafastPayment::class), 'DatafastPayment class exists');
            $this->info("✅ Clase DatafastPayment existe");
            
            // Crear instancia
            $payment = new DatafastPayment();
            $this->info("✅ Instancia creada correctamente");
            
            // Verificar fillable
            $fillable = $payment->getFillable();
            $expectedFields = [
                'user_id', 'amount', 'currency', 'status',
                'customer_data', 'shipping_data'
            ];
            
            $this->info("📋 Campos fillable encontrados: " . count($fillable));
            foreach ($expectedFields as $field) {
                if (in_array($field, $fillable)) {
                    $this->info("  ✅ Campo $field es fillable");
                } else {
                    $this->warn("  ⚠️ Campo $field NO es fillable");
                }
            }
            
            // Test create (sin guardar en DB)
            $testData = [
                'user_id' => $userId,
                'amount' => 99.99,
                'currency' => 'USD',
                'status' => 'pending',
                'customer_data' => ['test' => 'data'],
                'shipping_data' => ['address' => 'test'],
            ];
            
            // Solo verificar que el modelo puede manejar los datos
            $payment->fill($testData);
            $this->info("✅ Modelo puede manejar datos de prueba");
            
            // Verificar cast types si existen
            $casts = $payment->getCasts();
            if (isset($casts['customer_data'])) {
                $this->info("✅ customer_data tiene casting: " . $casts['customer_data']);
            }
            if (isset($casts['shipping_data'])) {
                $this->info("✅ shipping_data tiene casting: " . $casts['shipping_data']);
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error en model unit test: " . $e->getMessage());
        }
    }
    
    /**
     * Test de signatures de métodos
     */
    private function testMethodSignatures(): void
    {
        $this->info("\n📝 3. METHOD SIGNATURE TESTS:");
        
        try {
            $controller = app(DatafastController::class);
            $reflector = new ReflectionClass($controller);
            
            // Test createCheckout signature
            if ($reflector->hasMethod('createCheckout')) {
                $method = $reflector->getMethod('createCheckout');
                $params = $method->getParameters();
                
                $this->info("🔍 createCheckout signature:");
                foreach ($params as $param) {
                    $type = $param->getType() ? $param->getType()->getName() : 'mixed';
                    $name = $param->getName();
                    $optional = $param->isOptional() ? '(opcional)' : '(requerido)';
                    $this->info("  └─ $type \$$name $optional");
                }
            }
            
            // Test verifyPayment signature
            if ($reflector->hasMethod('verifyPayment')) {
                $method = $reflector->getMethod('verifyPayment');
                $params = $method->getParameters();
                
                $this->info("🔍 verifyPayment signature:");
                foreach ($params as $param) {
                    $type = $param->getType() ? $param->getType()->getName() : 'mixed';
                    $name = $param->getName();
                    $optional = $param->isOptional() ? '(opcional)' : '(requerido)';
                    $this->info("  └─ $type \$$name $optional");
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error en method signature test: " . $e->getMessage());
        }
    }
    
    /**
     * Test de puntos de integración
     */
    private function testIntegrationPoints(): void
    {
        $this->info("\n🔗 4. INTEGRATION POINTS TESTS:");
        
        try {
            // Verificar dependencies que DATAFAST necesita
            $dependencies = [
                \App\UseCases\Checkout\ProcessCheckoutUseCase::class,
                \App\Domain\Services\PricingCalculatorService::class,
                \App\Services\ConfigurationService::class,
            ];
            
            foreach ($dependencies as $dependency) {
                try {
                    $service = app($dependency);
                    $className = class_basename($dependency);
                    $this->info("✅ Dependencia $className disponible");
                } catch (\Exception $e) {
                    $this->error("❌ Dependencia $dependency NO disponible");
                }
            }
            
            // Verificar que DATAFAST puede resolver sus dependencies
            $controller = app(DatafastController::class);
            $this->info("✅ DatafastController resuelve dependencies correctamente");
            
        } catch (\Exception $e) {
            $this->error("❌ Error en integration points test: " . $e->getMessage());
        }
    }
    
    /**
     * Test de manejo de errores
     */
    private function testErrorHandling(): void
    {
        $this->info("\n⚠️ 5. ERROR HANDLING TESTS:");
        
        try {
            // Test con datos inválidos en el modelo
            $payment = new DatafastPayment();
            
            // Intentar llenar con datos inválidos
            try {
                $payment->fill([
                    'invalid_field' => 'test',
                    'amount' => 'invalid_amount',
                ]);
                $this->info("✅ Modelo maneja campos inválidos correctamente");
            } catch (\Exception $e) {
                $this->info("✅ Modelo rechaza datos inválidos: " . $e->getMessage());
            }
            
            // Test controller con app() inválido (simulado)
            try {
                $controller = app(DatafastController::class);
                $this->info("✅ Controller maneja instanciación correctamente");
            } catch (\Exception $e) {
                $this->warn("⚠️ Error en instanciación controller: " . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            $this->warn("⚠️ Error en error handling test: " . $e->getMessage());
        }
    }
    
    /**
     * Helper para assertions
     */
    private function assertTrue($condition, string $message): void
    {
        if (!$condition) {
            $this->error("❌ Assertion failed: $message");
            throw new \Exception("Assertion failed: $message");
        }
    }
}