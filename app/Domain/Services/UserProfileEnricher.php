<?php

namespace App\Domain\Services;

use App\Domain\ValueObjects\UserProfile;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserProfileEnricher
{
    private DemographicProfileGenerator $demographicGenerator;

    public function __construct(DemographicProfileGenerator $demographicGenerator)
    {
        $this->demographicGenerator = $demographicGenerator;
    }

    /**
     * Enriquece un perfil de usuario con datos demográficos e intereses derivados.
     */
    public function enrichProfile(UserProfile $userProfile, int $userId): UserProfile
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                return $userProfile;
            }

            // Enriquecer el perfil con demografía si está incompleto
            if (count($userProfile->getInterests()) < 3 && ! empty($userProfile->getDemographics())) {
                $demographicInterests = $this->demographicGenerator->generate($userProfile->getDemographics());

                foreach ($demographicInterests as $interest => $weight) {
                    if (! array_key_exists($interest, $userProfile->getInterests())) {
                        $userProfile->addInterest($interest, $weight);
                    }
                }
            }

            return $userProfile;
        } catch (\Exception $e) {
            Log::error('Error enriching user profile: '.$e->getMessage());

            return $userProfile;
        }
    }
}
