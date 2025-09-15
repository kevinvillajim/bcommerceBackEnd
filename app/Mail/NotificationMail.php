<?php

namespace App\Mail;

use App\Models\User;

class NotificationMail extends BaseMail
{
    private User $user;

    private string $subject;

    private string $message;

    private string $emailType;

    private array $additionalData;

    public function __construct(
        User $user,
        string $subject,
        string $message,
        string $emailType = 'notification',
        array $additionalData = []
    ) {
        $this->user = $user;
        $this->subject = $subject;
        $this->message = $message;
        $this->emailType = $emailType;
        $this->additionalData = $additionalData;

        // Initialize parent first to set up configService
        parent::__construct();

        // Now add the email data
        $this->emailData = array_merge($this->emailData, array_merge([
            'user' => $user,
            'subject' => $subject,
            'message' => $message,
            'emailType' => $emailType,
            'sentByAdmin' => $additionalData['sent_by_admin'] ?? false,
            'adminName' => $additionalData['admin_name'] ?? '',
            'adminEmail' => $additionalData['admin_email'] ?? '',
            'priority' => $additionalData['priority'] ?? 'normal',
            'actionUrl' => $additionalData['action_url'] ?? null,
            'actionText' => $additionalData['action_text'] ?? null,
            'additionalInfo' => $additionalData['additional_info'] ?? [],
        ], $additionalData));
    }

    protected function getTemplateName(): string
    {
        return 'emails.notification.general';
    }

    protected function getSubject(): string
    {
        return $this->subject;
    }
}
