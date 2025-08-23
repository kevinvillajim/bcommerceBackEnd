<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class PasswordResetController extends Controller
{
    protected MailService $mailService;

    public function __construct(MailService $mailService)
    {
        $this->mailService = $mailService;
    }

    /**
     * Enviar un enlace de restablecimiento de contraseña.
     * Con fines de prueba, también devuelve el token generado.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Encontrar al usuario
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No encontramos un usuario con esa dirección de correo electrónico.',
            ], 404);
        }

        // Crear un token de restablecimiento
        $token = Str::random(64);

        // Almacenar el token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token), // Almacenar el token hasheado es más seguro
                'created_at' => Carbon::now(),
            ]
        );

        // Enviar email de restablecimiento usando nuestro servicio personalizado
        $emailSent = false;
        try {
            $emailSent = $this->mailService->sendPasswordResetEmail($user, $token);

            if ($emailSent) {
                Log::info('Password reset email sent successfully', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } else {
                Log::warning('Failed to send password reset email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending password reset email: '.$e->getMessage(), [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        // Preparar respuesta base
        $response = [
            'status' => 'success',
            'message' => 'Si el correo existe en nuestro sistema, recibirás un enlace de restablecimiento.',
            'email_sent' => $emailSent,
        ];

        // En desarrollo, incluir información adicional para pruebas
        if (app()->environment(['local', 'development'])) {
            $response['debug_info'] = [
                'reset_token' => $token,
                'reset_url' => config('app.frontend_url', 'http://localhost:3000').'/reset-password?token='.$token.'&email='.urlencode($request->email),
                'email_sent_successfully' => $emailSent,
            ];
        }

        return response()->json($response);
    }

    /**
     * Verify password reset token via GET request from email link
     */
    public function verifyResetToken(Request $request)
    {
        try {
            $token = $request->query('token');
            $email = $request->query('email');
            
            if (!$token || !$email) {
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                return redirect()->to("{$frontendUrl}/forgot-password")
                    ->with('error', 'Token o email faltante');
            }

            // Obtener el registro del token
            $tokenRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->first();

            if (!$tokenRecord) {
                Log::warning('Password reset token not found', ['email' => $email, 'token' => substr($token, 0, 10).'...']);
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                return redirect()->to("{$frontendUrl}/forgot-password")
                    ->with('error', 'Token inválido o expirado');
            }

            // Verificar que el token no haya expirado (1 hora)
            if (Carbon::parse($tokenRecord->created_at)->addHour()->isPast()) {
                DB::table('password_reset_tokens')
                    ->where('email', $email)
                    ->delete();

                Log::warning('Password reset token expired', ['email' => $email, 'created_at' => $tokenRecord->created_at]);
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                return redirect()->to("{$frontendUrl}/forgot-password")
                    ->with('error', 'El token ha expirado. Solicita uno nuevo.');
            }

            // Verificar que el token coincida
            if (!Hash::check($token, $tokenRecord->token)) {
                Log::warning('Password reset token mismatch', ['email' => $email, 'token' => substr($token, 0, 10).'...']);
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                return redirect()->to("{$frontendUrl}/forgot-password")
                    ->with('error', 'Token inválido');
            }

            // Token válido, redirigir a la página de reset con los parámetros
            Log::info('Password reset token verified successfully', ['email' => $email, 'token' => substr($token, 0, 10).'...']);
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect()->to("{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($email) . "&verified=true");

        } catch (\Exception $e) {
            Log::error('Exception in PasswordResetController@verifyResetToken', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect()->to("{$frontendUrl}/forgot-password")
                ->with('error', 'Error interno del servidor');
        }
    }

    /**
     * Usuario solicita un token de recuperación (versión de prueba).
     * En esta versión, el token se devuelve directamente en la respuesta.
     * En producción, este método enviará el token por SMS.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function requestToken(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Verificar que el usuario exista
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'No encontramos un usuario con esa dirección de correo electrónico.',
            ], 404);
        }

        // Generar un token numérico de 6 dígitos (fácil de comunicar)
        $token = strval(mt_rand(100000, 999999));

        // Almacenar el token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );

        // En producción, aquí enviaríamos el token por SMS
        // Pero para pruebas, lo devolvemos directamente en la respuesta

        return response()->json([
            'status' => 'success',
            'message' => 'Token de recuperación generado correctamente.',
            'user_email' => $user->email,
            'reset_token' => $token,  // Solo para pruebas
            'expires_at' => Carbon::now()->addHour()->toDateTimeString(),
        ]);
    }

    /**
     * Restablecer la contraseña utilizando un token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Obtener el registro del token
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $tokenRecord) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token o correo electrónico inválido.',
            ], 400);
        }

        // Verificar que el token no haya expirado (1 hora)
        if (Carbon::parse($tokenRecord->created_at)->addHour()->isPast()) {
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, solicita un nuevo enlace de restablecimiento.',
            ], 400);
        }

        // Verificar que el token coincida
        if (! Hash::check($request->token, $tokenRecord->token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido.',
                'debug' => [
                    'received_token' => $request->token,
                    'stored_token_hash' => $tokenRecord->token,
                    'hash_check_result' => false,
                ],
            ], 400);
        }

        // Buscar y actualizar al usuario
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        // Actualizar la contraseña
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // Eliminar el token usado
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'status' => 'success',
            'message' => '¡Tu contraseña ha sido restablecida!',
        ]);
    }

    /**
     * Validar un token de restablecimiento sin cambiar la contraseña.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateToken(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        // Obtener el registro del token
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $tokenRecord) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token o correo electrónico inválido.',
                'valid' => false,
            ], 400);
        }

        // Verificar que el token no haya expirado (1 hora)
        if (Carbon::parse($tokenRecord->created_at)->addHour()->isPast()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token ha expirado. Por favor, solicita un nuevo token de restablecimiento.',
                'valid' => false,
            ], 400);
        }

        // Verificar que el token coincida
        if (! Hash::check($request->token, $tokenRecord->token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token inválido.',
                'valid' => false,
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Token válido.',
            'valid' => true,
        ]);
    }
}
