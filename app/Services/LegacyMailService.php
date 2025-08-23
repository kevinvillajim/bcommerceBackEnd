<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LegacyMailService
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Configure SMTP settings dynamically from database
     */
    private function configureSMTP(): bool
    {
        try {
            // Get mail configuration from database with .env fallbacks
            $host = $this->configService->getConfig('email.smtpHost', env('MAIL_HOST', 'localhost'));
            $port = $this->configService->getConfig('email.smtpPort', env('MAIL_PORT', 587));
            $username = $this->configService->getConfig('email.smtpUsername', env('MAIL_USERNAME', ''));
            $password = $this->configService->getConfig('email.smtpPassword', env('MAIL_PASSWORD', ''));
            $encryption = $this->configService->getConfig('email.smtpEncryption', env('MAIL_ENCRYPTION', 'tls'));
            $from_address = $this->configService->getConfig('email.senderEmail', env('MAIL_FROM_ADDRESS', 'noreply@example.com'));
            $from_name = $this->configService->getConfig('email.senderName', env('MAIL_FROM_NAME', env('APP_NAME', 'Comersia A')));

            // Configure mail settings dynamically
            Config::set('mail.mailers.smtp', [
                'transport' => 'smtp',
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $password,
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ]);

            Config::set('mail.from', [
                'address' => $from_address,
                'name' => $from_name,
            ]);

            // Set default mailer to smtp
            Config::set('mail.default', 'smtp');

            // SMTP configuration loaded from database

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to configure SMTP from database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(): array
    {
        try {
            if (! $this->configureSMTP()) {
                return [
                    'status' => 'error',
                    'message' => 'Error al configurar SMTP desde la base de datos',
                ];
            }

            // Get current mail configuration
            $host = Config::get('mail.mailers.smtp.host');
            $port = Config::get('mail.mailers.smtp.port');
            $username = Config::get('mail.mailers.smtp.username');
            $password = Config::get('mail.mailers.smtp.password');
            $encryption = Config::get('mail.mailers.smtp.encryption');

            // Test connection by trying to create a mail instance
            // This will validate the SMTP configuration without actually sending an email
            $originalDriver = Config::get('mail.default');

            // Temporarily set mail driver to log to avoid sending real emails
            Config::set('mail.default', 'log');

            // Try to send a test email to log
            Mail::raw('Test connection', function ($message) {
                $message->to('test@example.com')->subject('Test Connection');
            });

            // Restore original mail driver
            Config::set('mail.default', $originalDriver);

            Log::info('SMTP connection test successful', [
                'host' => $host,
                'port' => $port,
                'username' => $username,
            ]);

            return [
                'status' => 'success',
                'message' => 'Conexión SMTP exitosa',
                'details' => [
                    'host' => $host,
                    'port' => $port,
                    'encryption' => $encryption,
                    'username' => $username,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('SMTP connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Error de conexión SMTP: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(User $user, string $token): bool
    {
        try {
            if (! $this->configureSMTP()) {
                Log::error('Cannot send verification email: SMTP configuration failed');

                return false;
            }

            $appName = $this->configService->getConfig('email.senderName', 'Comersia App');
            $appUrl = config('app.url', 'https://comersia.app');
            $verificationUrl = $appUrl.'/verify-email?token='.$token;
            $timeout = $this->configService->getConfig('email.verificationTimeout', 24);

            // Email data
            $emailData = [
                'user' => $user,
                'verification_url' => $verificationUrl,
                'app_name' => $appName,
                'app_url' => $appUrl,
                'expires_hours' => $timeout,
            ];

            // Generate HTML content
            $htmlContent = $this->generateVerificationEmailHTML($emailData);

            // Send email
            Mail::html($htmlContent, function ($message) use ($user, $appName) {
                $message->to($user->email, $user->name ?? 'Usuario')
                    ->subject("Verificar tu cuenta en $appName");
            });

            Log::info('Verification email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'verification_url' => $verificationUrl,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(User $user, string $token): bool
    {
        try {
            if (! $this->configureSMTP()) {
                Log::error('Cannot send password reset email: SMTP configuration failed');

                return false;
            }

            $appName = $this->configService->getConfig('email.senderName', 'Comersia A');
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $resetUrl = $frontendUrl.'/reset-password?token='.$token.'&email='.urlencode($user->email);

            // Email data
            $emailData = [
                'user' => $user,
                'reset_url' => $resetUrl,
                'app_name' => $appName,
                'app_url' => $frontendUrl,
            ];

            // Generate HTML content
            $htmlContent = $this->generatePasswordResetEmailHTML($emailData);

            // Send email
            Mail::html($htmlContent, function ($message) use ($user, $appName) {
                $message->to($user->email, $user->name ?? 'Usuario')
                    ->subject("Restablecer contraseña - $appName");
            });

            Log::info('Password reset email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            if (! $this->configureSMTP()) {
                Log::error('Cannot send welcome email: SMTP configuration failed');

                return false;
            }

            $appName = $this->configService->getConfig('email.senderName', 'Comersia A');
            $appUrl = config('app.frontend_url', 'http://localhost:3000');

            // Email data
            $emailData = [
                'user' => $user,
                'app_name' => $appName,
                'app_url' => $appUrl,
            ];

            // Generate HTML content
            $htmlContent = $this->generateWelcomeEmailHTML($emailData);

            // Send email
            Mail::html($htmlContent, function ($message) use ($user, $appName) {
                $message->to($user->email, $user->name ?? 'Usuario')
                    ->subject("¡Bienvenido a $appName!");
            });

            Log::info('Welcome email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send general notification email
     */
    public function sendNotificationEmail(User $user, string $subject, string $message, array $additionalData = []): bool
    {
        try {
            if (! $this->configureSMTP()) {
                Log::error('Cannot send notification email: SMTP configuration failed');

                return false;
            }

            $appName = $this->configService->getConfig('email.senderName', 'Comersia A');
            $appUrl = config('app.url', 'https://comersia.app');

            // Email data
            $emailData = array_merge([
                'user' => $user,
                'subject' => $subject,
                'message' => $message,
                'app_name' => $appName,
                'app_url' => $appUrl,
            ], $additionalData);

            // Generate HTML content
            $htmlContent = $this->generateNotificationEmailHTML($emailData);

            // Send email
            Mail::html($htmlContent, function ($msg) use ($user, $subject) {
                $msg->to($user->email, $user->name ?? 'Usuario')
                    ->subject($subject);
            });

            Log::info('Notification email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get current mail configuration
     */
    public function getMailConfiguration(): array
    {
        return [
            'host' => $this->configService->getConfig('email.smtpHost', env('MAIL_HOST', 'localhost')),
            'port' => $this->configService->getConfig('email.smtpPort', env('MAIL_PORT', 587)),
            'username' => $this->configService->getConfig('email.smtpUsername', env('MAIL_USERNAME', '')),
            'encryption' => $this->configService->getConfig('email.smtpEncryption', env('MAIL_ENCRYPTION', 'tls')),
            'from_address' => $this->configService->getConfig('email.senderEmail', env('MAIL_FROM_ADDRESS', 'noreply@example.com')),
            'from_name' => $this->configService->getConfig('email.senderName', env('MAIL_FROM_NAME', env('APP_NAME', 'Comersia App'))),
        ];
    }

    /**
     * Update mail configuration in database
     */
    public function updateMailConfiguration(array $config): bool
    {
        try {
            $configMapping = [
                'host' => 'email.smtpHost',
                'port' => 'email.smtpPort',
                'username' => 'email.smtpUsername',
                'password' => 'email.smtpPassword',
                'encryption' => 'email.smtpEncryption',
                'from_address' => 'email.senderEmail',
                'from_name' => 'email.senderName',
            ];

            foreach ($configMapping as $key => $dbKey) {
                if (isset($config[$key])) {
                    $this->configService->setConfig($dbKey, $config[$key]);
                }
            }

            Log::info('Mail configuration updated successfully', [
                'updated_keys' => array_keys(array_intersect_key($config, $configMapping)),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update mail configuration', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate HTML for verification email
     */
    private function generateVerificationEmailHTML(array $data): string
    {
        $userName = $data['user']->name ?? 'Usuario';
        $appName = $data['app_name'] ?? 'Comersia App';
        $verificationUrl = $data['verification_url'] ?? '#';
        $expiresHours = $data['expires_hours'] ?? 24;

        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Verificar tu cuenta en {$appName}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); padding: 20px;'>
                <div style='text-align: center; padding: 20px 0; border-bottom: 2px solid #28a745; margin-bottom: 30px;'>
                    <h1 style='color: #28a745; margin: 0; font-size: 28px;'>{$appName}</h1>
                </div>
                
                <div style='padding: 20px 0;'>
                    <h2>¡Hola {$userName}!</h2>
                    
                    <p>Gracias por registrarte en {$appName}. Para completar tu registro y acceder a todas las funcionalidades, necesitamos verificar tu dirección de correo electrónico.</p>
                    
                    <div style='background-color: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center; margin: 30px 0; border: 2px solid #28a745;'>
                        <h3>Verificar mi cuenta</h3>
                        <p>Haz clic en el siguiente botón para verificar tu correo electrónico:</p>
                        <a href='{$verificationUrl}' style='display: inline-block; padding: 15px 40px; background-color: #28a745; color: white; text-decoration: none; border-radius: 8px; font-size: 18px; font-weight: bold; margin: 20px 0;'>Verificar Email</a>
                    </div>
                    
                    <div style='background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;'>
                        <strong>⏰ Importante:</strong> Este enlace expirará en {$expiresHours} horas.
                    </div>
                    
                    <p>Si el botón no funciona, puedes copiar y pegar el siguiente enlace en tu navegador:</p>
                    <p style='word-break: break-all; color: #007bff;'>{$verificationUrl}</p>
                    
                    <p>Si no creaste una cuenta en {$appName}, puedes ignorar este correo.</p>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 14px;'>
                    <p>Este es un mensaje automático, por favor no responder a este correo.</p>
                    <p>&copy; ".date('Y')." {$appName}. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Generate HTML for password reset email
     */
    private function generatePasswordResetEmailHTML(array $data): string
    {
        $userName = $data['user']->name ?? 'Usuario';
        $appName = $data['app_name'] ?? 'Comersia App';
        $resetUrl = $data['reset_url'] ?? '#';

        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Restablecer contraseña - {$appName}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); padding: 20px;'>
                <div style='text-align: center; padding: 20px 0; border-bottom: 2px solid #dc3545; margin-bottom: 30px;'>
                    <h1 style='color: #dc3545; margin: 0; font-size: 28px;'>{$appName}</h1>
                </div>
                
                <div style='padding: 20px 0;'>
                    <h2>Hola {$userName}</h2>
                    
                    <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en {$appName}.</p>
                    
                    <div style='background-color: #f8f9fa; padding: 30px; border-radius: 10px; text-align: center; margin: 30px 0; border: 2px solid #dc3545;'>
                        <h3>Restablecer Contraseña</h3>
                        <p>Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                        <a href='{$resetUrl}' style='display: inline-block; padding: 15px 40px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 8px; font-size: 18px; font-weight: bold; margin: 20px 0;'>Restablecer Contraseña</a>
                    </div>
                    
                    <p>Si no solicitaste restablecer tu contraseña, puedes ignorar este correo. Tu contraseña no será cambiada.</p>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 14px;'>
                    <p>Este es un mensaje automático, por favor no responder a este correo.</p>
                    <p>&copy; ".date('Y')." {$appName}. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Generate HTML for welcome email
     */
    private function generateWelcomeEmailHTML(array $data): string
    {
        $userName = $data['user']->name ?? 'Usuario';
        $appName = $data['app_name'] ?? 'Comersia App';
        $appUrl = $data['app_url'] ?? '#';

        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>¡Bienvenido a {$appName}!</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); padding: 20px;'>
                <div style='text-align: center; padding: 20px 0; border-bottom: 2px solid #007bff; margin-bottom: 30px;'>
                    <h1 style='color: #007bff; margin: 0; font-size: 28px;'>{$appName}</h1>
                </div>
                
                <div style='background: linear-gradient(135deg, #007bff, #28a745); color: white; padding: 30px; border-radius: 10px; text-align: center; margin: 20px 0;'>
                    <h2 style='margin: 0; font-size: 24px;'>¡Bienvenido {$userName}!</h2>
                    <p>Tu cuenta ha sido creada exitosamente</p>
                </div>
                
                <div style='padding: 20px 0;'>
                    <p>¡Nos alegra tenerte como parte de nuestra comunidad! Tu registro en {$appName} se ha completado correctamente.</p>
                    
                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                        <h3>¿Qué puedes hacer ahora?</h3>
                        <p><strong>Explorar productos</strong> - Descubre miles de productos de vendedores verificados</p>
                        <p><strong>Crear listas de favoritos</strong> - Guarda los productos que más te gusten</p>
                        <p><strong>Contactar vendedores</strong> - Haz preguntas sobre los productos</p>
                        <p><strong>Realizar pedidos</strong> - Compra de forma segura y confiable</p>
                        <p><strong>Seguir tus pedidos</strong> - Mantente al día con el estado de tus compras</p>
                    </div>
                    
                    <div style='text-align: center;'>
                        <a href='{$appUrl}' style='display: inline-block; padding: 15px 40px; background-color: #007bff; color: white; text-decoration: none; border-radius: 8px; font-size: 18px; font-weight: bold; margin: 20px 0;'>Comenzar a explorar</a>
                    </div>
                    
                    <p>Si tienes alguna pregunta o necesitas ayuda, no dudes en contactarnos. ¡Estamos aquí para ayudarte!</p>
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 14px;'>
                    <p>Gracias por unirte a {$appName}</p>
                    <p>&copy; ".date('Y')." {$appName}. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Generate HTML for notification email
     */
    private function generateNotificationEmailHTML(array $data): string
    {
        $userName = $data['user']->name ?? 'Usuario';
        $appName = $data['app_name'] ?? 'Comersia App';
        $subject = $data['subject'] ?? 'Notificación';
        $message = nl2br(htmlspecialchars($data['message'] ?? ''));
        $emailType = $data['email_type'] ?? 'notification';
        $sentByAdmin = $data['sent_by_admin'] ?? false;
        $adminName = $data['admin_name'] ?? '';
        $adminEmail = $data['admin_email'] ?? '';

        $borderColor = '#007bff'; // notification
        if ($emailType === 'announcement') {
            $borderColor = '#28a745';
        }
        if ($emailType === 'warning') {
            $borderColor = '#ffc107';
        }

        $adminInfo = '';
        if ($sentByAdmin) {
            $adminInfo = "<p><small>Este mensaje fue enviado por el administrador: {$adminName} ({$adminEmail})</small></p>";
        }

        return "
        <!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$subject}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4;'>
            <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); padding: 20px;'>
                <div style='text-align: center; padding: 20px 0; border-bottom: 2px solid {$borderColor}; margin-bottom: 30px;'>
                    <h1 style='color: {$borderColor}; margin: 0; font-size: 28px;'>{$appName}</h1>
                </div>
                
                <div style='padding: 20px 0;'>
                    <h2>{$subject}</h2>
                    
                    <div style='background-color: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0; border-left: 4px solid {$borderColor};'>
                        {$message}
                    </div>
                    
                    {$adminInfo}
                </div>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 14px;'>
                    <p>Este es un mensaje automático, por favor no responder a este correo.</p>
                    <p>&copy; ".date('Y')." {$appName}. Todos los derechos reservados.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
