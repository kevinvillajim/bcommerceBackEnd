<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EmailVerificationController extends Controller
{
    private EmailVerificationService $emailVerificationService;

    private MailService $mailService;

    public function __construct(
        EmailVerificationService $emailVerificationService,
        MailService $mailService
    ) {
        $this->emailVerificationService = $emailVerificationService;
        $this->mailService = $mailService;
    }

    /**
     * Verify email using GET request from email link
     */
    public function verify(Request $request)
    {
        try {
            $token = $request->query('token');
            
            if (!$token) {
                $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
                return redirect()->to("{$frontendUrl}/email-verification-pending")
                    ->with('error', 'Token de verificación requerido');
            }

            $result = $this->emailVerificationService->verifyEmail($token);
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            // Redirect to frontend based on result
            switch ($result['status']) {
                case 'success':
                    Log::info('Email verification successful via GET', ['token' => substr($token, 0, 10).'...']);
                    return redirect()->to("{$frontendUrl}/email-verification-success?status=success");
                    
                case 'already_verified':
                    Log::info('Email already verified via GET', ['token' => substr($token, 0, 10).'...']);
                    return redirect()->to("{$frontendUrl}/email-verification-success?status=already_verified");
                    
                case 'error':
                default:
                    Log::warning('Email verification failed via GET', [
                        'token' => substr($token, 0, 10).'...',
                        'message' => $result['message'] ?? 'Unknown error'
                    ]);
                    return redirect()->to("{$frontendUrl}/email-verification-pending")
                        ->with('error', $result['message'] ?? 'Error al verificar el email');
            }

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@verify', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect()->to("{$frontendUrl}/email-verification-pending")
                ->with('error', 'Error interno del servidor');
        }
    }

    /**
     * Verify email using token (POST method for API compatibility)
     */
    public function verifyEmail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|min:32|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token de verificación requerido',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $token = $request->input('token');
            $result = $this->emailVerificationService->verifyEmail($token);

            // Set appropriate HTTP status code based on result
            $statusCode = 200;
            switch ($result['status']) {
                case 'error':
                    $statusCode = 400;
                    break;
                case 'already_verified':
                    $statusCode = 200;
                    break;
                case 'success':
                    $statusCode = 200;
                    break;
            }

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@verifyEmail', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email requerido y debe existir en el sistema',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $email = $request->input('email');
            $user = User::where('email', $email)->first();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            $result = $this->emailVerificationService->resendVerificationEmail($user);

            // Set appropriate HTTP status code based on result
            $statusCode = 200;
            switch ($result['status']) {
                case 'error':
                    $statusCode = 400;
                    break;
                case 'rate_limited':
                    $statusCode = 429;
                    break;
                case 'already_verified':
                case 'bypassed':
                case 'success':
                    $statusCode = 200;
                    break;
            }

            return response()->json($result, $statusCode);

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@resendVerification', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Get verification status for authenticated user
     */
    public function getVerificationStatus(Request $request)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            $status = $this->emailVerificationService->getVerificationStatus($user);

            return response()->json([
                'status' => 'success',
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@getVerificationStatus', [
                'user_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Test mail configuration (admin only)
     */
    public function testMailConfiguration(Request $request)
    {
        try {
            // This should be protected by admin middleware
            $result = $this->mailService->testConnection();

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@testMailConfiguration', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al probar la configuración de correo',
            ], 500);
        }
    }

    /**
     * Get mail configuration (admin only)
     */
    public function getMailConfiguration(Request $request)
    {
        try {
            // This should be protected by admin middleware
            $config = $this->mailService->getMailConfiguration();

            // Hide password for security
            if (isset($config['password'])) {
                $config['password'] = '****';
            }

            return response()->json([
                'status' => 'success',
                'data' => $config,
            ]);

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@getMailConfiguration', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al obtener la configuración de correo',
            ], 500);
        }
    }

    /**
     * Update mail configuration (admin only)
     */
    public function updateMailConfiguration(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'host' => 'required|string|max:255',
                'port' => 'required|integer|min:1|max:65535',
                'username' => 'required|string|max:255',
                'password' => 'nullable|string|max:255',
                'encryption' => 'required|in:tls,ssl,none',
                'from_address' => 'required|email|max:255',
                'from_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de configuración inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $config = $request->only([
                'host', 'port', 'username', 'password',
                'encryption', 'from_address', 'from_name',
            ]);

            // Don't update password if it's the masked value
            if ($config['password'] === '****') {
                unset($config['password']);
            }

            $success = $this->mailService->updateMailConfiguration($config);

            if ($success) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Configuración de correo actualizada correctamente',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al actualizar la configuración de correo',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@updateMailConfiguration', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Send custom email to user (admin only)
     */
    public function sendCustomEmail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:5000',
                'email_type' => 'nullable|string|in:notification,announcement,warning',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Datos de email inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $userId = $request->input('user_id');
            $subject = $request->input('subject');
            $message = $request->input('message');
            $emailType = $request->input('email_type', 'notification');

            // Get user
            $user = User::find($userId);
            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado',
                ], 404);
            }

            // Get admin user for audit
            $admin = $request->user();

            Log::info('Admin sending custom email', [
                'admin_id' => $admin->id,
                'admin_email' => $admin->email,
                'target_user_id' => $userId,
                'target_user_email' => $user->email,
                'subject' => $subject,
                'email_type' => $emailType,
            ]);

            // Send email
            $emailSent = $this->mailService->sendNotificationEmail($user, $subject, $message, [
                'email_type' => $emailType,
                'sent_by_admin' => true,
                'admin_name' => $admin->name,
                'admin_email' => $admin->email,
            ]);

            if ($emailSent) {
                Log::info('Custom email sent successfully by admin', [
                    'admin_id' => $admin->id,
                    'user_id' => $userId,
                    'subject' => $subject,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Email enviado correctamente',
                    'data' => [
                        'recipient' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ],
                        'subject' => $subject,
                        'sent_at' => now()->toISOString(),
                    ],
                ]);
            } else {
                Log::error('Failed to send custom email by admin', [
                    'admin_id' => $admin->id,
                    'user_id' => $userId,
                    'subject' => $subject,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Error al enviar el email',
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Exception in EmailVerificationController@sendCustomEmail', [
                'admin_id' => $request->user()->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }
}
