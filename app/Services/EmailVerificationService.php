<?php

namespace App\Services;

use App\Models\EmailVerificationToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmailVerificationService
{
    private ConfigurationService $configService;

    private MailService $mailService;

    public function __construct(ConfigurationService $configService, MailService $mailService)
    {
        $this->configService = $configService;
        $this->mailService = $mailService;
    }

    /**
     * Check if email verification is required
     */
    public function isVerificationRequired(): bool
    {
        $bypassEnabled = $this->configService->getConfig('email.bypassVerification', true);
        $verificationRequired = $this->configService->getConfig('email.requireVerification', false);

        // If bypass is enabled, verification is not required
        if ($bypassEnabled) {
            return false;
        }

        // Otherwise, check if verification is explicitly required
        return $verificationRequired;
    }

    /**
     * Generate and send verification email to user
     */
    public function sendVerificationEmail(User $user): array
    {
        try {
            // Email verification is now ALWAYS required for new registrations
            Log::info('Processing email verification for user', ['user_id' => $user->id]);

            // Get timeout from configuration
            $timeoutHours = $this->configService->getConfig('email.verificationTimeout', 24);

            // Generate verification token
            $verificationToken = EmailVerificationToken::createForUser($user->id, $timeoutHours);

            // Send verification email using our MailManager system
            $emailSent = $this->mailService->sendVerificationEmail($user, $verificationToken->token);

            if ($emailSent) {
                Log::info('Verification email sent successfully via MailManager', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'expires_at' => $verificationToken->expires_at,
                ]);

                return [
                    'status' => 'success',
                    'message' => 'Email de verificación enviado correctamente',
                    'expires_at' => $verificationToken->expires_at,
                ];
            } else {
                Log::error('Failed to send verification email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Error al enviar el email de verificación',
                ];
            }
        } catch (\Exception $e) {
            Log::error('Exception in sendVerificationEmail', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Error interno al procesar la verificación de email',
            ];
        }
    }

    /**
     * Verify email using token
     */
    public function verifyEmail(string $token): array
    {
        try {
            // Find valid token
            $verificationToken = EmailVerificationToken::findValidToken($token);

            if (! $verificationToken) {
                Log::warning('Invalid or expired verification token used', ['token' => substr($token, 0, 10).'...']);

                return [
                    'status' => 'error',
                    'message' => 'Token de verificación inválido o expirado',
                ];
            }

            // Get user
            $user = $verificationToken->user;

            if (! $user) {
                Log::error('User not found for verification token', ['token_id' => $verificationToken->id]);

                return [
                    'status' => 'error',
                    'message' => 'Usuario no encontrado',
                ];
            }

            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                Log::info('User email already verified', ['user_id' => $user->id]);

                // Clean up token
                $verificationToken->delete();

                return [
                    'status' => 'already_verified',
                    'message' => 'El email ya está verificado',
                ];
            }

            // Mark email as verified
            $user->markEmailAsVerified();

            // Clean up the token
            $verificationToken->delete();

            Log::info('Email verified successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return [
                'status' => 'success',
                'message' => 'Email verificado correctamente',
                'user' => $user,
            ];

        } catch (\Exception $e) {
            Log::error('Exception in verifyEmail', [
                'token' => substr($token, 0, 10).'...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Error interno al verificar el email',
            ];
        }
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(User $user): array
    {
        try {
            // Check if already verified
            if ($user->hasVerifiedEmail()) {
                return [
                    'status' => 'already_verified',
                    'message' => 'El email ya está verificado',
                ];
            }

            // Check if verification is required
            if (! $this->isVerificationRequired()) {
                // If verification is not required, just mark as verified
                $user->markEmailAsVerified();

                return [
                    'status' => 'bypassed',
                    'message' => 'Email marcado como verificado (verificación deshabilitada)',
                ];
            }

            // Check rate limiting (prevent spam)
            $existingToken = EmailVerificationToken::where('user_id', $user->id)
                ->where('created_at', '>', Carbon::now()->subMinutes(5))
                ->first();

            if ($existingToken) {
                return [
                    'status' => 'rate_limited',
                    'message' => 'Debe esperar 5 minutos antes de solicitar otro email de verificación',
                ];
            }

            // Send new verification email
            return $this->sendVerificationEmail($user);

        } catch (\Exception $e) {
            Log::error('Exception in resendVerificationEmail', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Error interno al reenviar el email de verificación',
            ];
        }
    }

    /**
     * Clean up expired tokens (should be called periodically)
     */
    public function cleanupExpiredTokens(): int
    {
        try {
            $deletedCount = EmailVerificationToken::cleanupExpiredTokens();

            if ($deletedCount > 0) {
                Log::info('Cleaned up expired verification tokens', ['count' => $deletedCount]);
            }

            return $deletedCount;
        } catch (\Exception $e) {
            Log::error('Exception in cleanupExpiredTokens', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get verification status for user
     */
    public function getVerificationStatus(User $user): array
    {
        $isRequired = $this->isVerificationRequired();
        $isVerified = $user->hasVerifiedEmail();

        // Get pending token info if exists
        $pendingToken = EmailVerificationToken::where('user_id', $user->id)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        return [
            'verification_required' => $isRequired,
            'email_verified' => $isVerified,
            'can_access_system' => ! $isRequired || $isVerified,
            'pending_verification' => $pendingToken ? [
                'expires_at' => $pendingToken->expires_at,
                'can_resend' => $pendingToken->created_at->addMinutes(5)->isPast(),
            ] : null,
        ];
    }
}
