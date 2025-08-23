<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class UserInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'interaction_type',
        'item_id',
        'metadata',
        // ❌ QUITADO: 'interaction_time' - Campo no existe en la tabla
    ];

    protected $casts = [
        'metadata' => 'array',
        'item_id' => 'integer',
        // ❌ QUITADO: 'interaction_time' => 'datetime' - Campo no existe
    ];

    /**
     * Tipos de interacción soportados por el profile enricher
     */
    public const INTERACTION_TYPES = [
        'view_product' => 'View Product',
        'add_to_cart' => 'Add to Cart',
        'remove_from_cart' => 'Remove from Cart',
        'add_to_favorites' => 'Add to Favorites',
        'remove_from_favorites' => 'Remove from Favorites',
        'search' => 'Search',
        'browse_category' => 'Browse Category',
        'message_seller' => 'Message Seller',
        'purchase' => 'Purchase',
        'rate_product' => 'Rate Product',
        'view_seller' => 'View Seller',
        'click_product' => 'Click Product',
    ];

    /**
     * Pesos para calcular scoring de interacciones
     */
    public const INTERACTION_WEIGHTS = [
        'view_product' => 1.0,
        'add_to_cart' => 3.0,
        'remove_from_cart' => -1.0,
        'add_to_favorites' => 2.5,
        'remove_from_favorites' => -1.5,
        'search' => 1.5,
        'browse_category' => 0.8,
        'message_seller' => 2.0,
        'purchase' => 5.0,
        'rate_product' => 3.5,
        'view_seller' => 1.2,
        'click_product' => 0.5,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el producto (item_id puede ser product_id)
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'item_id');
    }

    /**
     * Scope para obtener interacciones de un tipo específico
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope para obtener interacciones de usuario
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para obtener interacciones recientes
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Registra una interacción de usuario con validación mejorada
     */
    public static function track(
        int $userId,
        string $interactionType,
        ?int $itemId = null,
        array $metadata = []
    ): self {
        try {
            // ✅ VALIDACIÓN: Si no hay producto, NO registrar búsquedas sin sentido
            if ($itemId === null && $interactionType === 'search') {
                Log::info('Búsqueda ignorada - sin producto asociado', [
                    'type' => $interactionType,
                    'user_id' => $userId,
                    'metadata' => $metadata,
                ]);

                // Crear objeto dummy para no romper el código
                $dummy = new self;
                $dummy->id = 0;
                $dummy->user_id = $userId;
                $dummy->interaction_type = $interactionType;
                $dummy->item_id = null;
                $dummy->metadata = $metadata;

                return $dummy;
            }

            // Validar que tenemos item_id para interacciones de producto
            if ($itemId === null && ! in_array($interactionType, ['purchase'])) {
                throw new \InvalidArgumentException("item_id es requerido para interacción tipo: {$interactionType}");
            }

            // Validar tipo de interacción
            if (! array_key_exists($interactionType, self::INTERACTION_TYPES)) {
                Log::warning('Tipo de interacción no válido', [
                    'type' => $interactionType,
                    'valid_types' => array_keys(self::INTERACTION_TYPES),
                ]);
            }

            // Para interacciones de vista de producto, registrar tiempo si está disponible
            if ($interactionType === 'view_product' && isset($metadata['view_time'])) {
                $metadata['view_duration'] = (int) $metadata['view_time'];
                $metadata['engagement_level'] = self::calculateEngagementLevel($metadata['view_time']);
            }

            // Agregar timestamp a metadata
            $metadata['recorded_at'] = now()->toISOString();

            $interaction = self::create([
                'user_id' => $userId,
                'interaction_type' => $interactionType,
                'item_id' => $itemId,
                'metadata' => $metadata,
                // ❌ QUITADO: 'interaction_time' => now() - Campo no existe en tabla
            ]);

            Log::info('Interacción registrada', [
                'id' => $interaction->id,
                'user_id' => $userId,
                'type' => $interactionType,
                'item_id' => $itemId,
            ]);

            return $interaction;

        } catch (\Exception $e) {
            Log::error('Error registrando interacción', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'type' => $interactionType,
                'item_id' => $itemId,
            ]);
            throw $e;
        }
    }

    /**
     * Calcula el nivel de engagement basado en tiempo de vista
     */
    private static function calculateEngagementLevel(int $viewTime): string
    {
        if ($viewTime >= 120) {
            return 'high';
        } elseif ($viewTime >= 30) {
            return 'medium';
        } elseif ($viewTime >= 5) {
            return 'low';
        } else {
            return 'bounce';
        }
    }

    /**
     * Obtiene el peso de la interacción para scoring
     */
    public function getWeight(): float
    {
        $baseWeight = self::INTERACTION_WEIGHTS[$this->interaction_type] ?? 1.0;

        // Aplicar modificadores basados en metadata
        if ($this->interaction_type === 'view_product' && isset($this->metadata['view_duration'])) {
            $duration = (int) $this->metadata['view_duration'];

            // Bonus por tiempo de vista prolongado
            if ($duration >= 120) {
                $baseWeight *= 2.0; // Doble peso para vistas largas
            } elseif ($duration >= 30) {
                $baseWeight *= 1.5; // 50% más peso para vistas medias
            } elseif ($duration < 5) {
                $baseWeight *= 0.3; // Menor peso para bounces
            }
        }

        return $baseWeight;
    }

    /**
     * Obtiene estadísticas de interacciones para un usuario
     */
    public static function getUserStats(int $userId): array
    {
        $interactions = self::where('user_id', $userId)->get();

        $stats = [
            'total_interactions' => $interactions->count(),
            'by_type' => [],
            'engagement_score' => 0,
            'most_active_category' => null,
            'recent_activity_days' => 0,
        ];

        // Contar por tipo
        foreach (self::INTERACTION_TYPES as $type => $label) {
            $count = $interactions->where('interaction_type', $type)->count();
            $stats['by_type'][$type] = [
                'count' => $count,
                'label' => $label,
            ];
        }

        // Calcular engagement score
        $totalWeight = 0;
        foreach ($interactions as $interaction) {
            $weight = self::INTERACTION_WEIGHTS[$interaction->interaction_type] ?? 1.0;

            // Aplicar modificadores temporales (interacciones recientes valen más)
            $daysSince = now()->diffInDays($interaction->created_at);
            $timeMultiplier = max(0.1, 1 - ($daysSince / 365)); // Decaimiento gradual en un año

            $totalWeight += $weight * $timeMultiplier;
        }

        $stats['engagement_score'] = round($totalWeight, 2);

        // Actividad reciente
        $recentInteraction = $interactions->sortByDesc('created_at')->first();
        if ($recentInteraction) {
            $stats['recent_activity_days'] = now()->diffInDays($recentInteraction->created_at);
        }

        return $stats;
    }
}
