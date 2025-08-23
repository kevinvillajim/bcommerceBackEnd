<?php

namespace App\Infrastructure\Services;

use App\Domain\Entities\UserInteractionEntity;
use App\Domain\Repositories\UserProfileRepositoryInterface;
use Illuminate\Support\Facades\Log;

class UserInteractionService
{
    protected UserProfileRepositoryInterface $userProfileRepository;

    public function __construct(UserProfileRepositoryInterface $userProfileRepository)
    {
        $this->userProfileRepository = $userProfileRepository;
    }

    /**
     * Registra una interacción de usuario.
     */
    public function trackInteraction(int $userId, string $interactionType, int $itemId, array $metadata = []): bool
    {
        try {
            // Crear entidad de interacción
            $interaction = new UserInteractionEntity(
                $userId,
                $interactionType,
                $itemId,
                $metadata
            );

            // Guardar en el repositorio
            $this->userProfileRepository->saveUserInteraction($interaction);

            return true;
        } catch (\Exception $e) {
            Log::error('Error tracking user interaction: '.$e->getMessage());

            return false;
        }
    }
}
