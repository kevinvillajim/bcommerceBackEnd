<?php

namespace App\UseCases\User;

use App\Domain\Entities\UserEntity;
use App\Domain\Interfaces\JwtServiceInterface;
use App\Domain\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class RegisterUserUseCase
{
    private UserRepositoryInterface $userRepository;

    private JwtServiceInterface $jwtService;

    /**
     * Constructor
     */
    public function __construct(
        UserRepositoryInterface $userRepository,
        JwtServiceInterface $jwtService
    ) {
        $this->userRepository = $userRepository;
        $this->jwtService = $jwtService;
    }

    /**
     * Registra un nuevo usuario y genera su token JWT
     *
     * @param  array  $extraData  Datos adicionales como age, gender, location
     */
    public function execute(string $name, string $email, string $password, array $extraData = []): ?array
    {
        try {
            // Verificar si el email ya existe
            $existingUser = $this->userRepository->findByEmail($email);
            if ($existingUser) {
                return [
                    'success' => false,
                    'message' => 'El correo electrónico ya está registrado.',
                ];
            }

            // Crear entidad de usuario
            $userEntity = UserEntity::create(
                $name,
                $email,
                Hash::make($password),
                $extraData['age'] ?? null,
                $extraData['gender'] ?? null,
                $extraData['location'] ?? null
            );

            // Guardar usuario
            $savedUser = $this->userRepository->save($userEntity);

            if (! $savedUser) {
                return [
                    'success' => false,
                    'message' => 'Error al registrar el usuario.',
                ];
            }

            // Convertir a modelo Laravel para generar token JWT
            $user = \App\Models\User::where('email', $email)->first();

            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'Error al recuperar el usuario recién creado.',
                ];
            }

            // Generar token JWT
            /** @phpstan-ignore-next-line */
            $token = JWTAuth::fromUser($user);

            return [
                'success' => true,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->jwtService->getTokenTTL(),
                'user' => $savedUser->toArray(),
            ];
        } catch (\Exception $e) {
            Log::error('Error en RegisterUserUseCase: '.$e->getMessage(), [
                'email' => $email,
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Error interno al registrar el usuario: '.$e->getMessage(),
            ];
        }
    }
}
