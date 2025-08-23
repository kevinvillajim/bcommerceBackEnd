<?php

namespace App\UseCases\User;

use App\Domain\Entities\UserEntity;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UpdateProfileUseCase
{
    private UserRepositoryInterface $userRepository;

    /**
     * Constructor
     */
    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Actualiza el perfil del usuario
     */
    public function execute(int $userId, array $profileData): ?UserEntity
    {
        try {
            // Buscar usuario por ID
            $userEntity = $this->userRepository->findById($userId);

            if (! $userEntity) {
                // Intento alternativo con el modelo directamente
                $userModel = User::find($userId);

                if (! $userModel) {
                    Log::error('Usuario no encontrado', ['user_id' => $userId]);

                    return null;
                }

                // Crear entidad a partir del modelo
                $userEntity = UserEntity::reconstitute(
                    $userId,
                    $userModel->name,
                    $userModel->email,
                    '',
                    $userModel->age,
                    $userModel->gender,
                    $userModel->location,
                    (bool) $userModel->is_blocked,
                    $userModel->email_verified_at ? $userModel->email_verified_at->format('Y-m-d H:i:s') : null,
                    $userModel->remember_token,
                    $userModel->created_at ? $userModel->created_at->format('Y-m-d H:i:s') : null,
                    $userModel->updated_at ? $userModel->updated_at->format('Y-m-d H:i:s') : null
                );
            }

            // Actualizar campos si están presentes
            if (isset($profileData['name'])) {
                $userEntity->setName($profileData['name']);
            }

            if (array_key_exists('age', $profileData)) {
                $userEntity->setAge($profileData['age']);
            }

            if (array_key_exists('gender', $profileData)) {
                $userEntity->setGender($profileData['gender']);
            }

            if (array_key_exists('location', $profileData)) {
                $userEntity->setLocation($profileData['location']);
            }

            // Guardar usuario actualizado
            $success = $this->userRepository->update($userEntity);

            if (! $success) {
                // Intento alternativo con el modelo directamente
                $userModel = User::find($userId);
                if ($userModel) {
                    if (isset($profileData['name'])) {
                        $userModel->name = $profileData['name'];
                    }

                    if (array_key_exists('age', $profileData)) {
                        $userModel->age = $profileData['age'];
                    }

                    if (array_key_exists('gender', $profileData)) {
                        $userModel->gender = $profileData['gender'];
                    }

                    if (array_key_exists('location', $profileData)) {
                        $userModel->location = $profileData['location'];
                    }

                    $userModel->save();

                    // Actualizar la entidad con los datos guardados
                    $userEntity->setUpdatedAt($userModel->updated_at->format('Y-m-d H:i:s'));
                    Log::info('Usuario actualizado usando modelo directo', ['user_id' => $userId]);
                } else {
                    Log::error('Error al actualizar usuario', ['user_id' => $userId]);

                    return null;
                }
            }

            return $userEntity;
        } catch (\Exception $e) {
            Log::error('Error en UpdateProfileUseCase: '.$e->getMessage(), [
                'user_id' => $userId,
                'stack_trace' => $e->getTraceAsString(),
                'profile_data' => $profileData,
            ]);

            return null;
        }
    }
}
