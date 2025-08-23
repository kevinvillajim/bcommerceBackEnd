<?php

namespace App\UseCases\Chat;

use App\Domain\Interfaces\ChatFilterInterface;

class FilterMessageUseCase
{
    private ChatFilterInterface $chatFilter;

    private int $strikeLimit;

    public function __construct(
        ChatFilterInterface $chatFilter,
        int $strikeLimit = 3
    ) {
        $this->chatFilter = $chatFilter;
        $this->strikeLimit = $strikeLimit;
    }

    /**
     * Filtra un mensaje para asegurar que no contiene contenido prohibido
     *
     * @param  string  $content  Contenido del mensaje
     * @param  int|null  $userId  ID del usuario (para registrar strikes si es necesario)
     * @return array Resultado de la operación
     */
    public function execute(string $content, ?int $userId = null): array
    {
        // Verificar si el contenido contiene elementos prohibidos
        $containsProhibited = $this->chatFilter->containsProhibitedContent(
            $content,
            $userId,
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

        // Si el mensaje pasa el filtro
        return [
            'success' => true,
            'message' => 'Mensaje válido',
            'content' => $content,
        ];
    }
}
