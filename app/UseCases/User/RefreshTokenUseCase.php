<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;

class RefreshTokenUseCase
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
     * @param  bool  $forceForever  Si se debe establecer el token refrescado con una duración indefinida
     * @param  bool  $resetClaims  Si se deben restablecer los claims personalizados
     */
    public function execute(bool $forceForever = false, bool $resetClaims = false): array
    {
        try {
            $token = $this->jwtService->refreshToken($forceForever, $resetClaims);

            if (! $token) {
                return [
                    'success' => false,
                    'status' => Response::HTTP_UNAUTHORIZED,
                    'error' => 'No se pudo refrescar el token',
                ];
            }

            // Intentar obtener el usuario
            $user = $this->jwtService->getUserFromToken($token);

            return [
                'success' => true,
                'status' => Response::HTTP_OK,
                'data' => [
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $this->jwtService->getTokenTTL(),
                    'user' => $user ?? null,
                ],
            ];
        } catch (JWTException $e) {
            Log::error('Error JWT en RefreshTokenUseCase: '.$e->getMessage(), [
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => Response::HTTP_UNAUTHORIZED,
                'error' => 'Error al refrescar el token: '.$e->getMessage(),
            ];
        } catch (\Exception $e) {
            Log::error('Error en RefreshTokenUseCase: '.$e->getMessage(), [
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'error' => 'Error interno al refrescar el token: '.$e->getMessage(),
            ];
        }
    }
}
