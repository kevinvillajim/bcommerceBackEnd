<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EcuadorTimeService
{
    /**
     * Timezone de Ecuador
     */
    const ECUADOR_TIMEZONE = 'America/Guayaquil';

    /**
     * Obtener la hora actual de Ecuador
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::ECUADOR_TIMEZONE);
    }

    /**
     * Crear una instancia Carbon con timezone de Ecuador
     */
    public static function create(?string $time = null): Carbon
    {
        if ($time === null) {
            return self::now();
        }

        return Carbon::parse($time)->setTimezone(self::ECUADOR_TIMEZONE);
    }

    /**
     * Convertir cualquier fecha a timezone de Ecuador
     */
    public static function toEcuadorTime($date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->setTimezone(self::ECUADOR_TIMEZONE);
        }

        return Carbon::parse($date)->setTimezone(self::ECUADOR_TIMEZONE);
    }

    /**
     * Formatear fecha para mostrar al usuario (Ecuador timezone)
     */
    public static function formatForUser($date, string $format = 'Y-m-d H:i:s'): string
    {
        return self::toEcuadorTime($date)->format($format);
    }

    /**
     * Formatear fecha para mostrar al usuario con formato amigable
     */
    public static function formatFriendly($date): string
    {
        $ecuadorTime = self::toEcuadorTime($date);
        
        return $ecuadorTime->format('d/m/Y H:i') . ' ECT';
    }

    /**
     * Obtener la diferencia en tiempo humano desde ahora (Ecuador timezone)
     */
    public static function diffForHumans($date): string
    {
        $ecuadorTime = self::toEcuadorTime($date);
        $nowEcuador = self::now();
        
        return $ecuadorTime->diffForHumans($nowEcuador);
    }

    /**
     * Verificar si una fecha está en el rango de hoy (Ecuador timezone)
     */
    public static function isToday($date): bool
    {
        return self::toEcuadorTime($date)->isToday();
    }

    /**
     * Obtener el inicio del día actual en Ecuador
     */
    public static function todayStart(): Carbon
    {
        return self::now()->startOfDay();
    }

    /**
     * Obtener el fin del día actual en Ecuador
     */
    public static function todayEnd(): Carbon
    {
        return self::now()->endOfDay();
    }

    /**
     * Convertir fecha UTC de base de datos a Ecuador timezone
     * Útil para mostrar en APIs y respuestas JSON
     */
    public static function fromDatabaseToEcuador($databaseDate): Carbon
    {
        // Asumimos que las fechas en BD están en UTC
        $utcDate = Carbon::parse($databaseDate, 'UTC');
        return $utcDate->setTimezone(self::ECUADOR_TIMEZONE);
    }

    /**
     * Preparar fecha para guardar en base de datos (convertir a UTC)
     * Aunque Laravel debería manejar esto automáticamente
     */
    public static function forDatabase($date): Carbon
    {
        return self::toEcuadorTime($date)->utc();
    }

    /**
     * Obtener información completa de timezone para debug
     */
    public static function getTimezoneInfo(): array
    {
        $now = self::now();
        
        return [
            'timezone' => self::ECUADOR_TIMEZONE,
            'current_time' => $now->format('Y-m-d H:i:s'),
            'utc_time' => $now->utc()->format('Y-m-d H:i:s'),
            'offset' => $now->format('P'),
            'timezone_name' => $now->timezoneName,
        ];
    }
}