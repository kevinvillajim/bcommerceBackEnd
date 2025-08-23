<?php

namespace App\UseCases\Chat;

use App\Domain\Entities\MessageEntity;
use App\Domain\Interfaces\ChatFilterInterface;
use App\Domain\Repositories\ChatRepositoryInterface;

class SendMessageUseCase
{
    private ChatRepositoryInterface $chatRepository;

    private ChatFilterInterface $chatFilter;

    private int $strikeLimit;

    public function __construct(
        ChatRepositoryInterface $chatRepository,
        ChatFilterInterface $chatFilter,
        int $strikeLimit = 3
    ) {
        $this->chatRepository = $chatRepository;
        $this->chatFilter = $chatFilter;
        $this->strikeLimit = $strikeLimit;
    }

    public function execute(int $chatId, int $senderId, string $content): array
    {
        // Verificar si el contenido contiene elementos prohibidos
        $containsProhibited = $this->chatFilter->containsProhibitedContent(
            $content,
            $senderId,
            $this->strikeLimit
        );

        if ($containsProhibited) {
            $reason = $this->chatFilter->getRejectReason($content);
            $censoredContent = $this->chatFilter->censorProhibitedContent($content);

            return [
                'success' => false,
                'message' => "Mensaje rechazado: {$reason}",
                'censored_content' => $censoredContent,
            ];
        }

        // Si pasa el filtro, enviar el mensaje
        $message = new MessageEntity(
            $chatId,
            $senderId,
            $content
        );

        $savedMessage = $this->chatRepository->addMessage($message);

        return [
            'success' => true,
            'message' => $savedMessage,
        ];
    }
}
