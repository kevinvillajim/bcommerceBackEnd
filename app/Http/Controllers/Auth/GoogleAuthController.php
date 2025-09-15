<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\UseCases\User\GoogleLoginUseCase;
use App\UseCases\User\GoogleRegisterUseCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    protected $googleLoginUseCase;

    protected $googleRegisterUseCase;

    public function __construct(
        GoogleLoginUseCase $googleLoginUseCase,
        GoogleRegisterUseCase $googleRegisterUseCase
    ) {
        $this->googleLoginUseCase = $googleLoginUseCase;
        $this->googleRegisterUseCase = $googleRegisterUseCase;
    }

    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(Request $request)
    {
        try {
            $action = $request->get('action', 'login'); // 'login' or 'register'

            Log::info('Google OAuth redirect initiated', [
                'action' => $action,
                'session_id' => session()->getId(),
                'has_session' => session()->isStarted(),
            ]);

            // Verificar que la sesión esté funcionando
            if (! session()->isStarted()) {
                Log::error('Session not started for Google OAuth');

                return $this->handleError('Session not available. Please try again.');
            }

            // Guardar la acción en la sesión para usar en el callback
            session(['oauth_action' => $action]);
            session()->save(); // Forzar guardado de sesión

            Log::info('Session data saved', [
                'oauth_action' => session('oauth_action'),
                'session_id' => session()->getId(),
            ]);

            return Socialite::driver('google')
                ->scopes(['openid', 'profile', 'email'])
                ->with([
                    'prompt' => 'select_account',
                    'access_type' => 'offline',
                ])
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Error redirecting to Google', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->handleError('Error al conectar con Google: '.$e->getMessage());
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            Log::info('Google OAuth callback received', [
                'has_session' => session()->isStarted(),
                'session_id' => session()->getId(),
            ]);

            // Verificar que la sesión esté disponible
            if (! session()->isStarted()) {
                Log::error('Session not available in callback');

                return $this->redirectToFrontendWithError('Session expired. Please try again.');
            }

            // Obtener información del usuario de Google
            $googleUser = Socialite::driver('google')->user();

            Log::info('Google user data received', [
                'id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name' => $googleUser->getName(),
            ]);

            // Obtener la acción que se guardó en la sesión
            $action = session('oauth_action', 'login');

            Log::info('Processing OAuth action', [
                'action' => $action,
                'session_oauth_action' => session('oauth_action'),
            ]);

            // Limpiar la sesión
            session()->forget('oauth_action');
            session()->save();

            // Ejecutar el caso de uso correspondiente
            if ($action === 'register') {
                $result = $this->googleRegisterUseCase->execute($googleUser);
            } else {
                $result = $this->googleLoginUseCase->execute($googleUser);
            }

            if (isset($result['error'])) {
                Log::warning('Google OAuth failed', [
                    'error' => $result['error'],
                    'action' => $action,
                ]);

                return $this->redirectToFrontendWithError($result['error']);
            }

            Log::info('Google OAuth successful', [
                'action' => $action,
                'user_id' => $result['user']['id'] ?? 'unknown',
            ]);

            // Éxito: redirigir al frontend con el token
            return $this->redirectToFrontendWithSuccess($result);
        } catch (\Exception $e) {
            Log::error('Error in Google callback', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->redirectToFrontendWithError('Error al procesar autenticación con Google');
        }
    }

    /**
     * Handle direct Google authentication from frontend
     */
    public function authenticateWithGoogle(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'action' => 'required|in:login,register',
            ]);

            $googleToken = $request->input('token');
            $action = $request->input('action');

            Log::info('Direct Google authentication', [
                'action' => $action,
                'token_length' => strlen($googleToken),
            ]);

            // Verificar el token con Google
            $client = new \Google_Client;
            $client->setClientId(env('GOOGLE_CLIENT_ID'));

            $payload = $client->verifyIdToken($googleToken);

            if (! $payload) {
                Log::warning('Invalid Google token received');

                return response()->json([
                    'message' => 'Token de Google inválido',
                ], 401);
            }

            // Crear objeto similar al de Socialite
            $googleUser = (object) [
                'id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
                'avatar' => $payload['picture'] ?? null,
                'given_name' => $payload['given_name'] ?? null,
                'family_name' => $payload['family_name'] ?? null,
            ];

            Log::info('Google token verified', [
                'user_email' => $googleUser->email,
                'user_id' => $googleUser->id,
            ]);

            // Ejecutar el caso de uso correspondiente
            if ($action === 'register') {
                $result = $this->googleRegisterUseCase->execute($googleUser);
            } else {
                $result = $this->googleLoginUseCase->execute($googleUser);
            }

            if (isset($result['error'])) {
                Log::warning('Direct Google authentication failed', [
                    'error' => $result['error'],
                    'action' => $action,
                ]);

                return response()->json([
                    'message' => $result['error'],
                ], 400);
            }

            Log::info('Direct Google authentication successful', [
                'action' => $action,
                'user_id' => $result['user']['id'] ?? 'unknown',
            ]);

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Error in direct Google authentication', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al autenticar con Google',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle error responses
     */
    private function handleError($message)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 500);
        }

        return $this->redirectToFrontendWithError($message);
    }

    /**
     * Redirect to frontend with error
     */
    private function redirectToFrontendWithError($error)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        return redirect($frontendUrl.'/login?error='.urlencode($error));
    }

    /**
     * Redirect to frontend with success
     */
    private function redirectToFrontendWithSuccess($result)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $redirectUrl = $frontendUrl.'/auth/google/success?'.http_build_query([
            'token' => $result['access_token'],
            'user' => base64_encode(json_encode($result['user'])),
            'expires_in' => $result['expires_in'],
        ]);

        return redirect($redirectUrl);
    }
}
