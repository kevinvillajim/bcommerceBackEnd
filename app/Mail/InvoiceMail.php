<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\User;

class InvoiceMail extends BaseMail
{
    private User $user;

    private Invoice $invoice;

    private string $pdfPath;

    public function __construct(User $user, Invoice $invoice, string $pdfPath)
    {
        $this->user = $user;
        $this->invoice = $invoice;
        $this->pdfPath = $pdfPath;

        // Initialize parent first to set up configService
        parent::__construct();

        // Now add the email data
        $this->emailData = array_merge($this->emailData, [
            'user' => $user,
            'invoice' => $invoice,
            'customerName' => $invoice->customer_name,
            'invoiceNumber' => $invoice->invoice_number,
            'totalAmount' => $invoice->total_amount,
        ]);
    }

    protected function getTemplateName(): string
    {
        return 'emails.invoices.simple';
    }

    protected function getSubject(): string
    {
        $appName = $this->configService->getConfig('email.senderName', 'Comersia');

        return "Factura #{$this->invoice->invoice_number} - {$appName}";
    }

    public function build()
    {
        $data = $this->getCommonData();

        return $this->view($this->getTemplateName())
            ->subject($this->getSubject())
            ->attach(storage_path("app/public/{$this->pdfPath}"), [
                'as' => "Factura_{$this->invoice->invoice_number}.pdf",
                'mime' => 'application/pdf',
            ])
            ->with($data);
    }
}
