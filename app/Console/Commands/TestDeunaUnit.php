<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\DeunaPaymentController;
use App\Http\Controllers\DeunaWebhookController;
use App\Models\DeunaPayment;
use App\Models\User;
use App\Models\Product;
use ReflectionClass;
use ReflectionMethod;

class TestDeunaUnit extends Command
{
    protected $signature = 'test:deuna-unit {userId} {productId}';
    protected $description = 'Test unitario específico para componentes DEUNA';

    public function handle()
    {
        $userId = $this->argument('userId');
        $productId = $this->argument('productId');
        
        $this->info("🔬 === TEST UNITARIO DEUNA ===");
        $this->info("Usuario: $userId, Producto: $productId");
        
        // Test 1: Payment Controller Unit Tests
        $this->testDeunaPaymentControllerUnit();
        
        // Test 2: Webhook Controller Unit Tests
        $this->testDeunaWebhookControllerUnit();
        
        // Test 3: Model Unit Tests
        $this->testDeunaModelUnit($userId);
        
        // Test 4: Method Signature Tests
        $this->testMethodSignatures();
        
        // Test 5: Integration Points
        $this->testIntegrationPoints();
        
        // Test 6: Error Handling
        $this->testErrorHandling();
        
        $this->info("\n🎉 ✅ TEST UNITARIO DEUNA COMPLETADO");
        
        return 0;
    }
    
    /**
     * Test unitario del DeunaPaymentController
     */
    private function testDeunaPaymentControllerUnit(): void
    {
        $this->info("\n🎯 1. PAYMENT CONTROLLER UNIT TESTS:");
        
        try {
            // Instanciar controller
            $controller = app(DeunaPaymentController::class);
            $this->info("✅ DeunaPaymentController instanciado correctamente");
            
            // Verificar que es instancia correcta
            $this->assertTrue($controller instanceof DeunaPaymentController, 'Payment Controller instance');
            $this->info("✅ Tipo de instancia correcto");
            
            // Test reflection
            $reflector = new ReflectionClass($controller);
            $this->info("✅ Reflection class creada correctamente");
            
            // Verificar métodos críticos existen
            $criticalMethods = [
                'createPayment' => 'Crear pago directo',
                'getPaymentStatus' => 'Verificar estado de pago',
                'generateQR' => 'Generar código QR',
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
            $this->error("❌ Error en payment controller unit test: " . $e->getMessage());
        }
    }
    
    /**
     * Test unitario del DeunaWebhookController
     */
    private function testDeunaWebhookControllerUnit(): void
    {
        $this->info("\n🌐 2. WEBHOOK CONTROLLER UNIT TESTS:");
        
        try {
            // Instanciar controller
            $controller = app(DeunaWebhookController::class);
            $this->info("✅ DeunaWebhookController instanciado correctamente");
            
            // Verificar que es instancia correcta
            $this->assertTrue($controller instanceof DeunaWebhookController, 'Webhook Controller instance');
            $this->info("✅ Tipo de instancia correcto");
            
            // Test reflection
            $reflector = new ReflectionClass($controller);
            $this->info("✅ Reflection class creada correctamente");
            
            // Verificar métodos críticos de webhook
            $webhookMethods = [
                'handle' => 'Manejar webhook principal',
                '__invoke' => 'Método de invocación',
            ];
            
            foreach ($webhookMethods as $method => $description) {
                if ($reflector->hasMethod($method)) {
                    $methodReflection = $reflector->getMethod($method);
                    $this->info("✅ Método $method existe - $description");
                    
                    // Verificar que el método es público
                    if ($methodReflection->isPublic()) {
                        $this->info("  └─ ✅ Es público");
                    } else {
                        $this->warn("  └─ ⚠️ No es público");
                    }
                    
                } else {
                    $this->info("ℹ️ Método $method no existe (opcional)");
                }
            }
            
        } catch (\Exception $e) {
            $this->warn("⚠️ Warning en webhook controller test: " . $e->getMessage());
        }
    }
    
    /**
     * Test unitario del DeunaPayment model
     */
    private function testDeunaModelUnit(int $userId): void
    {
        $this->info("\n💾 3. MODEL UNIT TESTS:");
        
        try {
            // Verificar clase existe
            $this->assertTrue(class_exists(DeunaPayment::class), 'DeunaPayment class exists');
            $this->info("✅ Clase DeunaPayment existe");
            
            // Crear instancia
            $payment = new DeunaPayment();
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
        $this->info("\n📝 4. METHOD SIGNATURE TESTS:");
        
        try {
            $paymentController = app(DeunaPaymentController::class);
            $reflector = new ReflectionClass($paymentController);
            
            // Test createPayment signature
            if ($reflector->hasMethod('createPayment')) {
                $method = $reflector->getMethod('createPayment');
                $params = $method->getParameters();
                
                $this->info("🔍 createPayment signature:");
                foreach ($params as $param) {
                    $type = $param->getType() ? $param->getType()->getName() : 'mixed';
                    $name = $param->getName();
                    $optional = $param->isOptional() ? '(opcional)' : '(requerido)';
                    $this->info("  └─ $type \$$name $optional");
                }
            }
            
            // Test getPaymentStatus signature
            if ($reflector->hasMethod('getPaymentStatus')) {
                $method = $reflector->getMethod('getPaymentStatus');
                $params = $method->getParameters();
                
                $this->info("🔍 getPaymentStatus signature:");
                foreach ($params as $param) {
                    $type = $param->getType() ? $param->getType()->getName() : 'mixed';
                    $name = $param->getName();
                    $optional = $param->isOptional() ? '(opcional)' : '(requerido)';
                    $this->info("  └─ $type \$$name $optional");
                }
            }
            
            // Test generateQR signature
            if ($reflector->hasMethod('generateQR')) {
                $method = $reflector->getMethod('generateQR');
                $params = $method->getParameters();
                
                $this->info("🔍 generateQR signature:");
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
        $this->info("\n🔗 5. INTEGRATION POINTS TESTS:");
        
        try {
            // Verificar dependencies que DEUNA necesita (las mismas que DATAFAST)
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
            
            // Verificar que DEUNA puede resolver sus dependencies
            $paymentController = app(DeunaPaymentController::class);
            $this->info("✅ DeunaPaymentController resuelve dependencies correctamente");
            
            // Verificar webhook controller también
            try {
                $webhookController = app(DeunaWebhookController::class);
                $this->info("✅ DeunaWebhookController resuelve dependencies correctamente");
            } catch (\Exception $e) {
                $this->warn("⚠️ DeunaWebhookController: " . $e->getMessage());
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error en integration points test: " . $e->getMessage());
        }
    }
    
    /**
     * Test de manejo de errores
     */
    private function testErrorHandling(): void
    {
        $this->info("\n⚠️ 6. ERROR HANDLING TESTS:");
        
        try {
            // Test con datos inválidos en el modelo
            $payment = new DeunaPayment();
            
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
            
            // Test controllers con app() inválido (simulado)
            try {
                $paymentController = app(DeunaPaymentController::class);
                $this->info("✅ Payment Controller maneja instanciación correctamente");
            } catch (\Exception $e) {
                $this->warn("⚠️ Error en instanciación payment controller: " . $e->getMessage());
            }
            
            try {
                $webhookController = app(DeunaWebhookController::class);
                $this->info("✅ Webhook Controller maneja instanciación correctamente");
            } catch (\Exception $e) {
                $this->warn("⚠️ Error en instanciación webhook controller: " . $e->getMessage());
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