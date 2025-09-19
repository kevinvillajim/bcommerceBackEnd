<?php

namespace App\Mail;

use App\Models\CreditNote;
use App\Models\User;

class CreditNoteMail extends BaseMail
{
    private User $user;
    private CreditNote $creditNote;
    private string $pdfPath;

    public function __construct(User $user, CreditNote $creditNote, string $pdfPath)
    {
        $this->user = $user;
        $this->creditNote = $creditNote;
        $this->pdfPath = $pdfPath;

        // Initialize parent first to set up configService
        parent::__construct();

        // Now add the email data
        $this->emailData = array_merge($this->emailData, [
            'user' => $user,
            'creditNote' => $creditNote,
            'customerName' => $creditNote->customer_name,
            'creditNoteNumber' => $creditNote->credit_note_number,
            'totalAmount' => $creditNote->total_amount,
            'motivo' => $creditNote->motivo,
            'documentoModificado' => $creditNote->documento_modificado_numero,
        ]);
    }

    protected function getTemplateName(): string
    {
        return 'emails.credit-notes.simple';
    }

    protected function getSubject(): string
    {
        $appName = $this->configService->getConfig('email.senderName', 'Comersia');

        return "Nota de Crédito #{$this->creditNote->credit_note_number} - {$appName}";
    }

    public function build()
    {
        $data = $this->getCommonData();

        return $this->view($this->getTemplateName())
            ->subject($this->getSubject())
            ->attach(storage_path("app/public/{$this->pdfPath}"), [
                'as' => "NotaCredito_{$this->creditNote->credit_note_number}.pdf",
                'mime' => 'application/pdf',
            ])
            ->with($data);
    }
}