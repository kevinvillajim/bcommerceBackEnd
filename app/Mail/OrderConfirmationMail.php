<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\User;

class OrderConfirmationMail extends BaseMail
{
    private User $user;

    private Order $order;

    public function __construct(User $user, Order $order)
    {
        $this->user = $user;
        $this->order = $order;

        // Initialize parent first to set up configService
        parent::__construct();

        // Now add the email data
        $this->emailData = array_merge($this->emailData, [
            'user' => $user,
            'order' => $order,
            'orderUrl' => $this->generateOrderUrl($order),
        ]);
    }

    protected function getTemplateName(): string
    {
        return 'emails.orders.confirmation';
    }

    protected function getSubject(): string
    {
        $appName = $this->configService->getConfig('email.senderName', config('app.name', 'BCommerce'));

        return "Confirmación de pedido #{$this->order->id} - {$appName}";
    }

    private function generateOrderUrl(Order $order): string
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        return "{$frontendUrl}/orders/{$order->id}";
    }
}
