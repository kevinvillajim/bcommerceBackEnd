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
        $this->configService = app(ConfigurationService::class);
        $this->emailData = $emailData;
        
        // Configure SMTP from database
        $this->configureSMTPFromDatabase();
    }

    /**
     * Configure SMTP settings from database
     */
    private function configureSMTPFromDatabase(): void
    {
        try {
            $host = $this->configService->getConfig('email.smtpHost', env('MAIL_HOST', 'localhost'));
            $port = $this->configService->getConfig('email.smtpPort', env('MAIL_PORT', 587));
            $username = $this->configService->getConfig('email.smtpUsername', env('MAIL_USERNAME', ''));
            $password = $this->configService->getConfig('email.smtpPassword', env('MAIL_PASSWORD', ''));
            $encryption = $this->configService->getConfig('email.smtpEncryption', env('MAIL_ENCRYPTION', 'tls'));
            $from_address = $this->configService->getConfig('email.senderEmail', env('MAIL_FROM_ADDRESS', 'noreply@example.com'));
            $from_name = $this->configService->getConfig('email.senderName', env('MAIL_FROM_NAME', env('APP_NAME', 'BCommerce')));

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
        } catch (\Exception $e) {
            \Log::warning('Failed to configure SMTP from database, using .env defaults: ' . $e->getMessage());
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