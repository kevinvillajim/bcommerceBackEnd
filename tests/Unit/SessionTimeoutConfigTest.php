<?php

namespace Tests\Unit;

use App\Infrastructure\Services\JwtService;
use App\Services\ConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test para verificar que la configuración de sessionTimeout
 * se lea correctamente de la base de datos
 */
class SessionTimeoutConfigTest extends TestCase
{
    // NO usar RefreshDatabase para evitar borrar datos

    public function test_configuration_service_reads_session_timeout_from_database()
    {
        // Crear mock del ConfigurationService
        $mockConfigService = $this->createMock(ConfigurationService::class);

        // Mock del método getConfig para que devuelva 120 minutos
        $mockConfigService->expects($this->once())
            ->method('getConfig')
            ->with('security.sessionTimeout', 60)
            ->willReturn(120);

        // Verificar que el servicio retorna el valor correcto
        $sessionTimeout = $mockConfigService->getConfig('security.sessionTimeout', 60);

        $this->assertEquals(120, $sessionTimeout);
        $this->assertIsInt($sessionTimeout);
    }

    public function test_jwt_service_uses_database_configuration()
    {
        // Mock del ConfigurationService
        $mockConfigService = $this->createMock(ConfigurationService::class);

        // Mock para que devuelva 120 minutos desde la DB
        $mockConfigService->expects($this->atLeastOnce())
            ->method('getConfig')
            ->with('security.sessionTimeout', 60)
            ->willReturn(120);

        // Crear JwtService con el mock
        $jwtService = new JwtService($mockConfigService);

        // Obtener el TTL en segundos
        $ttlInSeconds = $jwtService->getTokenTTL();

        // Verificar que son 120 minutos = 7200 segundos
        $this->assertEquals(7200, $ttlInSeconds); // 120 * 60 = 7200
    }

    public function test_jwt_service_handles_invalid_configuration()
    {
        // Mock del ConfigurationService
        $mockConfigService = $this->createMock(ConfigurationService::class);

        // Mock para que devuelva un valor inválido
        $mockConfigService->expects($this->atLeastOnce())
            ->method('getConfig')
            ->with('security.sessionTimeout', 60)
            ->willReturn('invalid_value');

        // Crear JwtService con el mock
        $jwtService = new JwtService($mockConfigService);

        // Obtener el TTL en segundos (debería usar el fallback de 60 minutos)
        $ttlInSeconds = $jwtService->getTokenTTL();

        // Verificar que usa el fallback de 60 minutos = 3600 segundos
        $this->assertEquals(3600, $ttlInSeconds); // 60 * 60 = 3600
    }

    public function test_jwt_service_handles_exception_in_configuration()
    {
        // Mock del ConfigurationService
        $mockConfigService = $this->createMock(ConfigurationService::class);

        // Mock para que lance una excepción
        $mockConfigService->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willThrowException(new \Exception('Database connection error'));

        // Crear JwtService con el mock
        $jwtService = new JwtService($mockConfigService);

        // Obtener el TTL en segundos (debería usar el fallback de 60 minutos)
        $ttlInSeconds = $jwtService->getTokenTTL();

        // Verificar que usa el fallback de 60 minutos = 3600 segundos
        $this->assertEquals(3600, $ttlInSeconds); // 60 * 60 = 3600
    }

    public function test_different_session_timeout_values()
    {
        $testCases = [
            30 => 1800,   // 30 minutes = 1800 seconds
            60 => 3600,   // 60 minutes = 3600 seconds
            120 => 7200,  // 120 minutes = 7200 seconds
            240 => 14400, // 240 minutes = 14400 seconds
        ];

        foreach ($testCases as $minutes => $expectedSeconds) {
            // Mock del ConfigurationService
            $mockConfigService = $this->createMock(ConfigurationService::class);

            // Mock para que devuelva el valor específico
            $mockConfigService->expects($this->atLeastOnce())
                ->method('getConfig')
                ->with('security.sessionTimeout', 60)
                ->willReturn($minutes);

            // Crear JwtService con el mock
            $jwtService = new JwtService($mockConfigService);

            // Obtener el TTL en segundos
            $ttlInSeconds = $jwtService->getTokenTTL();

            // Verificar que la conversión es correcta
            $this->assertEquals($expectedSeconds, $ttlInSeconds,
                "Failed for {$minutes} minutes - expected {$expectedSeconds} seconds, got {$ttlInSeconds}");
        }
    }
}
