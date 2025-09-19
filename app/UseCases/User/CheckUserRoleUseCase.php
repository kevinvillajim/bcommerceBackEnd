<?php

namespace App\UseCases\User;

use App\Domain\Interfaces\JwtServiceInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CheckUserRoleUseCase
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
     */
    public function execute(): array
    {
        try {
            $user = $this->jwtService->getAuthenticatedUser();

            if (! $user) {
                return [
                    'success' => false,
                    'status' => Response::HTTP_UNAUTHORIZED,
                    'message' => 'Usuario no autenticado',
                ];
            }

            // Si el usuario está bloqueado, no permitimos acceso
            if ($user->isBlocked()) {
                return [
                    'success' => false,
                    'status' => Response::HTTP_FORBIDDEN,
                    'message' => 'Tu cuenta ha sido bloqueada',
                ];
            }

            $isSeller = $user->isSeller();
            $isAdmin = $user->isAdmin();
            $isPaymentUser = $user->isPaymentUser();
            $role = $user->getRole();

            // Obtener información adicional si es seller
            $sellerInfo = null;
            if ($isSeller && $user->seller) {
                $sellerInfo = [
                    'id' => $user->seller->id,
                    'store_name' => $user->seller->store_name,
                    'status' => $user->seller->status,
                    'verification_level' => $user->seller->verification_level,
                ];
            }

            // Obtener información adicional si es admin
            $adminInfo = null;
            if ($isAdmin && $user->admin) {
                $adminInfo = [
                    'id' => $user->admin->id,
                    'role' => $user->admin->role,
                    'permissions' => $user->admin->permissions,
                ];
            }

            // Obtener información adicional si es payment user
            $paymentInfo = null;
            if ($isPaymentUser && $user->paymentUser) {
                $paymentInfo = [
                    'id' => $user->paymentUser->id,
                    'status' => $user->paymentUser->status,
                    'permissions' => $user->paymentUser->permissions,
                ];
            }

            return [
                'success' => true,
                'status' => Response::HTTP_OK,
                'data' => [
                    'user_id' => $user->id,
                    'role' => $role,
                    'is_seller' => $isSeller,
                    'is_admin' => $isAdmin,
                    'is_payment_user' => $isPaymentUser,
                    'seller_info' => $sellerInfo,
                    'admin_info' => $adminInfo,
                    'payment_info' => $paymentInfo,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error en CheckUserRoleUseCase: '.$e->getMessage(), [
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'message' => 'Error al verificar el rol del usuario: '.$e->getMessage(),
            ];
        }
    }
}
