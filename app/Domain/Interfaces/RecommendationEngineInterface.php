<?php

namespace App\Domain\Interfaces;

use App\Domain\ValueObjects\UserProfile;

interface RecommendationEngineInterface
{
    /**
     * Generar recomendaciones basadas en el perfil del usuario
     */
    public function generateRecommendations(int $userId, int $limit = 10): array;

    /**
     * Registra una interacción de usuario.
     */
    public function trackInteraction(
        int $userId,
        string $interactionType,
        int $itemId,
        array $metadata = []
    ): bool;

    /**
     * Método alias para compatibilidad con código existente.
     */
    public function trackUserInteraction(
        int $userId,
        string $interactionType,
        int $itemId,
        array $metadata = []
    ): void;

    /**
     * Obtiene el perfil de usuario para recomendaciones.
     */
    public function getUserProfile(int $userId): UserProfile;

    /**
     * Obtiene el perfil de usuario formateado para la API
     */
    public function getUserProfileFormatted(int $userId): array;

    /**
     * Generar un perfil genérico basado en demografía
     */
    public function generateDemographicProfile(array $demographics): array;
}
