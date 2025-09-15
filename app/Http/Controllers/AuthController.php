<?php

namespace App\Http\Controllers;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Helpers\TokenHelper;
use App\Models\User;
use App\Services\ConfigurationService;
use App\UseCases\User\UpdateProfileUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $jwtService;

    protected $configService;

    /**
     * Create a new use case instance.
     *
     * @return void
     */
    public function __construct(JwtServiceInterface $jwtService, ConfigurationService $configService)
    {
        $this->jwtService = $jwtService;
        $this->configService = $configService;
    }

    /**
     * Check if account is locked due to failed attempts
     *
     * @param  string  $email
     * @return bool
     */
    private function isAccountLocked($email)
    {
        $lockKey = "account_lock:$email";

        return Cache::has($lockKey);
    }

    /**
     * Get failed attempts count for an email
     *
     * @param  string  $email
     * @return int
     */
    private function getFailedAttempts($email)
    {
        $key = "failed_attempts:$email";

        return Cache::get($key, 0);
    }

    /**
     * Increment failed attempts and lock account if needed
     *
     * @param  string  $email
     * @return void
     */
    private function handleFailedAttempt($email)
    {
        $maxAttempts = $this->configService->getConfig('security.accountLockAttempts', 5);
        $key = "failed_attempts:$email";
        $lockKey = "account_lock:$email";

        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addMinutes(30)); // Keep track for 30 minutes

        if ($attempts >= $maxAttempts) {
            // Lock account for 30 minutes
            Cache::put($lockKey, true, now()->addMinutes(30));
            Log::warning('Account locked due to failed attempts', [
                'email' => $email,
                'attempts' => $attempts,
            ]);
        }
    }

    /**
     * Clear failed attempts for successful login
     *
     * @param  string  $email
     * @return void
     */
    private function clearFailedAttempts($email)
    {
        $key = "failed_attempts:$email";
        $lockKey = "account_lock:$email";
        Cache::forget($key);
        Cache::forget($lockKey);
    }

    /**
     * Get a JWT token via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ], [
                'email.required' => 'El email es obligatorio.',
                'email.email' => 'El email debe ser una dirección válida.',
                'password.required' => 'La contraseña es obligatoria.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Los datos proporcionados no son válidos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Intentar autenticar
            $credentials = $request->only('email', 'password');

            // Check if account is temporarily locked
            if ($this->isAccountLocked($credentials['email'])) {
                Log::warning('Login attempt on locked account', [
                    'email' => $credentials['email'],
                ]);

                return response()->json([
                    'message' => 'Cuenta temporalmente bloqueada por múltiples intentos fallidos. Inténtalo de nuevo más tarde.',
                    'error' => 'Cuenta temporalmente bloqueada',
                ], 423); // 423 Locked
            }

            // Buscar usuario por email
            $user = User::where('email', $credentials['email'])->first();

            // Verificar si el usuario existe
            if (! $user) {
                $this->handleFailedAttempt($credentials['email']);
                Log::warning('Login attempt with non-existent email', [
                    'email' => $credentials['email'],
                ]);

                return response()->json([
                    'message' => 'Email no encontrado.',
                    'error' => 'Email no encontrado',
                ], 404);
            }

            // Verificar contraseña
            if (! Hash::check($credentials['password'], $user->password)) {
                $this->handleFailedAttempt($credentials['email']);
                Log::warning('Failed login attempt', [
                    'email' => $credentials['email'],
                ]);

                return response()->json([
                    'message' => 'Contraseña incorrecta.',
                    'error' => 'Contraseña incorrecta',
                ], 401);
            }

            // Verificar si el usuario está bloqueado
            if ($user->isBlocked()) {
                Log::warning('Login attempt for blocked user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return response()->json([
                    'message' => 'Tu cuenta ha sido bloqueada por un administrador.',
                    'error' => 'Cuenta bloqueada',
                ], 403);
            }

            // Verificar si el email está verificado
            if (! $user->hasVerifiedEmail()) {
                Log::info('Login attempt with unverified email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return response()->json([
                    'message' => 'Debes verificar tu dirección de correo electrónico antes de iniciar sesión. Revisa tu bandeja de entrada y haz clic en el enlace de verificación.',
                    'error' => 'Email no verificado',
                    'error_code' => 'EMAIL_NOT_VERIFIED',
                    'user_email' => $user->email,
                ], 409); // 409 Conflict - standard code for email verification required
            }

            // Clear failed attempts on successful authentication
            $this->clearFailedAttempts($credentials['email']);

            // Check seller status and create notification if needed
            $seller = \App\Models\Seller::where('user_id', $user->id)->first();
            \Log::info('🔍 DEBUG - Seller encontrado:', ['seller_id' => $seller?->id, 'status' => $seller?->status, 'user_id' => $user->id]);

            if ($seller && in_array($seller->status, ['suspended', 'inactive'])) {
                \Log::info('🚨 Seller con status problemático detectado durante login', [
                    'user_id' => $user->id,
                    'seller_id' => $seller->id,
                    'seller_status' => $seller->status,
                    'store_name' => $seller->store_name,
                ]);

                // Determinar el tipo de notificación específico
                $notificationType = $seller->status === 'suspended' ? 'seller_suspended' : 'seller_inactive';

                // Verificar si ya existe una notificación NO LEÍDA del tipo específico
                $unreadNotification = \App\Models\Notification::where('user_id', $user->id)
                    ->where('type', $notificationType)
                    ->where('read', false)
                    ->first();

                $shouldCreateNotification = false;

                if (! $unreadNotification) {
                    // No hay notificación no leída del tipo específico, crear una nueva
                    $shouldCreateNotification = true;
                    \Log::info('✅ No hay notificación no leída específica para status, creando nueva', [
                        'user_id' => $user->id,
                        'notification_type' => $notificationType,
                        'seller_status' => $seller->status,
                    ]);
                } else {
                    \Log::info('ℹ️ Ya existe notificación no leída del tipo específico', [
                        'user_id' => $user->id,
                        'notification_id' => $unreadNotification->id,
                        'notification_type' => $notificationType,
                        'seller_status' => $seller->status,
                    ]);
                }

                if ($shouldCreateNotification) {
                    // Preparar mensajes específicos y detallados
                    if ($seller->status === 'suspended') {
                        $title = 'Cuenta de vendedor suspendida';
                        $message = 'Tu cuenta de vendedor ha sido suspendida. Puedes ver tus datos históricos pero no realizar nuevas ventas. Contacta al administrador para más información.';
                    } else { // inactive
                        $title = 'Cuenta de vendedor desactivada';
                        $message = 'Tu cuenta de vendedor ha sido desactivada. Contacta al administrador para reactivar tu cuenta.';
                    }

                    try {
                        $notification = \App\Models\Notification::create([
                            'user_id' => $user->id,
                            'type' => $notificationType,
                            'title' => $title,
                            'message' => $message,
                            'read' => false,
                            'data' => [
                                'seller_status' => $seller->status,
                                'store_name' => $seller->store_name,
                            ],
                        ]);

                        \Log::info('✅ Notificación específica creada exitosamente', [
                            'user_id' => $user->id,
                            'notification_id' => $notification->id,
                            'notification_type' => $notificationType,
                            'seller_status' => $seller->status,
                            'title' => $title,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('❌ Error al crear notificación específica para seller', [
                            'user_id' => $user->id,
                            'seller_status' => $seller->status,
                            'notification_type' => $notificationType,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }

            // Generar token JWT
            try {
                $token = JWTAuth::fromUser($user);
            } catch (JWTException $e) {
                Log::error('JWT Token generation error', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                ]);

                return response()->json(['error' => 'No se pudo generar el token'], 500);
            }

            // Obtener configuración de timeout de sesión
            $timeoutMinutes = config('session_timeout.ttl');
            $expiresAt = now()->addMinutes($timeoutMinutes);

            // Devolver respuesta
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => TokenHelper::getExpiresInSeconds(),
                'user' => $user,
                'session_config' => [
                    'timeout_minutes' => $timeoutMinutes,
                    'expires_at' => $expiresAt->toISOString(),
                ],
            ]);
        } catch (ValidationException $e) {
            // Manejar errores de validación
            Log::error('Login validation error', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'error' => 'Error de validación',
                'details' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Manejar cualquier otro error
            Log::error('Unexpected login error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        try {
            // Attempt to get authenticated user
            $user = JWTAuth::parseToken()->authenticate();

            return response()->json($user);
        } catch (\Tymon\JWTAuth\Exceptions\UserNotDefinedException $e) {
            return response()->json(['error' => 'User not found'], 404);
        }
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        try {
            // Invalidate current token
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Successfully logged out']);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Failed to logout'], 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        try {
            // Refresh the token
            $token = JWTAuth::refresh();

            // Validate the refreshed token
            $tokenValidation = TokenHelper::validateToken($token);

            if (! $tokenValidation['valid']) {
                return response()->json(['error' => 'Could not refresh token'], 401);
            }

            // Obtener configuración de timeout de sesión
            $timeoutMinutes = config('session_timeout.ttl');
            $expiresAt = now()->addMinutes($timeoutMinutes);

            $response = response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => TokenHelper::getExpiresInSeconds(),
                'user' => $tokenValidation['user'],
            ]);

            // Agregar headers de sesión para sincronización del frontend
            $response->headers->set('X-Session-Timeout', $timeoutMinutes * 60); // en segundos
            $response->headers->set('X-Session-Expires', $expiresAt->toISOString());

            return $response;
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not refresh token'], 401);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string  $token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        try {
            $user = JWTAuth::setToken($token)->authenticate();

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $this->jwtService->getTokenTTL(),
                'user' => $user,
            ]);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not retrieve user'], 500);
        }
    }

    /**
     * Actualiza el perfil del usuario autenticado
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request, UpdateProfileUseCase $updateProfileUseCase)
    {
        try {
            // Validar datos de entrada
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'age' => 'sometimes|nullable|integer|min:0|max:120',
                'gender' => 'sometimes|nullable|string|in:Masculino,Femenino,No binario,Prefiero no decirlo',
                'location' => 'sometimes|nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Los datos proporcionados no son válidos.',
                    'errors' => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Obtener usuario autenticado mediante JWT token
            $user = JWTAuth::parseToken()->authenticate();

            if (! $user) {
                return response()->json([
                    'message' => 'Usuario no autenticado',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Ejecutar caso de uso para actualizar perfil
            $updatedUser = $updateProfileUseCase->execute(
                $user->id,
                $request->only(['name', 'age', 'gender', 'location'])
            );

            if (! $updatedUser) {
                return response()->json([
                    'message' => 'No se pudo actualizar el perfil',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Convertir entidad a array para respuesta
            $userData = [
                'id' => $updatedUser->getId(),
                'name' => $updatedUser->getName(),
                'email' => $updatedUser->getEmail(),
                'age' => $updatedUser->getAge(),
                'gender' => $updatedUser->getGender(),
                'location' => $updatedUser->getLocation(),
                'email_verified_at' => $updatedUser->getEmailVerifiedAt(),
                'created_at' => $updatedUser->getCreatedAt(),
                'updated_at' => $updatedUser->getUpdatedAt(),
            ];

            return response()->json($userData, Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el perfil',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
