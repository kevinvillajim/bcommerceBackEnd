<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Seller;
use App\Models\User;
use App\Services\Mail\MailManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Obtener lista de usuarios paginada
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);

            // Obtener usuarios con paginación
            $result = $this->userRepository->findAll($page, $perPage);

            // Agregar información adicional como conteo de órdenes
            foreach ($result['data'] as &$user) {
                // Si el usuario es un modelo, convertirlo a array primero
                if (is_object($user) && method_exists($user, 'toArray')) {
                    $userData = $user->toArray();
                } else {
                    $userData = $user;
                }

                // Buscar usuario para obtener relaciones
                $userModel = User::find($userData['id']);
                if ($userModel) {
                    $userData['orders_count'] = $userModel->orders()->count();
                    $userData['last_login_at'] = $userModel->last_login_at ?? null;
                    $userData['is_seller'] = $userModel->isSeller();
                    $userData['is_admin'] = $userModel->isAdmin();
                    $userData['is_blocked'] = $userModel->isBlocked();
                }

                $user = $userData;
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
                'meta' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalle de un usuario
     */
    public function show(int $id): JsonResponse
    {
        try {
            $userEntity = $this->userRepository->findById($id);

            if (! $userEntity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Obtener datos adicionales del usuario
            $user = User::find($id);
            $userData = $userEntity->toArray();

            if ($user) {
                $userData['orders_count'] = $user->orders()->count();
                $userData['last_login_at'] = $user->last_login_at ?? null;
                $userData['is_seller'] = $user->isSeller();
                $userData['is_admin'] = $user->isAdmin();
                $userData['is_blocked'] = $user->isBlocked();
                $userData['strikes_count'] = $user->getStrikeCount();
            }

            return response()->json([
                'success' => true,
                'data' => $userData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalle de usuario: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle de usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bloquear usuario
     */
    public function block(int $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            $user->block();

            return response()->json([
                'success' => true,
                'message' => 'Usuario bloqueado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al bloquear usuario: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al bloquear usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desbloquear usuario
     */
    public function unblock(int $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            $user->unblock();

            return response()->json([
                'success' => true,
                'message' => 'Usuario desbloqueado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al desbloquear usuario: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al desbloquear usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar correo de restablecimiento de contraseña
     */
    public function resetPassword(int $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Generar token de restablecimiento
            $token = Str::random(60);

            // Guardar token en la base de datos
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'email' => $user->email,
                    'token' => hash('sha256', $token),
                    'created_at' => now(),
                ]
            );

            // Usar el MailManager directamente para enviar el correo
            $mailManager = app(MailManager::class);

            // Enviar correo de restablecimiento
            $emailSent = $mailManager->sendPasswordResetEmail($user, $token);

            if ($emailSent) {
                Log::info('Password reset email sent by admin', [
                    'admin_id' => auth()->user()->id,
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Correo de restablecimiento de contraseña enviado correctamente',
                ]);
            } else {
                Log::error('Failed to send password reset email', [
                    'user_id' => $id,
                    'user_email' => $user->email,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar correo de restablecimiento de contraseña',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Exception in AdminUserController@resetPassword', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar correo de restablecimiento de contraseña',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Convertir usuario en administrador
     */
    public function makeAdmin(int $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Verificar si ya es administrador
            if ($user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario ya es administrador',
                ], 400);
            }

            // Crear registro de admin para el usuario - CORREGIDO: Usar valor válido de la enumeración
            if (! $user->admin()->exists()) {
                $user->admin()->create([
                    'role' => 'customer_support', // Valor corregido según la enumeración
                    'permissions' => json_encode(['users', 'products', 'orders']),
                    'status' => 'active',
                ]);
            } else {
                // Activar el rol de admin si existe pero está inactivo
                $user->admin()->update([
                    'status' => 'active',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Usuario convertido en administrador correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al convertir usuario en administrador: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al convertir usuario en administrador',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convertir usuario en vendedor
     */
    public function makeSeller(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'store_name' => 'required|string|min:3|max:100|unique:sellers,store_name',
                'description' => 'nullable|string|max:500',
            ]);

            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Verificar si ya es vendedor
            if ($user->isSeller()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario ya es vendedor',
                ], 400);
            }

            // Crear registro de vendedor para el usuario
            $seller = new Seller;
            $seller->user_id = $id;
            $seller->store_name = $request->store_name;
            $seller->description = $request->description ?? '';
            $seller->status = 'active'; // El admin lo crea como activo directamente
            $seller->verification_level = 'basic';
            // $seller->commission_rate = 10.00; // TODO: Implementar comisiones individuales en el futuro - usar configuración global del admin (se obtiene dinámicamente)
            $seller->total_sales = 0;
            $seller->is_featured = false;
            $seller->save();

            return response()->json([
                'success' => true,
                'message' => 'Usuario convertido en vendedor correctamente',
                'data' => $seller,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al convertir usuario en vendedor: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al convertir usuario en vendedor',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convertir usuario en usuario de pagos
     */
    public function makePaymentUser(int $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Verificar si ya es administrador con rol payment
            if ($user->isAdmin()) {
                $admin = $user->admin;
                if ($admin->role === 'payment') {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario ya tiene rol de pagos',
                    ], 400);
                }

                // Si ya es admin pero con otro rol, actualizar a payment
                $admin->update([
                    'role' => 'payment',
                    'permissions' => json_encode(['external_payments']),
                    'status' => 'active',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Usuario actualizado a rol de pagos correctamente',
                ]);
            }

            // Crear registro de admin con rol payment para el usuario
            $user->admin()->create([
                'role' => 'payment',
                'permissions' => json_encode(['external_payments']),
                'status' => 'active',
            ]);

            Log::info('Usuario convertido a rol payment por administrador', [
                'payment_user_id' => $id,
                'payment_user_email' => $user->email,
                'admin_user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario convertido a rol de pagos correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al convertir usuario a rol de pagos: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al convertir usuario a rol de pagos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = User::find($id);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Verificar si es el último administrador
            if ($user->isAdmin()) {
                $adminCount = User::whereHas('admin', function ($query) {
                    $query->where('status', 'active');
                })->count();

                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar el último administrador del sistema',
                    ], 400);
                }
            }

            // Realizar soft delete
            $user->delete();

            Log::info('Usuario eliminado por administrador', [
                'deleted_user_id' => $id,
                'deleted_user_email' => $user->email,
                'admin_user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar usuario: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
