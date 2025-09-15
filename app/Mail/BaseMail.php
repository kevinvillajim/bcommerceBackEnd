<?php

namespace App\Mail;

use App\Services\ConfigurationService;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

abstract class BaseMail extends Mailable
{
    use SerializesModels;

    protected ConfigurationService $configService;

    protected array $emailData;

    public function __construct(array $emailData = [])
    {
        try {
            $this->configService = app(ConfigurationService::class);
        } catch (\Exception $e) {
            // If ConfigurationService fails, create a new instance
            $this->configService = new ConfigurationService;
            \Log::warning('BaseMail: Created new ConfigurationService instance due to error: '.$e->getMessage());
        }

        $this->emailData = $emailData;

        // Configure SMTP from database - now more robust
        $this->configureSMTPFromDatabase();
    }

    /**
     * Configure SMTP settings from database
     */
    private function configureSMTPFromDatabase(): void
    {
        try {
            // First check if we should even try to load from database
            // In production, we might want to use .env values directly
            $useEnvOnly = env('MAIL_USE_ENV_ONLY', false);

            if ($useEnvOnly) {
                // Just use .env values, don't try to load from database
                return;
            }

            // Try to get from database, but have good fallbacks
            $host = env('MAIL_HOST', 'localhost');
            $port = env('MAIL_PORT', 587);
            $username = env('MAIL_USERNAME', '');
            $password = env('MAIL_PASSWORD', '');
            $encryption = env('MAIL_ENCRYPTION', 'tls');
            $from_address = env('MAIL_FROM_ADDRESS', 'noreply@example.com');
            $from_name = env('MAIL_FROM_NAME', env('APP_NAME', 'BCommerce'));

            // Only try database if ConfigurationService is available
            try {
                if ($this->configService) {
                    $host = $this->configService->getConfig('email.smtpHost', $host) ?: $host;
                    $port = $this->configService->getConfig('email.smtpPort', $port) ?: $port;
                    $username = $this->configService->getConfig('email.smtpUsername', $username) ?: $username;
                    $dbPassword = $this->configService->getConfig('email.smtpPassword');
                    if ($dbPassword) {
                        $password = $dbPassword;
                    }
                    $encryption = $this->configService->getConfig('email.smtpEncryption', $encryption) ?: $encryption;
                    $from_address = $this->configService->getConfig('email.senderEmail', $from_address) ?: $from_address;
                    $from_name = $this->configService->getConfig('email.senderName', $from_name) ?: $from_name;
                }
            } catch (\Exception $dbException) {
                \Log::info('Using .env mail configuration (database not available): '.$dbException->getMessage());
            }

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

            Config::set('mail.default', 'smtp');

            \Log::debug('Mail configuration set', [
                'host' => $host,
                'port' => $port,
                'from' => $from_address,
            ]);
        } catch (\Exception $e) {
            \Log::error('Critical error configuring SMTP: '.$e->getMessage());
            // Don't throw, let it use whatever is already configured
        }
    }

    /**
     * Get common email data
     */
    protected function getCommonData(): array
    {
        return array_merge([
            'appName' => $this->configService->getConfig('email.senderName', config('app.name', 'BCommerce')),
            'appUrl' => config('app.url', 'https://bcommerce.app'),
            'supportEmail' => $this->configService->getConfig('email.supportEmail', 'soporte@bcommerce.app'),
            'websiteUrl' => config('app.url', 'https://bcommerce.app'),
        ], $this->emailData);
    }

    /**
     * Get template name (to be implemented by child classes)
     */
    abstract protected function getTemplateName(): string;

    /**
     * Get email subject (to be implemented by child classes)
     */
    abstract protected function getSubject(): string;

    /**
     * Build the message
     */
    public function build()
    {
        $data = $this->getCommonData();

        return $this->view($this->getTemplateName())
            ->subject($this->getSubject())
            ->with($data);
    }
}
