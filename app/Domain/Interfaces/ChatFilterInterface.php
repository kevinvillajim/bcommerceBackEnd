<?php

// app/Domain/Interfaces/ChatFilterInterface.php

namespace App\Domain\Interfaces;

interface ChatFilterInterface
{
    /**
     * Verifica si un mensaje contiene contenido prohibido
     *
     * @param  string  $message  El mensaje a verificar
     * @param  int|null  $userId  ID del usuario (opcional para verificación sin registrar strikes)
     * @param  int|null  $nStrikes  Número máximo de strikes permitidos (opcional)
     * @return bool True si contiene contenido prohibido
     */
    public function containsProhibitedContent(string $message, ?int $userId = null, ?int $nStrikes = 3): bool;

    /**
     * Devuelve el motivo por el cual un mensaje ha sido rechazado
     */
    public function getRejectReason(string $message): ?string;

    /**
     * Intenta censurar contenido prohibido
     */
    public function censorProhibitedContent(string $message): string;
}
