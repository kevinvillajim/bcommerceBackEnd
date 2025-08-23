<?php

namespace App\Domain\Repositories;

use App\Domain\Entities\UserInteractionEntity;
use App\Domain\ValueObjects\UserProfile;

interface UserProfileRepositoryInterface
{
    /**
     * Guarda una interacción de usuario.
     */
    public function saveUserInteraction(UserInteractionEntity $interaction): UserInteractionEntity;

    public function getUserInteractions(int $userId, int $limit = 50): array;

    /**
     * Construye un perfil de usuario a partir de sus interacciones.
     */
    public function buildUserProfile(int $userId): UserProfile;

    /**
     * Obtiene las preferencias de categoría para un usuario.
     */
    public function getCategoryPreferences(int $userId): array;

    public function getRecentSearchTerms(int $userId, int $limit = 10): array;

    public function getViewedProducts(int $userId, int $limit = 20): array;

    /**
     * Obtiene los IDs de productos vistos por un usuario.
     */
    public function getViewedProductIds(int $userId): array;

    /**
     * Obtiene los intereses basados en etiquetas para un usuario.
     */
    public function getTagInterestsForUser(int $userId): array;
}
