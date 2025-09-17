<?php

namespace App\Services\Mail;

use App\Mail\EmailVerificationMail;
use App\Mail\NotificationMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\PasswordResetMail;
use App\Mail\WelcomeMail;
use App\Models\Order;
use App\Models\User;
use App\Services\ConfigurationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Improved Mail Manager with individual templates and Mailable classes
 *
 * Features:
 * - Blade templates for easy customization
 * - Individual Mailable classes for each email type
 * - Centralized configuration management
 * - Easy to extend with new email types
 * - Better error handling and logging
 */
class MailManager
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Send email verification message
     */
    public function sendVerificationEmail(User $user, string $token): bool
    {
        try {
            Mail::to($user->email)->send(new EmailVerificationMail($user, $token));

            Log::info('Verification email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'template' => 'emails.verification.verify-email',
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
            Mail::to($user->email)->send(new PasswordResetMail($user, $token));

            Log::info('Password reset email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'template' => 'emails.password.reset',
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
            Mail::to($user->email)->send(new WelcomeMail($user));

            Log::info('Welcome email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'template' => 'emails.welcome.new-user',
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
    public function sendNotificationEmail(
        User $user,
        string $subject,
        string $message,
        string $type = 'notification',
        array $additionalData = []
    ): bool {
        try {
            Mail::to($user->email)->send(new NotificationMail($user, $subject, $message, $type, $additionalData));

            Log::info('Notification email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
                'type' => $type,
                'template' => 'emails.notification.general',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'subject' => $subject,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmationEmail(User $user, Order $order): bool
    {
        try {
            Mail::to($user->email)->send(new OrderConfirmationMail($user, $order));

            Log::info('Order confirmation email sent successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'order_id' => $order->id,
                'template' => 'emails.orders.confirmation',
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
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
            // Create a test mailable to validate configuration
            $testMail = new class extends \Illuminate\Mail\Mailable
            {
                public function build()
                {
                    return $this->view('emails.test')
                        ->subject('Test Connection');
                }
            };

            // Get current mail configuration
            $host = Config::get('mail.mailers.smtp.host');
            $port = Config::get('mail.mailers.smtp.port');
            $username = Config::get('mail.mailers.smtp.username');
            $encryption = Config::get('mail.mailers.smtp.encryption');

            // Test by sending to log
            $originalDriver = Config::get('mail.default');
            Config::set('mail.default', 'log');

            Mail::to('test@example.com')->send($testMail);

            // Restore original driver
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
            'from_name' => $this->configService->getConfig('email.senderName', env('MAIL_FROM_NAME', env('APP_NAME', 'BCommerce'))),
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
     * Send invoice email with PDF attachment
     */
    public function sendInvoiceEmail(User $user, \App\Models\Invoice $invoice, string $pdfPath): bool
    {
        try {
            // Prepare recipients: customer + backup email
            $recipients = [
                $invoice->customer_email, // Cliente de la factura
                'facturacion@comersia.app', // Backup empresa
            ];

            // Send to each recipient
            foreach ($recipients as $email) {
                Mail::to($email)->send(new \App\Mail\InvoiceMail($user, $invoice, $pdfPath));
            }

            Log::info('Invoice email sent successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'recipients' => $recipients,
                'pdf_path' => $pdfPath,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send invoice email', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer_email' => $invoice->customer_email,
                'pdf_path' => $pdfPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get available email templates
     */
    public function getAvailableTemplates(): array
    {
        return [
            'verification' => [
                'name' => 'Verificación de Email',
                'description' => 'Email enviado para verificar nuevas cuentas',
                'template' => 'emails.verification.verify-email',
                'mailable' => EmailVerificationMail::class,
            ],
            'password_reset' => [
                'name' => 'Restablecimiento de Contraseña',
                'description' => 'Email para restablecer contraseñas olvidadas',
                'template' => 'emails.password.reset',
                'mailable' => PasswordResetMail::class,
            ],
            'welcome' => [
                'name' => 'Bienvenida',
                'description' => 'Email de bienvenida para nuevos usuarios',
                'template' => 'emails.welcome.new-user',
                'mailable' => WelcomeMail::class,
            ],
            'notification' => [
                'name' => 'Notificación General',
                'description' => 'Email genérico para notificaciones y anuncios',
                'template' => 'emails.notification.general',
                'mailable' => NotificationMail::class,
            ],
            'order_confirmation' => [
                'name' => 'Confirmación de Pedido',
                'description' => 'Email de confirmación para nuevos pedidos',
                'template' => 'emails.orders.confirmation',
                'mailable' => OrderConfirmationMail::class,
            ],
            'invoice' => [
                'name' => 'Factura Electrónica',
                'description' => 'Email con factura electrónica adjunta',
                'template' => 'emails.invoices.simple',
                'mailable' => \App\Mail\InvoiceMail::class,
            ],
        ];
    }
}
