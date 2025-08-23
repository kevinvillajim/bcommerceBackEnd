<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoginUserUseCase
{
    protected $jwtService;

    /**
     * Create a new use case instance.
     *
     * @return void
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Execute the use case
     */
    public function execute(array $credentials): ?array
    {
        try {
            // Attempt authentication
            if (! Auth::attempt($credentials)) {
                Log::info('Fallo de autenticación', ['email' => $credentials['email']]);

                return ['error' => 'Correo o contraseña incorrectos.'];
            }

            /** @var User $user */
            $user = Auth::user();

            // Verificar si el usuario está bloqueado
            if ($user->isBlocked()) {
                Log::warning('Intento de login de usuario bloqueado', ['user_id' => $user->id]);

                return ['error' => 'Tu cuenta ha sido bloqueada. Por favor, contacta con soporte.'];
            }

            // Generar token
            $token = $this->jwtService->generateToken($user);

            return [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->jwtService->getTokenTTL(),
                'user' => $user,
            ];
        } catch (\Exception $e) {
            Log::error('Error al iniciar sesión', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['error' => 'Ha ocurrido un error al intentar iniciar sesión. Intenta nuevamente.'];
        }
    }
}
