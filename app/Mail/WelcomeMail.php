<?php

namespace App\Mail;

use App\Models\User;

class WelcomeMail extends BaseMail
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
        
        // Initialize parent first to set up configService
        parent::__construct();
        
        // Now add the email data
        $this->emailData = array_merge($this->emailData, [
            'user' => $user,
        ]);
    }

    protected function getTemplateName(): string
    {
        return 'emails.welcome.new-user';
    }

    protected function getSubject(): string
    {
        $appName = $this->configService->getConfig('email.senderName', config('app.name', 'BCommerce'));
        return "¡Bienvenido a {$appName}!";
    }
}