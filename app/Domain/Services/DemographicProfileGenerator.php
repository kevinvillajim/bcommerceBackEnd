<?php

namespace App\Domain\Services;

use Illuminate\Support\Facades\Log;

class DemographicProfileGenerator
{
    /**
     * Genera un perfil de intereses basado en demografía.
     */
    public function generate(array $demographics): array
    {
        $interests = [];

        try {
            // Aplicar reglas heurísticas basadas en demografía
            if (isset($demographics['age'])) {
                $age = (int) $demographics['age'];

                if ($age < 18) {
                    $interests['videojuegos'] = 5;
                    $interests['accesorios_pc'] = 4;
                    $interests['gadgets'] = 3;
                } elseif ($age >= 18 && $age <= 25) {
                    $interests['tecnologia'] = 5;
                    $interests['hardware_pc'] = 4;
                    $interests['gadgets'] = 3;
                } elseif ($age > 25 && $age <= 40) {
                    $interests['hogar_inteligente'] = 5;
                    $interests['hogar'] = 5;
                    $interests['electronica'] = 4;
                    $interests['moviles'] = 3;
                } elseif ($age > 40) {
                    $interests['salud'] = 5;
                    $interests['hogar_inteligente'] = 5;
                    $interests['electronica'] = 4;
                    $interests['lectores_digitales'] = 3;
                }
            }

            if (isset($demographics['gender'])) {
                $gender = strtolower($demographics['gender']);

                // Generalizaciones en base a tecnología
                if ($gender === 'male') {
                    $interests['hardware_pc'] = isset($interests['hardware_pc'])
                        ? $interests['hardware_pc'] + 2
                        : 4;
                    $interests['gaming'] = isset($interests['gaming'])
                        ? $interests['gaming'] + 2
                        : 4;
                } elseif ($gender === 'female') {
                    $interests['gadgets'] = isset($interests['gadgets'])
                        ? $interests['gadgets'] + 2
                        : 4;
                    $interests['moviles'] = isset($interests['moviles'])
                        ? $interests['moviles'] + 2
                        : 4;
                }
            }

            if (isset($demographics['location'])) {
                // Ajustar por localización si es necesario en el futuro
            }
        } catch (\Exception $e) {
            Log::error('Error generating demographic profile: '.$e->getMessage());
        }

        return $interests;
    }
}
