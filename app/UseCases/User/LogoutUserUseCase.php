<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LogoutUserUseCase
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
     *
     * @param  bool  $forceForever  Si se debe invalidar el token permanentemente
     */
    public function execute(bool $forceForever = false): array
    {
        try {
            $success = $this->jwtService->invalidateToken($forceForever);

            if (! $success) {
                Log::warning('No se pudo invalidar el token JWT', [
                    'force_forever' => $forceForever,
                ]);

                return [
                    'success' => false,
                    'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'error' => 'Error al cerrar sesión',
                ];
            }

            return [
                'success' => true,
                'status' => Response::HTTP_OK,
                'message' => 'Sesión cerrada correctamente',
            ];
        } catch (\Exception $e) {
            Log::error('Error en LogoutUserUseCase: '.$e->getMessage(), [
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'error' => 'Error al cerrar sesión: '.$e->getMessage(),
            ];
        }
    }
}
