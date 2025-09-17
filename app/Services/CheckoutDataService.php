<?php

namespace App\Services;

use App\Domain\ValueObjects\CheckoutData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Servicio para manejar datos temporales de checkout
 *
 * Maneja el almacenamiento, recuperación y validación de CheckoutData temporal
 * usando Cache para performance y expiración automática
 */
class CheckoutDataService
{
    private const CACHE_PREFIX = 'checkout_data_';

    /**
     * Almacena CheckoutData temporal
     */
    public function store(CheckoutData $checkoutData): string
    {
        $cacheKey = $this->getCacheKey($checkoutData->sessionId);

        // Almacenar en cache con TTL desde configuración
        Cache::put($cacheKey, $checkoutData->toStorageArray(), $this->getTTL());

        Log::info('✅ CheckoutData almacenado temporalmente', [
            'session_id' => $checkoutData->sessionId,
            'user_id' => $checkoutData->userId,
            'cache_key' => $cacheKey,
            'expires_at' => $checkoutData->expiresAt->toISOString(),
            'ttl_seconds' => $this->getTTL(),
        ]);

        return $cacheKey;
    }

    /**
     * Recupera CheckoutData temporal por sessionId
     */
    public function retrieve(string $sessionId): ?CheckoutData
    {
        $cacheKey = $this->getCacheKey($sessionId);
        $data = Cache::get($cacheKey);

        if (! $data) {
            Log::warning('❌ CheckoutData no encontrado en cache', [
                'session_id' => $sessionId,
                'cache_key' => $cacheKey,
            ]);

            return null;
        }

        try {
            $checkoutData = CheckoutData::fromArray($data);

            // Verificar si ha expirado
            if ($checkoutData->isExpired()) {
                Log::warning('⏰ CheckoutData expirado, removiendo del cache', [
                    'session_id' => $sessionId,
                    'expired_at' => $checkoutData->expiresAt->toISOString(),
                ]);
                $this->remove($sessionId);

                return null;
            }

            Log::info('✅ CheckoutData recuperado exitosamente', [
                'session_id' => $sessionId,
                'user_id' => $checkoutData->userId,
                'expires_at' => $checkoutData->expiresAt->toISOString(),
            ]);

            return $checkoutData;

        } catch (InvalidArgumentException $e) {
            Log::error('❌ Error al crear CheckoutData desde cache', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'cached_data' => $data,
            ]);

            // Remover datos corruptos del cache
            $this->remove($sessionId);

            return null;
        }
    }

    /**
     * ELIMINADO: Búsqueda por userId - INNECESARIA
     * El frontend siempre debe proporcionar sessionId
     */

    /**
     * Remueve CheckoutData temporal
     */
    public function remove(string $sessionId): bool
    {
        $cacheKey = $this->getCacheKey($sessionId);
        $removed = Cache::forget($cacheKey);

        Log::info($removed ? '✅ CheckoutData removido del cache' : '⚠️ CheckoutData no existía en cache', [
            'session_id' => $sessionId,
            'cache_key' => $cacheKey,
            'was_removed' => $removed,
        ]);

        return $removed;
    }

    /**
     * Verifica si existe CheckoutData para sessionId
     */
    public function exists(string $sessionId): bool
    {
        $cacheKey = $this->getCacheKey($sessionId);

        return Cache::has($cacheKey);
    }

    /**
     * SIMPLIFICADO: Detecta si el request usa CheckoutData temporal
     */
    public function isTemporalCheckoutRequest(array $requestData): bool
    {
        // SIMPLIFICADO: Solo verificar session_id y que exista en cache
        $hasSessionId = isset($requestData['session_id']) && !empty($requestData['session_id']);

        if ($hasSessionId) {
            return $this->exists($requestData['session_id']);
        }

        return false;
    }

    /**
     * Extrae sessionId del request
     */
    public function extractSessionId(array $requestData): ?string
    {
        return $requestData['session_id'] ?? null;
    }

    /**
     * NUEVO: Limpiar CheckoutData por sessionId - ALIAS PARA SIMPLICIDAD
     */
    public function deleteCheckoutData(string $sessionId): bool
    {
        return $this->remove($sessionId);
    }

    /**
     * NUEVO: Validar integridad de CheckoutData
     */
    public function validateStoredData(string $sessionId): bool
    {
        $data = $this->retrieve($sessionId);
        if (!$data) {
            return false;
        }

        $isValid = $data->isValid() &&
                  !empty($data->items) &&
                  $data->getFinalTotal() > 0;

        if (!$isValid) {
            Log::warning('❌ CheckoutData inválido encontrado', [
                'session_id' => $sessionId,
                'final_total' => $data->getFinalTotal(),
                'items_count' => count($data->items),
                'is_expired' => $data->isExpired()
            ]);
        }

        return $isValid;
    }

    /**
     * Genera clave de cache para sessionId
     */
    private function getCacheKey(string $sessionId): string
    {
        return self::CACHE_PREFIX.$sessionId;
    }

    /**
     * Obtiene TTL en segundos desde .env (CHECKOUT_DATA_TTL)
     */
    private function getTTL(): int
    {
        return (int) config('app.checkout_data_ttl', 1800); // Default 30 minutos si no está en .env
    }

    /**
     * Obtiene estadísticas del cache para debugging
     */
    public function getStats(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'configured_ttl' => $this->getTTL(),
            'ttl_source' => config('app.checkout_data_ttl') ? '.env' : 'default',
            'cache_driver' => config('cache.default'),
        ];
    }
}
