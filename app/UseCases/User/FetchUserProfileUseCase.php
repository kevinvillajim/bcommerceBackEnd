<?php

namespace App\UseCases\User;

use App\Domain\Repositories\UserRepositoryInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FetchUserProfileUseCase
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
     * Obtiene el perfil del usuario
     */
    public function execute(int $userId): array
    {
        try {
            // Buscar usuario por ID
            $userEntity = $this->userRepository->findById($userId);

            if (! $userEntity) {
                return [
                    'success' => false,
                    'status' => Response::HTTP_NOT_FOUND,
                    'message' => 'Usuario no encontrado',
                ];
            }

            // Verificar si el usuario está bloqueado
            if ($userEntity->isBlocked()) {
                return [
                    'success' => false,
                    'status' => Response::HTTP_FORBIDDEN,
                    'message' => 'Tu cuenta ha sido bloqueada',
                ];
            }

            // Convertir entidad a array para respuesta, incluyendo solo los datos necesarios
            $userData = [
                'id' => $userEntity->getId(),
                'name' => $userEntity->getName(),
                'email' => $userEntity->getEmail(),
                'age' => $userEntity->getAge(),
                'gender' => $userEntity->getGender(),
                'location' => $userEntity->getLocation(),
                'email_verified_at' => $userEntity->getEmailVerifiedAt(),
                'created_at' => $userEntity->getCreatedAt(),
                'updated_at' => $userEntity->getUpdatedAt(),
            ];

            return [
                'success' => true,
                'status' => Response::HTTP_OK,
                'data' => $userData,
            ];
        } catch (\Exception $e) {
            Log::error('Error en GetUserProfileUseCase: '.$e->getMessage(), [
                'user_id' => $userId,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Error al obtener el perfil del usuario',
            ];
        }
    }
}
