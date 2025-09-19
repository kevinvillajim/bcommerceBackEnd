<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Repositories\UserRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\PaymentUser;
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
                    $userData['is_payment_user'] = $userModel->isPaymentUser();
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
                $userData['is_payment_user'] = $user->isPaymentUser();
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
     * Cambiar rol de usuario de manera centralizada
     */
    public function changeRole(int $id, Request $request): JsonResponse
    {
        try {
            $request->validate([
                'role' => 'required|string|in:customer,seller,admin,payment',
                'store_name' => 'nullable|string|min:3|max:100|unique:sellers,store_name,' . $id . ',user_id',
                'description' => 'nullable|string|max:500',
            ]);

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            $newRole = $request->role;
            $currentRoles = [
                'admin' => $user->isAdmin(),
                'seller' => $user->isSeller(),
                'payment' => $user->isPaymentUser(),
            ];

            // Verificación de seguridad: no eliminar el último admin
            if ($currentRoles['admin'] && $newRole !== 'admin') {
                $adminCount = User::whereHas('admin', function ($query) {
                    $query->where('status', 'active');
                })->count();

                if ($adminCount <= 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede quitar el rol de administrador al último administrador del sistema',
                    ], 400);
                }
            }

            DB::beginTransaction();

            try {
                // Desactivar roles actuales
                if ($currentRoles['admin']) {
                    $user->admin()->update(['status' => 'inactive']);
                }
                if ($currentRoles['seller']) {
                    $user->seller()->update(['status' => 'inactive']);
                }
                if ($currentRoles['payment']) {
                    $user->paymentUser()->update(['status' => 'inactive']);
                }

                // Activar o crear el nuevo rol
                switch ($newRole) {
                    case 'admin':
                        if (!$user->admin()->exists()) {
                            $user->admin()->create([
                                'role' => 'customer_support',
                                'permissions' => json_encode(['users', 'products', 'orders']),
                                'status' => 'active',
                            ]);
                        } else {
                            $user->admin()->update(['status' => 'active']);
                        }
                        break;

                    case 'seller':
                        if (!$user->seller()->exists()) {
                            if (!$request->store_name) {
                                throw new \Exception('El nombre de la tienda es requerido para convertir en vendedor');
                            }

                            $user->seller()->create([
                                'store_name' => $request->store_name,
                                'description' => $request->description ?? '',
                                'status' => 'active',
                                'verification_level' => 'basic',
                                'total_sales' => 0,
                                'is_featured' => false,
                            ]);
                        } else {
                            $user->seller()->update(['status' => 'active']);
                        }
                        break;

                    case 'payment':
                        if (!$user->paymentUser()->exists()) {
                            $user->paymentUser()->create([
                                'status' => 'active',
                                'permissions' => ['external_payments'],
                            ]);
                        } else {
                            $user->paymentUser()->update(['status' => 'active']);
                        }
                        break;

                    case 'customer':
                        // Solo desactivar roles, no crear nada nuevo
                        break;

                    default:
                        throw new \Exception('Rol no válido');
                }

                DB::commit();

                Log::info('Rol de usuario cambiado por administrador', [
                    'user_id' => $id,
                    'user_email' => $user->email,
                    'new_role' => $newRole,
                    'previous_roles' => $currentRoles,
                    'admin_user_id' => auth()->id(),
                ]);

                $roleMessages = [
                    'customer' => 'Usuario convertido a cliente correctamente',
                    'seller' => 'Usuario convertido a vendedor correctamente',
                    'admin' => 'Usuario convertido a administrador correctamente',
                    'payment' => 'Usuario convertido a usuario de pagos correctamente',
                ];

                return response()->json([
                    'success' => true,
                    'message' => $roleMessages[$newRole],
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al cambiar rol de usuario: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar rol de usuario',
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
