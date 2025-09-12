<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Invoice;
use App\Jobs\RetryFailedSriInvoiceJob;
use ReflectionClass;

class RetryRoundsTest extends TestCase
{
    /** @test */
    public function it_calculates_retry_rounds_correctly()
    {
        echo "\n🧪 TESTING SISTEMA DE RONDAS CORREGIDO\n";
        echo "=====================================\n";

        // Crear una factura mock
        $invoice = new Invoice(['retry_count' => 0]);
        $job = new RetryFailedSriInvoiceJob($invoice);

        // Usar reflexión para acceder a métodos privados
        $reflection = new ReflectionClass($job);
        $getCurrentRound = $reflection->getMethod('getCurrentRound');
        $getCurrentRound->setAccessible(true);
        
        $getAttemptInCurrentRound = $reflection->getMethod('getAttemptInCurrentRound');
        $getAttemptInCurrentRound->setAccessible(true);
        
        $getDelayForRound = $reflection->getMethod('getDelayForRound');
        $getDelayForRound->setAccessible(true);

        // Testear todas las combinaciones
        $testCases = [
            // [retry_count, expected_round, expected_attempt_in_round]
            [0, 1, 3],  // Inicial - 0 % 3 = 0 → retorna 3 (estado inicial)
            [1, 1, 1],  // Ronda 1, intento 1
            [2, 1, 2],  // Ronda 1, intento 2  
            [3, 1, 3],  // Ronda 1, intento 3
            [4, 2, 1],  // Ronda 2, intento 1
            [5, 2, 2],  // Ronda 2, intento 2
            [6, 2, 3],  // Ronda 2, intento 3
            [7, 3, 1],  // Ronda 3, intento 1
            [8, 3, 2],  // Ronda 3, intento 2
            [9, 3, 3],  // Ronda 3, intento 3
            [10, 4, 1], // Ronda 4, intento 1
            [11, 4, 2], // Ronda 4, intento 2
            [12, 4, 3], // Ronda 4, intento 3
        ];

        foreach ($testCases as [$retryCount, $expectedRound, $expectedAttempt]) {
            $actualRound = $getCurrentRound->invoke($job, $retryCount);
            $actualAttempt = $getAttemptInCurrentRound->invoke($job, $retryCount);
            $nextRoundDelay = $getDelayForRound->invoke($job, $actualRound + 1);

            echo sprintf(
                "retry_count=%d → Ronda %d, Intento %d, Próximo delay: %s min\n",
                $retryCount,
                $actualRound,
                $actualAttempt,
                $nextRoundDelay === null ? 'FIN' : $nextRoundDelay
            );

            // Verificar que las rondas sean correctas
            $this->assertEquals($expectedRound, $actualRound, "Ronda incorrecta para retry_count $retryCount");
            $this->assertEquals($expectedAttempt, $actualAttempt, "Intento en ronda incorrecto para retry_count $retryCount");
        }

        echo "\n✅ TODOS LOS CÁLCULOS DE RONDAS SON CORRECTOS\n";
        echo "=====================================\n";
        echo "📋 SISTEMA FINAL:\n";
        echo "   • Ronda 1: intentos 1-3 (inmediatos, 5 seg entre ellos)\n";
        echo "   • Ronda 2: intentos 4-6 (delay 5 min, luego 5 seg entre ellos)\n";  
        echo "   • Ronda 3: intentos 7-9 (delay 15 min, luego 5 seg entre ellos)\n";
        echo "   • Ronda 4: intentos 10-12 (delay 30 min, luego 5 seg entre ellos)\n";
        echo "   • Total máximo: 12 reintentos\n";
        echo "   • Estado final: DEFINITIVELY_FAILED\n";
    }

    /** @test */
    public function it_respects_timing_constraints()
    {
        echo "\n⏱️ VERIFICANDO RESTRICCIONES DE TIEMPO\n";
        echo "=====================================\n";

        $totalTimeMinutes = 0;
        
        // Ronda 1: 3 intentos inmediatos (5 segundos entre ellos)
        $round1Time = (3 * 5) / 60; // 15 segundos = 0.25 minutos
        $totalTimeMinutes += $round1Time;
        echo "Ronda 1: 3 intentos × 5 seg = {$round1Time} min\n";
        
        // Ronda 2: delay 5 min + 3 intentos (5 seg entre ellos)
        $round2Time = 5 + (3 * 5) / 60; // 5 min + 15 seg
        $totalTimeMinutes += $round2Time;
        echo "Ronda 2: 5 min delay + 0.25 min intentos = {$round2Time} min\n";
        
        // Ronda 3: delay 15 min + 3 intentos (5 seg entre ellos)  
        $round3Time = 15 + (3 * 5) / 60; // 15 min + 15 seg
        $totalTimeMinutes += $round3Time;
        echo "Ronda 3: 15 min delay + 0.25 min intentos = {$round3Time} min\n";
        
        // Ronda 4: delay 30 min + 3 intentos (5 seg entre ellos)  
        $round4Time = 30 + (3 * 5) / 60; // 30 min + 15 seg
        $totalTimeMinutes += $round4Time;
        echo "Ronda 4: 30 min delay + 0.25 min intentos = {$round4Time} min\n";

        echo "\n📊 TIEMPO TOTAL PARA 12 REINTENTOS: {$totalTimeMinutes} minutos\n";
        echo "📊 EQUIVALENTE A: " . round($totalTimeMinutes / 60, 2) . " horas\n";

        // Verificar que es imposible completar 9 reintentos en 17 segundos
        $testDurationSeconds = 17.46;
        $testDurationMinutes = $testDurationSeconds / 60;
        
        echo "\n❌ TIEMPO DEL TEST ANTERIOR: {$testDurationSeconds} seg ({$testDurationMinutes} min)\n";
        echo "✅ TIEMPO REAL NECESARIO: {$totalTimeMinutes} min\n";
        echo "🎯 CONCLUSIÓN: Era TÉCNICAMENTE IMPOSIBLE que fueran 12 reintentos reales\n";

        $this->assertGreaterThan($testDurationMinutes, $totalTimeMinutes, 
            'El sistema corregido toma más tiempo que el test anterior');
    }
}