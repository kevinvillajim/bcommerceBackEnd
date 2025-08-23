<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class GoogleLoginUseCase
{
    protected $jwtService;

    protected $userRepository;

    public function __construct(
        JwtServiceInterface $jwtService,
        UserRepositoryInterface $userRepository
    ) {
        $this->jwtService = $jwtService;
        $this->userRepository = $userRepository;
    }

    /**
     * Execute Google login
     *
     * @param  object  $googleUser
     */
    public function execute($googleUser): array
    {
        try {
            Log::info('Executing Google login', ['email' => $googleUser->email]);

            // Buscar usuario existente por email
            $user = User::where('email', $googleUser->email)->first();

            if (! $user) {
                Log::info('User not found for Google login', ['email' => $googleUser->email]);

                return [
                    'error' => 'No existe una cuenta con este correo electrónico. ¿Deseas registrarte?',
                ];
            }

            // Verificar si el usuario está bloqueado
            if ($user->isBlocked()) {
                Log::warning('Blocked user attempted Google login', ['user_id' => $user->id]);

                return [
                    'error' => 'Tu cuenta ha sido bloqueada. Por favor, contacta con soporte.',
                ];
            }

            // Actualizar información del usuario con datos de Google si es necesario
            $this->updateUserGoogleInfo($user, $googleUser);

            // Generar token JWT
            $token = $this->jwtService->generateToken($user);

            Log::info('Google login successful', ['user_id' => $user->id]);

            return [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->jwtService->getTokenTTL(),
                'user' => $user->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in Google login', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $googleUser->email ?? 'unknown',
            ]);

            return [
                'error' => 'Ha ocurrido un error al iniciar sesión con Google. Intenta nuevamente.',
            ];
        }
    }

    /**
     * Update user with Google information
     *
     * @param  object  $googleUser
     */
    private function updateUserGoogleInfo(User $user, $googleUser): void
    {
        try {
            $updated = false;

            // Actualizar google_id si no existe
            if (! $user->google_id && isset($googleUser->id)) {
                $user->google_id = $googleUser->id;
                $updated = true;
            }

            // Actualizar avatar si no existe o si el de Google es más reciente
            if (
                isset($googleUser->avatar) &&
                (! $user->avatar || strpos($user->avatar, 'googleusercontent.com') !== false)
            ) {
                $user->avatar = $googleUser->avatar;
                $updated = true;
            }

            // Marcar email como verificado si viene de Google
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
                $updated = true;
            }

            if ($updated) {
                $user->save();
                Log::info('User updated with Google info', ['user_id' => $user->id]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating user Google info', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            // No lanzamos excepción para no interrumpir el login
        }
    }
}
