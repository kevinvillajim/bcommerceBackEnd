<?php

namespace App\Mail;

use App\Models\User;

class PasswordResetMail extends BaseMail
{
    private User $user;
    private string $token;

    public function __construct(User $user, string $token)
    {
        $this->user = $user;
        $this->token = $token;
        
        // Initialize parent first to set up configService
        parent::__construct();
        
        // Now add the email data
        $this->emailData = array_merge($this->emailData, [
            'user' => $user,
            'resetUrl' => $this->generateResetUrl($token),
        ]);
    }

    protected function getTemplateName(): string
    {
        return 'emails.password.reset';
    }

    protected function getSubject(): string
    {
        $appName = $this->configService->getConfig('email.senderName', config('app.name', 'BCommerce'));
        return "Restablecer contraseña - {$appName}";
    }

    private function generateResetUrl(string $token): string
    {
        $frontendUrl = config('app.frontend_url', 'https://comersia.app');
        return "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($this->user->email);
    }
}