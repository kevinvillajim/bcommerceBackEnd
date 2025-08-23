<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserStrike extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'reason',
        'message_id', // Referencia al mensaje
        'created_by', // Quién aplicó el strike (admin o sistema)
    ];

    /**
     * Los atributos que deben convertirse a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtiene el usuario al que pertenece este strike.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Método para contar los strikes activos de un usuario
     */
    public static function countActiveStrikes(int $userId): int
    {
        // Puedes ajustar esto si quieres que los strikes caduquen después de cierto tiempo
        // Por ejemplo: where('created_at', '>=', now()->subDays(30))
        return self::where('user_id', $userId)->count();
    }

    /**
     * Verifica si un usuario ha superado el límite de strikes
     */
    public static function hasExceededLimit(int $userId, int $limit): bool
    {
        return self::countActiveStrikes($userId) >= $limit;
    }
}
