<?php

namespace App\Mail;

use App\Models\User;

class EmailVerificationMail extends BaseMail
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
            'verificationUrl' => $this->generateVerificationUrl($token),
            'expiresHours' => $this->configService->getConfig('email.verificationTimeout', 24),
        ]);
    }

    protected function getTemplateName(): string
    {
        return 'emails.verification.verify-email';
    }

    protected function getSubject(): string
    {
        $appName = $this->configService->getConfig('email.senderName', config('app.name', 'BCommerce'));
        return "Verificar tu cuenta en {$appName}";
    }

    private function generateVerificationUrl(string $token): string
    {
        $backendUrl = config('app.url', 'http://localhost:8000');
        return "{$backendUrl}/api/email-verification/verify?token={$token}";
    }
}