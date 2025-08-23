<?php

namespace App\Infrastructure\Services;

use App\Domain\Interfaces\ChatFilterInterface;
use App\Events\SellerAccountBlocked;
use App\Events\SellerStrikeAdded;
use App\Models\Seller;
use App\Models\User;
use App\Models\UserStrike;
use App\Services\ConfigurationService;

class ChatFilterService implements ChatFilterInterface
{
    private ConfigurationService $configService;

    public function __construct(ConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Patrones para detectar emojis numéricos específicos (0⃣-9⃣)
     */
    private $numberEmojiPatterns = [
        '/[0-9]\x{FE0F}?\x{20E3}/u', // Números con keycap emoji (0⃣-9⃣)
        '/\x{1F51F}/u', // 🔟 (ten emoji)
    ];

    /**
     * Palabras clave que indican contexto de CONTACTO (números prohibidos)
     */
    private $contactContextWords = [
        // Contacto directo (PESO ALTO)
        'número', 'numero', 'teléfono', 'telefono', 'celular', 'móvil', 'movil',
        'contacto', 'contactar', 'contactame', 'contáctame', 'llama', 'llamar', 'llamame', 'llámame',
        'whatsapp', 'whats', 'wsp', 'telegram', 'sms', 'mensaje', 'mensajea', 'mensajear',

        // Ubicación y encuentros (PESO ALTO)
        'dirección', 'direccion', 'ubicación', 'ubicacion', 'sector', 'calle', 'avenida', 'av',
        'casa', 'domicilio', 'residencia', 'barrio', 'ciudadela', 'conjunto',
        'encuentro', 'encuentre', 'reunir', 'reunamos', 'reunirse', 'vernos', 'verse',
        'quedar', 'quedamos', 'cita', 'coordinar', 'coordine', 'coordinemos',

        // Métodos de pago externos (PESO MEDIO)
        'transferencia', 'deposito', 'depósito', 'banco', 'cuenta', 'efectivo',
        'paypal', 'western', 'moneygram', 'giro',

        // Comunicación externa (PESO ALTO)
        'email', 'correo', 'gmail', 'yahoo', 'hotmail', 'facebook', 'instagram',
        'linkedin', 'twitter', 'tiktok', 'zoom', 'skype', 'teams', 'meet',

        // Evasión de plataforma (PESO ALTO)
        'aparte', 'fuera', 'afuera', 'externo', 'externamente', 'directo', 'directamente',
        'privado', 'privada', 'particular', 'personal', 'independiente',
    ];

    /**
     * Palabras clave que indican contexto de NEGOCIO (números permitidos)
     */
    private $businessContextWords = [
        // Cantidades y stock (PESO ALTO)
        'tengo', 'tiene', 'hay', 'disponible', 'stock', 'cantidad', 'unidades', 'piezas',
        'artículos', 'articulos', 'productos', 'items', 'ejemplares', 'copias', 'vendo',
        'vendiendo', 'ofrezco', 'incluye', 'contiene', 'trae', 'viene', 'pack',

        // Precios y dinero (PESO ALTO)
        'cuesta', 'vale', 'precio', 'valor', 'coste', 'costo', 'pagar', 'pago',
        'dólares', 'dolares', 'usd', 'centavos', 'euros', 'soles', 'pesos',
        'descuento', 'oferta', 'promoción', 'promocion', 'rebaja', 'barato',
        'caro', 'economic', 'economico', 'ganga', 'oportunidad',

        // Tiempo y fechas (PESO ALTO)
        'días', 'dias', 'horas', 'minutos', 'semanas', 'meses', 'año', 'años',
        'demora', 'demoro', 'tarda', 'tarde', 'entrega', 'envío', 'envio',
        'tiempo', 'plazo', 'fecha', 'cuando', 'listo', 'disponible', 'inmediato',
        'rápido', 'rapido', 'pronto', 'mañana', 'hoy', 'ayer',

        // Medidas y especificaciones (PESO ALTO)
        'tamaño', 'talla', 'medida', 'largo', 'ancho', 'alto', 'peso', 'gramos',
        'kilos', 'metros', 'centímetros', 'pulgadas', 'litros', 'mililitros',
        'voltios', 'watts', 'rpm', 'velocidad', 'capacidad', 'memoria', 'gb', 'mb',
        'pulgadas', 'inches', 'cm', 'mm', 'kg', 'gr',

        // Porcentajes y estadísticas (PESO MEDIO)
        'porciento', 'porcentaje', '%', 'por ciento', 'de', 'sobre', 'total',
        'promedio', 'máximo', 'maximo', 'mínimo', 'minimo', 'aproximadamente',
        'cerca', 'alrededor', 'entre', 'desde', 'hasta',

        // Garantía y servicio (PESO MEDIO)
        'garantía', 'garantia', 'servicio', 'reparación', 'nuevo', 'usado',
        'mantenimiento', 'revisión', 'revision', 'cambio', 'repuesto', 'original',

        // Condición y estado (PESO MEDIO)
        'estado', 'condicion', 'condición', 'perfecto', 'excelente', 'bueno',
        'regular', 'funciona', 'funcionando', 'trabajando', 'operativo',

        // Compra/venta legítima (PESO ALTO)
        'comprar', 'compra', 'venta', 'vender', 'interesado', 'interesada',
        'necesito', 'busco', 'quiero', 'deseo', 'acepto', 'negociable',
    ];

    /**
     * Palabras de números escritos que podrían usarse para evadir filtros
     */
    private $writtenNumbers = [
        'cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete',
        'dieciocho', 'diecinueve', 'veinte', 'treinta', 'cuarenta', 'cincuenta',
        'sesenta', 'setenta', 'ochenta', 'noventa', 'cien', 'mil',
    ];

    /**
     * Verifica si el mensaje contiene contenido prohibido y registra strikes si es necesario
     */
    public function containsProhibitedContent(string $message, ?int $userId = null, ?int $nStrikes = null): bool
    {
        $message = $this->normalizeMessage($message);

        // Get strikes threshold from database configuration
        if ($nStrikes === null) {
            $nStrikes = $this->configService->getConfig('moderation.userStrikesThreshold', 3);
        }

        // Verificar emojis numéricos primero (siempre prohibidos)
        if ($this->hasProhibitedNumberEmojis($message)) {
            if ($userId !== null) {
                $this->registerStrike($userId, $nStrikes, 'Uso de emojis numéricos prohibidos');
            }

            return true;
        }

        // Verificar solicitudes de contacto sin números (NUEVO)
        if ($this->hasContactRequestPatterns($message)) {
            if ($userId !== null) {
                $this->registerStrike($userId, $nStrikes, 'Solicitud de información de contacto');
            }

            return true;
        }

        // Verificar números escritos en contexto de contacto
        if ($this->hasWrittenNumbersInContactContext($message)) {
            if ($userId !== null) {
                $this->registerStrike($userId, $nStrikes, 'Números escritos en contexto de contacto');
            }

            return true;
        }

        // Verificar patrones de teléfono
        if ($this->hasPhoneNumberInContactContext($message)) {
            if ($userId !== null) {
                $this->registerStrike($userId, $nStrikes, 'Número de teléfono detectado');
            }

            return true;
        }

        return false;
    }

    /**
     * Normaliza el mensaje para mejor análisis
     */
    private function normalizeMessage(string $message): string
    {
        // Convertir a minúsculas para análisis de contexto
        $normalized = mb_strtolower($message, 'UTF-8');

        // Remover acentos para mejor detección de palabras
        $normalized = $this->removeAccents($normalized);

        return $normalized;
    }

    /**
     * Remueve acentos para mejor análisis de texto
     */
    private function removeAccents(string $text): string
    {
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'ç' => 'c',
        ];

        return strtr($text, $accents);
    }

    /**
     * Verifica si hay emojis numéricos prohibidos
     */
    private function hasProhibitedNumberEmojis(string $message): bool
    {
        foreach ($this->numberEmojiPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si hay solicitudes de información de contacto (sin números)
     */
    private function hasContactRequestPatterns(string $message): bool
    {
        // Patrones específicos de solicitud de contacto
        $contactRequestPatterns = [
            // Solicitudes directas de redes sociales
            '/(?:mandame|pasame|dame|enviame|comparteme)\s+(?:tu\s+)?(?:facebook|whatsapp|whats|instagram|telegram|email|correo|gmail)/',
            '/(?:mi\s+)?(?:facebook|whatsapp|whats|instagram|telegram)\s+es\s/',
            '/(?:agregame|añademe|busqueme|contactame)\s+(?:en\s+)?(?:facebook|whatsapp|whats|instagram|telegram)/',

            // Solicitudes de encuentro/ubicación
            '/(?:me\s+encuentro|estoy|vivo|trabajo)\s+en\s+(?:la\s+)?(?:av|avenida|calle|sector|barrio)/',
            '/(?:nos\s+vemos|encontramos|encontremonos|quedar|vernos)\s+en\s+/',
            '/(?:mi\s+)?(?:direccion|ubicacion)\s+es\s+/',
            '/(?:vivo|resido|trabajo)\s+(?:en\s+)?(?:el\s+)?(?:sector|barrio|zona)\s+/',

            // Evasiones de plataforma
            '/(?:conversemos|hablemos|escribeme|contactame)\s+(?:por\s+)?(?:fuera|aparte|afuera|externo)/',
            '/(?:comunicate|escribeme|contactame)\s+(?:por\s+)?(?:privado|directo)/',
            '/(?:salir|comunicarnos|hablar)\s+(?:de\s+)?(?:aqui|la\s+plataforma)/',

            // Intercambio directo de datos de contacto
            '/(?:te\s+paso|aqui\s+mi|este\s+es\s+mi)\s+(?:facebook|whatsapp|email|correo)/',
        ];

        foreach ($contactRequestPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        // Verificar combinaciones sospechosas de palabras clave
        $suspiciousCombos = [
            ['mandame', 'facebook'], ['mandame', 'whatsapp'], ['mandame', 'instagram'],
            ['pasame', 'facebook'], ['pasame', 'whatsapp'], ['pasame', 'correo'],
            ['conversemos', 'whatsapp'], ['conversemos', 'facebook'], ['conversemos', 'telegram'],
            ['escribeme', 'privado'], ['contactame', 'directo'], ['hablemos', 'fuera'],
            ['encuentro', 'avenida'], ['encuentro', 'sector'], ['ubicacion', 'direccion'],
            ['vemos', 'calle'], ['quedar', 'sector'],
        ];

        foreach ($suspiciousCombos as $combo) {
            $allFound = true;
            foreach ($combo as $word) {
                if (stripos($message, $word) === false) {
                    $allFound = false;
                    break;
                }
            }
            if ($allFound) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si hay números escritos en contexto de contacto
     */
    private function hasWrittenNumbersInContactContext(string $message): bool
    {
        // Buscar secuencias de números escritos que podrían formar un teléfono
        $numberPattern = '\b('.implode('|', $this->writtenNumbers).')\b';

        if (preg_match_all('/'.$numberPattern.'/i', $message, $matches)) {
            $foundNumbers = $matches[0];

            $consecutiveLimit = $this->configService->getConfig('moderation.consecutiveNumbersLimit', 7);
            $contextLimit = $this->configService->getConfig('moderation.numbersWithContextLimit', 3);

            // Si hay números escritos consecutivos por encima del límite, es sospechoso
            if (count($foundNumbers) >= $consecutiveLimit) {
                return $this->isInContactContext($message);
            }

            // Si hay números escritos junto con palabras de contacto
            if (count($foundNumbers) >= $contextLimit && $this->isInContactContext($message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si hay números telefónicos en contexto de contacto
     */
    private function hasPhoneNumberInContactContext(string $message): bool
    {
        // PATRONES INEQUÍVOCOS - Bloquear automáticamente sin verificar contexto
        $definitePhonePatterns = [
            // Formato internacional Ecuador: +593 9X XXX XXXX
            '/(?<!\w)(\+593[\s.-]?[96-9]\d[\s.-]?\d{3}[\s.-]?\d{4})(?!\w)/',
            // Formato local Ecuador: 09X XXX XXXX (el más común)
            '/(?<!\w)(0[96-9]\d[\s.-]?\d{3}[\s.-]?\d{4})(?!\w)/',
            // Teléfonos fijos Ecuador: 0[2-7]XXXXXXX (8 dígitos total)
            '/(?<!\w)(0[2-7]\d{7})(?!\w)/',
        ];

        // Verificar patrones inequívocos - bloquear automáticamente
        foreach ($definitePhonePatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true; // Bloquear automáticamente, no necesita contexto
            }
        }

        // PATRONES AMBIGUOS - Verificar contexto antes de bloquear

        // Otros formatos internacionales (requieren contexto)
        if (preg_match('/(?<!\w)(\+\d{1,3}[\s.-]?[96-9]\d{2}[\s.-]?\d{3}[\s.-]?\d{3,4})(?!\w)/', $message)) {
            return $this->isInContactContext($message);
        }

        // Números largos ambiguos (8-12 dígitos) - solo bloquear con contexto fuerte
        if (preg_match('/\b\d{8,12}\b/', $message)) {
            return $this->isInContactContext($message) && ! $this->hasStrongBusinessIndicators($message);
        }

        // Secuencias de números con formato telefónico
        if (preg_match('/\b\d{2,4}[\s.-]\d{3,4}[\s.-]\d{3,4}\b/', $message)) {
            return $this->isInContactContext($message);
        }

        return false;
    }

    /**
     * Determina si el mensaje está en contexto de contacto o negocio
     */
    private function isInContactContext(string $message): bool
    {
        $contactScore = 0;
        $businessScore = 0;

        // Contar palabras de contexto de contacto con pesos dinámicos
        foreach ($this->contactContextWords as $word) {
            if (stripos($message, $word) !== false) {
                $contactScore += $this->getContactWordWeight($word);
            }
        }

        // Contar palabras de contexto de negocio con pesos dinámicos
        foreach ($this->businessContextWords as $word) {
            if (stripos($message, $word) !== false) {
                $businessScore += $this->getBusinessWordWeight($word);
            }
        }

        // Verificar patrones sospechosos adicionales
        if ($this->hasSuspiciousPatterns($message)) {
            $contactScore += $this->configService->getConfig('moderation.contactScorePenalty', 3);
        }

        // Verificar si hay indicadores claros de negocio
        if ($this->hasStrongBusinessIndicators($message)) {
            $businessScore += $this->configService->getConfig('moderation.businessScoreBonus', 15);
        }

        // Verificar si hay indicadores claros de contacto
        if ($this->hasStrongContactIndicators($message)) {
            $contactScore += $this->configService->getConfig('moderation.contactPenaltyHeavy', 20);
        }

        // Lógica mejorada: necesita una diferencia significativa Y un mínimo de score
        $scoreDifference = $contactScore - $businessScore;
        $minimumContactScore = $this->configService->getConfig('moderation.minimumContactScore', 8);
        $scoreDifferenceThreshold = $this->configService->getConfig('moderation.scoreDifferenceThreshold', 5);

        return $contactScore >= $minimumContactScore && $scoreDifference >= $scoreDifferenceThreshold;
    }

    /**
     * Obtiene el peso de una palabra de contacto según su importancia
     */
    private function getContactWordWeight(string $word): int
    {
        // Palabras de contacto directo (peso muy alto)
        $veryHighWeight = ['telefono', 'celular', 'whatsapp', 'contacto', 'llamar', 'numero'];
        // Palabras de encuentro/ubicación (peso alto)
        $highWeight = ['direccion', 'encuentro', 'calle', 'casa', 'vernos', 'coordinar'];
        // Palabras de comunicación externa (peso alto)
        $externalComm = ['gmail', 'facebook', 'instagram', 'email', 'zoom', 'skype'];
        // Palabras de evasión (peso muy alto)
        $evasionWords = ['aparte', 'fuera', 'externo', 'directo', 'privado'];

        if (in_array($word, $veryHighWeight) || in_array($word, $evasionWords)) {
            return 8;
        } elseif (in_array($word, $highWeight) || in_array($word, $externalComm)) {
            return 5;
        } else {
            return 2;
        }
    }

    /**
     * Obtiene el peso de una palabra de negocio según su importancia
     */
    private function getBusinessWordWeight(string $word): int
    {
        // Palabras de negocio fuerte (peso muy alto)
        $veryHighWeight = ['precio', 'cuesta', 'vale', 'vendo', 'comprar', 'productos', 'stock'];
        // Palabras de especificaciones (peso alto)
        $specWords = ['tamaño', 'peso', 'medida', 'capacidad', 'memoria', 'voltios'];
        // Palabras de tiempo/entrega (peso alto)
        $timeWords = ['dias', 'horas', 'demora', 'entrega', 'inmediato', 'rapido'];
        // Palabras de cantidad (peso alto)
        $quantityWords = ['tengo', 'cantidad', 'unidades', 'piezas', 'disponible'];

        if (in_array($word, $veryHighWeight)) {
            return 6;
        } elseif (in_array($word, $specWords) || in_array($word, $timeWords) || in_array($word, $quantityWords)) {
            return 4;
        } else {
            return 2;
        }
    }

    /**
     * Verifica patrones sospechosos adicionales
     */
    private function hasSuspiciousPatterns(string $message): bool
    {
        // Números precedidos por '+' o '00' (muy sospechoso)
        if (preg_match('/(?:\+|00)\d{8,}/', $message)) {
            return true;
        }

        // Frases típicas de evasión
        $evasionPhrases = [
            'te paso mi', 'aqui mi', 'este es mi', 'mi numero es',
            'comunicate', 'escribeme', 'busqueme', 'contactame',
        ];

        foreach ($evasionPhrases as $phrase) {
            if (stripos($message, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si hay indicadores fuertes de contexto de negocio
     */
    private function hasStrongBusinessIndicators(string $message): bool
    {
        // Patrones que claramente indican transacción legítima
        $strongBusinessPatterns = [
            '/tiene(?:s)?\s+\d+\s+(?:productos|articulos|unidades|piezas)/',
            '/cuesta\s+\d+\s*(?:dolares|usd|pesos)/',
            '/precio\s+(?:es\s+)?\d+/',
            '/(?:demora|tarda)\s+\d+\s+(?:dias|horas|semanas)/',
            '/(?:pesa|mide)\s+\d+\s*(?:kg|gr|cm|metros)/',
            '/oferta\s+\d+(?:%|\s+por\s+ciento)/',
            '/memoria\s+\d+\s*(?:gb|mb)/',
            '/capacidad\s+\d+\s*(?:litros|ml)/',
        ];

        foreach ($strongBusinessPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        // Combinaciones de palabras que indican negocio
        $businessCombos = [
            ['vendo', 'productos'], ['precio', 'negociable'],
            ['stock', 'disponible'], ['entrega', 'inmediata'],
            ['oferta', 'especial'], ['descuento', 'por'],
        ];

        foreach ($businessCombos as $combo) {
            $allFound = true;
            foreach ($combo as $word) {
                if (stripos($message, $word) === false) {
                    $allFound = false;
                    break;
                }
            }
            if ($allFound) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica si hay indicadores fuertes de contexto de contacto
     */
    private function hasStrongContactIndicators(string $message): bool
    {
        // Patrones que claramente indican intercambio de contacto
        $strongContactPatterns = [
            '/(?:mi\s+)?(?:numero|telefono|celular)\s+(?:es\s+)?\d+/',
            '/llamame\s+al\s+\d+/',
            '/escribeme\s+al\s+\d+/',
            '/contactame\s+(?:al\s+)?\d+/',
            '/whatsapp\s+\d+/',
            '/(?:mi\s+)?direccion\s+es/',
            '/nos\s+vemos\s+en/',
            '/te\s+paso\s+mi\s+(?:numero|telefono)/',
        ];

        foreach ($strongContactPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registra un strike para un usuario por enviar contenido prohibido
     */
    private function registerStrike(int $userId, int $nStrikes, string $reason): void
    {
        // Verificar si el usuario es un vendedor
        $isSeller = Seller::where('user_id', $userId)->exists();

        if (! $isSeller) {
            // Si no es vendedor, simplemente registramos el strike pero no se bloquea
            UserStrike::create([
                'user_id' => $userId,
                'reason' => $reason,
            ]);

            return;
        }

        // Si es vendedor, registramos el strike
        $strike = UserStrike::create([
            'user_id' => $userId,
            'reason' => $reason,
        ]);

        // Disparar evento de strike añadido
        event(new SellerStrikeAdded($strike->id, $userId));

        // Comprobar si ha acumulado el máximo de strikes
        $this->checkAndBlockSeller($userId, $nStrikes);
    }

    /**
     * Verificar si un usuario ha superado el límite de strikes y bloquearlo si es necesario
     */
    private function checkAndBlockSeller(int $userId, int $limit): void
    {
        $strikeCount = UserStrike::where('user_id', $userId)->count();

        if ($strikeCount >= $limit) {
            $user = User::find($userId);
            if ($user) {
                // Bloquear al usuario
                $user->is_blocked = true;
                $user->save();

                // Actualizar el estado del vendedor
                $seller = Seller::where('user_id', $userId)->first();
                if ($seller) {
                    $seller->status = 'inactive';
                    $seller->save();
                }

                // Disparar evento de cuenta bloqueada
                event(new SellerAccountBlocked(
                    $userId,
                    "Cuenta bloqueada por acumular {$strikeCount} strikes"
                ));
            }
        }
    }

    /**
     * Obtiene la razón de rechazo para un mensaje
     */
    public function getRejectReason(string $message): ?string
    {
        $normalized = $this->normalizeMessage($message);

        if ($this->hasProhibitedNumberEmojis($message)) {
            return 'El mensaje contiene emojis numéricos, los cuales están prohibidos para evitar el intercambio de información de contacto.';
        }

        if ($this->hasContactRequestPatterns($normalized)) {
            return 'El mensaje contiene solicitudes de información de contacto, lo cual no está permitido.';
        }

        if ($this->hasStrongContactIndicators($normalized)) {
            return 'El mensaje contiene patrones claros de intercambio de información de contacto, lo cual no está permitido.';
        }

        if ($this->hasWrittenNumbersInContactContext($normalized)) {
            return 'El mensaje contiene números escritos en un contexto que sugiere intercambio de información de contacto.';
        }

        if ($this->hasPhoneNumberInContactContext($normalized)) {
            return 'El mensaje contiene información que parece ser un número de contacto en un contexto inapropiado.';
        }

        return null;
    }

    /**
     * Censura el contenido prohibido en un mensaje
     */
    public function censorProhibitedContent(string $message): string
    {
        $censored = $message;
        $normalized = $this->normalizeMessage($message);

        // Censurar emojis numéricos (siempre)
        foreach ($this->numberEmojiPatterns as $pattern) {
            $censored = preg_replace($pattern, '⚠️', $censored);
        }

        // Censurar patrones inequívocos de teléfono (siempre)
        $definitePhonePatterns = [
            '/(?<!\w)(\+593[\s.-]?[96-9]\d[\s.-]?\d{3}[\s.-]?\d{4})(?!\w)/',
            '/(?<!\w)(0[96-9]\d[\s.-]?\d{3}[\s.-]?\d{4})(?!\w)/',
            '/(?<!\w)(0[2-7]\d{7})(?!\w)/',
        ];

        foreach ($definitePhonePatterns as $pattern) {
            $censored = preg_replace($pattern, '[NÚMERO CENSURADO]', $censored);
        }

        // Censurar otros números solo si están en contexto de contacto
        if ($this->isInContactContext($normalized)) {
            // Censurar otros formatos internacionales
            $censored = preg_replace('/(?<!\w)(\+\d{1,3}[\s.-]?[96-9]\d{2}[\s.-]?\d{3}[\s.-]?\d{3,4})(?!\w)/', '[NÚMERO CENSURADO]', $censored);

            // Censurar secuencias largas de números solo si no hay indicadores de negocio
            if (! $this->hasStrongBusinessIndicators($normalized)) {
                $censored = preg_replace('/\b\d{8,}\b/', '[NÚMERO CENSURADO]', $censored);

                // Censurar números con formato telefónico
                $censored = preg_replace('/\b\d{2,4}[\s.-]\d{3,4}[\s.-]\d{3,4}\b/', '[NÚMERO CENSURADO]', $censored);
            }
        }

        return $censored;
    }
}
