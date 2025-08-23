<?php

namespace App\Domain\Services;

use App\Domain\ValueObjects\UserProfile;
use Illuminate\Support\Facades\Log;

class ProfileCompletenessCalculator
{
    /**
     * Calcula la completitud del perfil de usuario.
     */
    public function calculate(UserProfile $profile): int
    {
        try {
            $score = 0;

            // Intereses
            $interestCount = count($profile->getInterests());
            if ($interestCount > 10) {
                $score += 30;
            } elseif ($interestCount > 5) {
                $score += 20;
            } elseif ($interestCount > 0) {
                $score += 10;
            }

            // Historial de búsqueda
            $searchCount = count($profile->getSearchHistory());
            if ($searchCount > 20) {
                $score += 25;
            } elseif ($searchCount > 10) {
                $score += 15;
            } elseif ($searchCount > 0) {
                $score += 5;
            }

            // Productos vistos
            $viewedCount = count($profile->getViewedProducts());
            if ($viewedCount > 30) {
                $score += 25;
            } elseif ($viewedCount > 15) {
                $score += 15;
            } elseif ($viewedCount > 0) {
                $score += 5;
            }

            // Demografía
            if (! empty($profile->getDemographics())) {
                $score += 10;

                if (isset($profile->getDemographics()['age'])) {
                    $score += 5;
                }

                if (isset($profile->getDemographics()['gender'])) {
                    $score += 5;
                }
            }

            return min(100, $score);
        } catch (\Exception $e) {
            Log::error('Error calculating profile completeness: '.$e->getMessage());

            return 0;
        }
    }
}
