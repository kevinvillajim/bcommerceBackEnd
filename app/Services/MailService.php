<?php

namespace App\Services;

use App\Models\User;
use App\Services\Mail\MailManager;

/**
 * Mail Service - Modern email service powered by MailManager
 * This service provides backwards compatibility while using the new template system internally
 */
class MailService
{
    private MailManager $mailManager;

    public function __construct(MailManager $mailManager)
    {
        $this->mailManager = $mailManager;
    }

    /**
     * Send verification email to user
     */
    public function sendVerificationEmail(User $user, string $token): bool
    {
        return $this->mailManager->sendVerificationEmail($user, $token);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(User $user, string $token): bool
    {
        return $this->mailManager->sendPasswordResetEmail($user, $token);
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(User $user): bool
    {
        return $this->mailManager->sendWelcomeEmail($user);
    }

    /**
     * Send general notification email
     */
    public function sendNotificationEmail(User $user, string $subject, string $message, array $additionalData = []): bool
    {
        $emailType = $additionalData['email_type'] ?? 'notification';

        // Map old additionalData format to new format
        $mappedData = [
            'sent_by_admin' => $additionalData['sent_by_admin'] ?? false,
            'admin_name' => $additionalData['admin_name'] ?? '',
            'admin_email' => $additionalData['admin_email'] ?? '',
            'priority' => $additionalData['priority'] ?? 'normal',
            'action_url' => $additionalData['action_url'] ?? null,
            'action_text' => $additionalData['action_text'] ?? null,
            'additional_info' => $additionalData['additional_info'] ?? [],
        ];

        return $this->mailManager->sendNotificationEmail($user, $subject, $message, $emailType, $mappedData);
    }

    /**
     * Test SMTP connection
     */
    public function testConnection(): array
    {
        return $this->mailManager->testConnection();
    }

    /**
     * Get current mail configuration
     */
    public function getMailConfiguration(): array
    {
        return $this->mailManager->getMailConfiguration();
    }

    /**
     * Update mail configuration in database
     */
    public function updateMailConfiguration(array $config): bool
    {
        return $this->mailManager->updateMailConfiguration($config);
    }

    /**
     * Delegate any other method calls to MailManager
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->mailManager, $method)) {
            return call_user_func_array([$this->mailManager, $method], $parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}
