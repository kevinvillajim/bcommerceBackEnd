<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use Illuminate\Http\Response;

class GetAuthenticatedUserUseCase
{
    private JwtServiceInterface $jwtService;

    /**
     * Create a new use case instance.
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the use case.
     */
    public function execute(): array
    {
        try {
            $user = $this->jwtService->getAuthenticatedUser();

            if (! $user) {
                return [
                    'success' => false,
                    'status' => Response::HTTP_UNAUTHORIZED,
                    'error' => 'Usuario no autenticado',
                ];
            }

            // Si el usuario está bloqueado, no permitimos acceso
            if ($user->isBlocked()) {
                return [
                    'success' => false,
                    'status' => Response::HTTP_FORBIDDEN,
                    'error' => 'Tu cuenta ha sido bloqueada',
                ];
            }

            return [
                'success' => true,
                'status' => Response::HTTP_OK,
                'data' => $user,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'error' => 'Error al obtener el usuario autenticado: '.$e->getMessage(),
            ];
        }
    }
}
