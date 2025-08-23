<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class AdminLog extends Model
{
    use HasFactory;

    protected $table = 'admin_logs';

    // Solo created_at, no updated_at (los logs no se modifican)
    public $timestamps = false;

    protected $dates = ['created_at'];

    protected $fillable = [
        'level',
        'event_type',
        'message',
        'context',
        'method',
        'url',
        'ip_address',
        'user_agent',
        'user_id',
        'status_code',
        'error_hash',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
        'status_code' => 'integer',
    ];

    /**
     * Niveles de criticidad disponibles
     */
    const LEVEL_CRITICAL = 'critical';

    const LEVEL_ERROR = 'error';

    const LEVEL_WARNING = 'warning';

    const LEVEL_INFO = 'info';

    /**
     * Tipos de eventos comunes
     */
    const EVENT_API_ERROR = 'api_error';

    const EVENT_DATABASE_ERROR = 'database_error';

    const EVENT_PAYMENT_ERROR = 'payment_error';

    const EVENT_AUTHENTICATION_ERROR = 'auth_error';

    const EVENT_VALIDATION_ERROR = 'validation_error';

    const EVENT_SYSTEM_ERROR = 'system_error';

    const EVENT_SECURITY_VIOLATION = 'security_violation';

    /**
     * Usuario que generó el error (si está autenticado)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crear un nuevo log con rate limiting
     */
    public static function createLog(array $data): ?self
    {
        // Generar hash para rate limiting
        $errorHash = md5($data['event_type'].'|'.$data['message'].'|'.($data['url'] ?? ''));
        $data['error_hash'] = $errorHash;
        $data['created_at'] = now();

        // Rate limiting: máximo 1 del mismo error cada 5 minutos
        $cacheKey = 'admin_log_rate_limit:'.$errorHash;

        if (Cache::has($cacheKey)) {
            // Ya existe este error en los últimos 5 minutos, no crear duplicado
            return null;
        }

        // Crear el log
        $log = static::create($data);

        // Establecer rate limit por 5 minutos
        Cache::put($cacheKey, true, 300); // 300 segundos = 5 minutos

        return $log;
    }

    /**
     * Método conveniente para crear log de error crítico
     */
    public static function logCritical(string $eventType, string $message, array $context = [], ?\Throwable $exception = null): ?self
    {
        $data = [
            'level' => self::LEVEL_CRITICAL,
            'event_type' => $eventType,
            'message' => $message,
            'context' => array_merge($context, self::extractRequestContext()),
        ];

        // Agregar información de la excepción si está disponible
        if ($exception) {
            $data['context']['exception'] = [
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        return self::createLog($data);
    }

    /**
     * Método conveniente para crear log de error
     */
    public static function logError(string $eventType, string $message, array $context = [], ?int $statusCode = null): ?self
    {
        $data = [
            'level' => self::LEVEL_ERROR,
            'event_type' => $eventType,
            'message' => $message,
            'context' => array_merge($context, self::extractRequestContext()),
            'status_code' => $statusCode,
        ];

        return self::createLog($data);
    }

    /**
     * Extraer contexto de la request actual
     */
    private static function extractRequestContext(): array
    {
        if (! app()->bound('request') || ! request()) {
            return [];
        }

        $request = request();

        return [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'headers' => [
                'accept' => $request->header('Accept'),
                'content_type' => $request->header('Content-Type'),
                'user_agent' => $request->header('User-Agent'),
            ],
        ];
    }

    /**
     * Scopes para filtrado eficiente
     */
    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeByCritical($query)
    {
        return $query->where('level', self::LEVEL_CRITICAL);
    }

    public function scopeByErrors($query)
    {
        return $query->whereIn('level', [self::LEVEL_CRITICAL, self::LEVEL_ERROR]);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>', now()->subHours($hours));
    }

    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * Obtener estadísticas de logs
     */
    public static function getStats(): array
    {
        $now = now();

        return [
            'total' => self::count(),
            'today' => self::whereDate('created_at', $now->toDateString())->count(),
            'this_week' => self::where('created_at', '>', $now->subWeek())->count(),
            'critical' => self::where('level', self::LEVEL_CRITICAL)->count(),
            'errors' => self::where('level', self::LEVEL_ERROR)->count(),
            'by_event_type' => self::selectRaw('event_type, count(*) as count')
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->pluck('count', 'event_type')
                ->toArray(),
        ];
    }

    /**
     * Limpiar logs antiguos (para el comando de limpieza)
     */
    public static function cleanupOldLogs(int $daysToKeep = 30, int $batchSize = 100): int
    {
        $totalDeleted = 0;

        if ($daysToKeep === 0) {
            // Delete ALL logs when days = 0
            do {
                $deleted = self::limit($batchSize)->delete();
                $totalDeleted += $deleted;

                // Pequeña pausa para no sobrecargar la BD
                if ($deleted > 0) {
                    usleep(10000); // 10ms
                }

            } while ($deleted > 0);
        } else {
            // Delete logs older than specified days
            $cutoffDate = now()->subDays($daysToKeep);

            do {
                $deleted = self::where('created_at', '<', $cutoffDate)
                    ->limit($batchSize)
                    ->delete();

                $totalDeleted += $deleted;

                // Pequeña pausa para no sobrecargar la BD
                if ($deleted > 0) {
                    usleep(10000); // 10ms
                }

            } while ($deleted > 0);
        }

        return $totalDeleted;
    }
}
