<?php

namespace App\Http\Controllers;

use App\Domain\Interfaces\JwtServiceInterface;
use App\Models\Seller;
use App\Models\User;
use App\UseCases\User\FetchUserProfileUseCase;
use App\UseCases\User\UpdateProfileUseCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    private JwtServiceInterface $jwtService;

    /**
     * Constructor
     */
    public function __construct(JwtServiceInterface $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Obtiene el perfil del usuario autenticado
     */
    public function show(FetchUserProfileUseCase $getUserProfileUseCase): JsonResponse
    {
        try {
            $user = $this->jwtService->getAuthenticatedUser();

            if (! $user) {
                Log::warning('❌ Intento de acceso sin autenticación al perfil');

                return response()->json([
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            Log::info('📋 Obteniendo perfil del usuario', ['user_id' => $user->id]);

            $result = $getUserProfileUseCase->execute($user->id);

            Log::info('✅ Perfil obtenido exitosamente', ['user_id' => $user->id]);

            return response()->json(
                isset($result['data']) ? $result['data'] : $result,
                $result['status'] ?? 200
            );
        } catch (\Exception $e) {
            Log::error('❌ Error al obtener perfil: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al obtener el perfil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el perfil del usuario autenticado
     */
    public function update(Request $request, UpdateProfileUseCase $updateProfileUseCase): JsonResponse
    {
        try {
            Log::info('📤 Datos recibidos para actualizar perfil:', $request->all());

            $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'age' => 'sometimes|nullable|integer|min:0|max:120',
                'gender' => 'sometimes|nullable|string|in:Masculino,Femenino,No binario,Prefiero no decirlo',
                'location' => 'sometimes|nullable|string|max:255',
                'phone' => 'sometimes|nullable|string|max:20',
                'store_name' => 'sometimes|nullable|string|max:255',
                'store_description' => 'sometimes|nullable|string|max:1000',
            ]);

            $user = $this->jwtService->getAuthenticatedUser();

            if (! $user) {
                Log::warning('❌ Intento de actualización sin autenticación');

                return response()->json([
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            Log::info('📝 Actualizando perfil del usuario', ['user_id' => $user->id]);

            // Preparar datos básicos del usuario
            $userData = $request->only(['name', 'age', 'gender', 'location']);

            // Actualizar datos básicos del usuario si hay alguno
            if (! empty($userData)) {
                Log::info('📝 Actualizando datos básicos:', $userData);
                $updatedUser = $updateProfileUseCase->execute($user->id, $userData);

                if (! $updatedUser) {
                    Log::error('❌ No se pudo actualizar el perfil básico');

                    return response()->json([
                        'message' => 'No se pudo actualizar el perfil',
                    ], 500);
                }
            }

            // Actualizar datos adicionales directamente en el modelo
            $userModel = User::find($user->id);
            $updated = false;

            // Actualizar phone si se proporciona
            if ($request->has('phone')) {
                $userModel->phone = $request->input('phone');
                $updated = true;
                Log::info('📞 Actualizando teléfono:', ['phone' => $request->input('phone')]);
            }

            if ($updated) {
                $userModel->save();
                Log::info('✅ Datos adicionales del usuario actualizados');
            }

            // Si hay datos de seller, actualizarlos por separado
            if ($request->has('store_name') || $request->has('store_description')) {
                Log::info('🏪 Actualizando datos de seller');
                $this->updateSellerData($user->id, $request->only(['store_name', 'store_description']));
            }

            // Obtener datos actualizados del usuario completo
            $userModel = User::with('seller')->find($user->id);

            // Preparar respuesta con todos los datos
            $userData = [
                'id' => $userModel->id,
                'name' => $userModel->name,
                'email' => $userModel->email,
                'age' => $userModel->age,
                'gender' => $userModel->gender,
                'location' => $userModel->location,
                'phone' => $userModel->phone,
                'avatar' => $userModel->avatar ? Storage::url($userModel->avatar) : null,
                'store_name' => $userModel->seller->store_name ?? null,
                'store_description' => $userModel->seller->store_description ?? null,
                'email_verified_at' => $userModel->email_verified_at,
                'created_at' => $userModel->created_at,
                'updated_at' => $userModel->updated_at,
            ];

            Log::info('✅ Perfil actualizado exitosamente', ['user_id' => $user->id]);
            Log::info('📥 Datos de respuesta:', $userData);

            return response()->json($userData, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('❌ Error de validación en actualización de perfil:', $e->errors());

            return response()->json([
                'message' => 'Datos de entrada inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Error al actualizar perfil: '.$e->getMessage(), [
                'user_id' => $user->id ?? 'unknown',
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar el perfil',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Subir avatar del usuario
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        try {
            Log::info('📷 Iniciando subida de avatar');

            $request->validate([
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // máximo 5MB
            ]);

            $user = $this->jwtService->getAuthenticatedUser();

            if (! $user) {
                Log::warning('❌ Intento de subida de avatar sin autenticación');

                return response()->json([
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $userModel = User::find($user->id);

            if (! $userModel) {
                Log::error('❌ Usuario no encontrado en base de datos', ['user_id' => $user->id]);

                return response()->json([
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            Log::info('📷 Procesando avatar para usuario', [
                'user_id' => $user->id,
                'file_size' => $request->file('avatar')->getSize(),
                'file_type' => $request->file('avatar')->getMimeType(),
            ]);

            // Eliminar avatar anterior si existe
            if ($userModel->avatar && Storage::disk('public')->exists($userModel->avatar)) {
                Storage::disk('public')->delete($userModel->avatar);
                Log::info('🗑️ Avatar anterior eliminado', ['old_avatar' => $userModel->avatar]);
            }

            // Subir nuevo avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            Log::info('✅ Avatar subido exitosamente', ['path' => $avatarPath]);

            // Actualizar usuario con nueva ruta de avatar
            $userModel->avatar = $avatarPath;
            $userModel->save();

            // Obtener datos completos del usuario
            $userModel = User::with('seller')->find($user->id);

            // Preparar respuesta con todos los datos
            $userData = [
                'id' => $userModel->id,
                'name' => $userModel->name,
                'email' => $userModel->email,
                'age' => $userModel->age,
                'gender' => $userModel->gender,
                'location' => $userModel->location,
                'phone' => $userModel->phone,
                'avatar' => Storage::url($userModel->avatar),
                'store_name' => $userModel->seller->store_name ?? null,
                'store_description' => $userModel->seller->store_description ?? null,
                'email_verified_at' => $userModel->email_verified_at,
                'created_at' => $userModel->created_at,
                'updated_at' => $userModel->updated_at,
            ];

            Log::info('✅ Avatar actualizado exitosamente', ['user_id' => $user->id]);

            return response()->json($userData, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('❌ Error de validación en subida de avatar:', $e->errors());

            return response()->json([
                'message' => 'Archivo de imagen inválido',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Error al subir avatar: '.$e->getMessage(), [
                'user_id' => $user->id ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al subir el avatar',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cambiar contraseña del usuario
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            Log::info('🔐 Iniciando cambio de contraseña');

            $request->validate([
                'current_password' => 'required|string',
                'password' => 'required|string|min:6',
                'password_confirmation' => 'required|string|same:password',
            ]);

            $user = $this->jwtService->getAuthenticatedUser();

            if (! $user) {
                Log::warning('❌ Intento de cambio de contraseña sin autenticación');

                return response()->json([
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $userModel = User::find($user->id);

            if (! $userModel) {
                Log::error('❌ Usuario no encontrado para cambio de contraseña', ['user_id' => $user->id]);

                return response()->json([
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Verificar contraseña actual
            if (! Hash::check($request->current_password, $userModel->password)) {
                Log::warning('❌ Contraseña actual incorrecta', ['user_id' => $user->id]);

                return response()->json([
                    'message' => 'La contraseña actual es incorrecta',
                ], 422);
            }

            // Actualizar contraseña
            $userModel->password = Hash::make($request->password);
            $userModel->save();

            Log::info('✅ Contraseña actualizada exitosamente', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'Contraseña actualizada correctamente',
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('❌ Error de validación en cambio de contraseña:', $e->errors());

            return response()->json([
                'message' => 'Datos de contraseña inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('❌ Error al cambiar contraseña: '.$e->getMessage(), [
                'user_id' => $user->id ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al cambiar la contraseña',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar datos específicos del seller
     */
    private function updateSellerData(int $userId, array $sellerData): bool
    {
        try {
            Log::info('🏪 Iniciando actualización de datos de seller', [
                'user_id' => $userId,
                'data' => $sellerData,
            ]);

            $user = User::find($userId);

            if (! $user) {
                Log::error('❌ Usuario no encontrado para actualizar seller', ['user_id' => $userId]);

                return false;
            }

            // Verificar si el usuario es seller
            if (! $user->isSeller()) {
                Log::warning('❌ Usuario no es seller', ['user_id' => $userId]);

                return false;
            }

            $seller = $user->seller;

            if (! $seller) {
                Log::warning('❌ Usuario es seller pero no tiene registro de seller', ['user_id' => $userId]);

                return false;
            }

            $updated = false;

            // Actualizar campos de seller
            if (array_key_exists('store_name', $sellerData)) {
                $seller->store_name = $sellerData['store_name'];
                $updated = true;
                Log::info('🏪 Actualizando store_name:', ['value' => $sellerData['store_name']]);
            }

            if (array_key_exists('store_description', $sellerData)) {
                $seller->store_description = $sellerData['store_description'];
                $updated = true;
                Log::info('📝 Actualizando store_description:', ['value' => $sellerData['store_description']]);
            }

            if ($updated) {
                $seller->save();
                Log::info('✅ Datos de seller actualizados correctamente', [
                    'user_id' => $userId,
                    'seller_id' => $seller->id,
                ]);

                return true;
            }

            Log::info('ℹ️ No hay cambios en datos de seller');

            return true;
        } catch (\Exception $e) {
            Log::error('❌ Error al actualizar datos de seller: '.$e->getMessage(), [
                'user_id' => $userId,
                'seller_data' => $sellerData,
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
