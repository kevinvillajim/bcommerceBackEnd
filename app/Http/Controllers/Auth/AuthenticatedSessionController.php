<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        $user = $request->user();

        // Check if user is blocked
        if ($user->isBlocked()) {
            Auth::logout();

            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Your account has been blocked.',
            ], 403);
        }
        
        // Check if user is a suspended seller
        if ($user->role === 'seller') {
            $seller = \App\Models\Seller::where('user_id', $user->id)->first();
            if ($seller && $seller->status === 'suspended') {
                Auth::logout();
                
                return response()->json([
                    'error' => 'Account Suspended',
                    'message' => 'Tu cuenta de vendedor ha sido suspendida. Contacta al administrador para más información.',
                ], 403);
            }
            
            if ($seller && $seller->status === 'inactive') {
                Auth::logout();
                
                return response()->json([
                    'error' => 'Account Inactive',
                    'message' => 'Tu cuenta de vendedor está inactiva. Contacta al administrador para reactivarla.',
                ], 403);
            }
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            // Invalidate the token
            JWTAuth::invalidate(JWTAuth::getToken());

            // Logout from Laravel's built-in authentication
            Auth::logout();

            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Logout failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
