<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleRegisterUseCase
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
     * Execute Google registration
     *
     * @param  object  $googleUser
     */
    public function execute($googleUser): array
    {
        try {
            Log::info('Executing Google registration', ['email' => $googleUser->email]);

            // Verificar si ya existe un usuario con este email
            $existingUser = User::where('email', $googleUser->email)->first();

            if ($existingUser) {
                // Si el usuario ya existe, hacer login en lugar de registro
                Log::info('User already exists, performing login instead', ['email' => $googleUser->email]);

                $googleLoginUseCase = app(GoogleLoginUseCase::class);

                return $googleLoginUseCase->execute($googleUser);
            }

            // Verificar si ya existe un usuario con este google_id
            if (isset($googleUser->id)) {
                $existingGoogleUser = User::where('google_id', $googleUser->id)->first();
                if ($existingGoogleUser) {
                    Log::warning('User with this Google ID already exists', [
                        'google_id' => $googleUser->id,
                        'existing_email' => $existingGoogleUser->email,
                    ]);

                    return [
                        'error' => 'Esta cuenta de Google ya está asociada a otro usuario.',
                    ];
                }
            }

            // Crear nuevo usuario
            $userData = [
                'name' => $googleUser->name,
                'email' => $googleUser->email,
                'password' => Hash::make(Str::random(32)), // Password aleatorio ya que usa Google
                'google_id' => $googleUser->id ?? null,
                'avatar' => $googleUser->avatar ?? null,
                'email_verified_at' => now(), // Email verificado por Google
            ];

            // Extraer nombre y apellido si están disponibles
            if (isset($googleUser->given_name) || isset($googleUser->family_name)) {
                $userData['first_name'] = $googleUser->given_name ?? '';
                $userData['last_name'] = $googleUser->family_name ?? '';

                // Si no hay nombre completo, construirlo
                if (empty($userData['name'])) {
                    $userData['name'] = trim($userData['first_name'].' '.$userData['last_name']);
                }
            }

            $user = User::create($userData);

            Log::info('Google user created successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Generar token JWT
            $token = $this->jwtService->generateToken($user);

            return [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->jwtService->getTokenTTL(),
                'user' => $user->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Error in Google registration', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'email' => $googleUser->email ?? 'unknown',
            ]);

            return [
                'error' => 'Ha ocurrido un error al registrarse con Google. Intenta nuevamente.',
            ];
        }
    }
}
